<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Registration;

use App\Model\Event\Event;
use App\Model\Event\EventField;
use App\Model\Event\EventFieldType;
use App\Model\Event\EventInput;
use App\Model\Event\EventState;
use App\Model\Registration\LogActor;
use App\Model\Registration\PairingHint;
use App\Model\Registration\RecipientSource;
use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationAnswer;
use App\Model\Registration\RegistrationOrigin;
use App\Model\Registration\RegistrationState;
use App\Model\Registration\RegistrationToken;
use App\Model\Registration\Sex;
use App\Model\Registration\VerificationMethod;
use App\Model\Security\Crypto;
use App\Model\Skautis\Dto\Person;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class RegistrationTest extends Unit
{
    private DateTimeImmutable $now;

    private Crypto $crypto;

    protected function _before(): void
    {
        $this->now = new DateTimeImmutable('2026-10-05 10:00');
        $this->crypto = new Crypto('unit-test-secret');
    }

    public function testMemberInvitationCopiesPersonData(): void
    {
        $registration = $this->member();

        self::assertTrue($registration->isMember());
        self::assertSame(VerificationMethod::BirthDate, $registration->getVerificationMethod());
        self::assertSame(RegistrationOrigin::Invited, $registration->getOrigin());
        self::assertSame('Malá Anna', $registration->getDisplayName());
        self::assertSame(Sex::Female, $registration->getSex());
        self::assertSame(RegistrationState::Invited, $registration->getState());
        self::assertNull($registration->getInvitedAt());
        self::assertCount(1, $registration->getLogs());
        self::assertSame(LogActor::User, $registration->getLogs()[0]->getActor());
    }

    public function testNonMemberIsUnlockedByTheInvitationEmailUntilFilledIn(): void
    {
        $registration = $this->nonMember();

        self::assertFalse($registration->isMember());
        self::assertSame(VerificationMethod::Email, $registration->getVerificationMethod());
        self::assertSame('pavel@example.cz', $registration->getDisplayName(), 'no name yet, the e-mail stands in');
        self::assertSame(['pavel@example.cz'], $registration->getSelectedRecipientEmails());

        self::assertFalse($registration->verifyEmail('jiny@example.cz', $this->now));
        self::assertTrue($registration->verifyEmail(' Pavel@Example.cz ', $this->now));

        $registration->respond(new RegistrationAnswer(attending: true, contactEmail: 'pavel@example.cz', firstName: 'Pavel', lastName: 'Host', sex: Sex::Male, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'), true, $this->now);
        self::assertSame('Host Pavel', $registration->getDisplayName());
        self::assertTrue($registration->asksForContactEmail(), 'a non-member keeps one contact e-mail');
        self::assertSame(['pavel@example.cz'], $registration->getMailAddresses());
        self::assertSame(VerificationMethod::Code, $registration->getVerificationMethod(), 'filled data need a stronger check');
    }

    public function testBirthDateVerificationBlocksAfterFiveFailures(): void
    {
        $registration = $this->member();
        $wrong = new DateTimeImmutable('2015-03-13');

        for ($attempt = 1; $attempt < Registration::MAX_VERIFICATION_ATTEMPTS; ++$attempt) {
            self::assertFalse($registration->verifyBirthDate($wrong, $this->now));
            self::assertFalse($registration->isBlocked($this->now));
        }

        self::assertFalse($registration->verifyBirthDate($wrong, $this->now));
        self::assertTrue($registration->isBlocked($this->now));
        // While blocked even the right answer is refused and wrong ones do not restart the counter.
        self::assertFalse($registration->verifyBirthDate(new DateTimeImmutable('2015-03-12'), $this->now->modify('+1 minute')));
        self::assertTrue($registration->isBlocked($this->now->modify('+14 minutes')));
        self::assertFalse($registration->isBlocked($this->now->modify('+16 minutes')));

        self::assertTrue($registration->verifyBirthDate(new DateTimeImmutable('2015-03-12'), $this->now->modify('+16 minutes')));
        self::assertNull($registration->getBlockedUntil());
    }

    public function testSeenIsRecordedOnlyOnce(): void
    {
        $registration = $this->member();
        $registration->markSeen($this->now);
        $registration->markSeen($this->now->modify('+1 hour'));

        self::assertSame(RegistrationState::Seen, $registration->getState());
        self::assertEquals($this->now, $registration->getSeenAt());
        self::assertCount(2, $registration->getLogs());
    }

    public function testAnswerRegistersOrWaitlistsAndKeepsThePlaceOnEdit(): void
    {
        $registration = $this->member();

        $registration->respond($this->answer(attending: true, note: 'první'), capacityAvailable: false, now: $this->now);
        self::assertSame(RegistrationState::Waitlisted, $registration->getState());

        $registration->respond($this->answer(attending: true, note: 'druhá'), capacityAvailable: true, now: $this->now);
        self::assertSame(RegistrationState::Waitlisted, $registration->getState(), 'waitlisted stays until the leader promotes');

        $registration->promote(LogActor::User, 1, false, $this->now);
        self::assertSame(RegistrationState::Registered, $registration->getState());

        $registration->respond($this->answer(attending: true, note: 'třetí'), capacityAvailable: false, now: $this->now);
        self::assertSame(RegistrationState::Registered, $registration->getState(), 'a registered person keeps the place while editing');
        self::assertSame('třetí', $registration->getNote());
        self::assertSame('rodic@example.cz', $registration->getContactEmail());
    }

    public function testDeclineAndCancel(): void
    {
        $registration = $this->member();
        $registration->respond($this->answer(attending: false, declineReason: 'nemoc'), capacityAvailable: true, now: $this->now);
        self::assertSame(RegistrationState::Declined, $registration->getState());
        self::assertSame('nemoc', $registration->getDeclineReason());

        $registration->respond($this->answer(attending: true), capacityAvailable: true, now: $this->now);
        self::assertSame(RegistrationState::Registered, $registration->getState());
        self::assertNull($registration->getDeclineReason());

        $registration->cancel(LogActor::Participant, null, $this->now);
        self::assertSame(RegistrationState::Cancelled, $registration->getState());
        self::assertEquals($this->now, $registration->getCancelledAt());

        $registration->markDeclinedByLeader(7, 'po telefonu', $this->now);
        self::assertSame(RegistrationState::Declined, $registration->getState());
    }

    public function testRejectionRemovesPersonalDataButKeepsTheBlockingKeys(): void
    {
        $registration = $this->member();
        $registration->addRecipient('matka@example.cz', null, RecipientSource::Mother);
        $registration->respond($this->answer(attending: true, note: 'tajné'), true, $this->now);

        $registration->reject(7, 'nepatří k nám', 'email-hash', $this->now);

        self::assertSame(RegistrationState::Rejected, $registration->getState());
        self::assertNull($registration->getFirstName());
        self::assertNull($registration->getBirthDate());
        self::assertNull($registration->getContactEmail());
        self::assertNull($registration->getNote());
        self::assertSame([], $registration->getRecipients());
        self::assertSame(101, $registration->getSkautisPersonId(), 'the person id blocks a second sign-up');
        self::assertSame('email-hash', $registration->getEmailHash());
        self::assertNotNull($registration->getAnonymizedAt());
        self::assertFalse($registration->canBeChangedByParticipant($this->now));
        self::assertStringContainsString('nepatří k nám', $registration->getLogs()[\count($registration->getLogs()) - 1]->getMessage());
    }

    public function testPublicSignUpIsPairedWhenAMemberMatched(): void
    {
        $event = $this->event();
        $person = new Person(101, 'Anna', 'Malá', '', new DateTimeImmutable('2015-03-12'), 'female', 1003);
        $answer = new RegistrationAnswer(attending: true, contactEmail: 'rodic@example.cz', firstName: 'anna', lastName: 'mala', birthDate: new DateTimeImmutable('2015-03-12'));

        $paired = Registration::registerPublic($event, $answer, $this->token(), $person, null, true, $this->now);
        self::assertTrue($paired->isMember());
        self::assertSame(RegistrationOrigin::Public, $paired->getOrigin());
        self::assertSame('Malá Anna', $paired->getDisplayName(), 'skautIS spelling wins');
        self::assertSame(RegistrationState::Registered, $paired->getState());
        self::assertNull($paired->getPairingHint());

        $unpaired = Registration::registerPublic($event, $answer, $this->token(), null, PairingHint::Possible, false, $this->now);
        self::assertFalse($unpaired->isMember());
        self::assertSame(PairingHint::Possible, $unpaired->getPairingHint());
        self::assertSame(RegistrationState::Waitlisted, $unpaired->getState());
        self::assertSame('mala anna', $unpaired->getDisplayName());

        $unpaired->pairWithPerson($person, 7, $this->now);
        self::assertTrue($unpaired->isMember());
        self::assertNull($unpaired->getPairingHint());
        self::assertSame('Malá Anna', $unpaired->getDisplayName());
    }

    public function testMemberDataAreNeverOverwrittenByTheAnswer(): void
    {
        $registration = $this->member();
        $registration->respond(new RegistrationAnswer(attending: true, firstName: 'Jiná', lastName: 'Osoba', birthDate: new DateTimeImmutable('2000-01-01')), true, $this->now);

        self::assertSame('Malá Anna', $registration->getDisplayName());
        self::assertSame('2015-03-12', $registration->getBirthDate()?->format('Y-m-d'));
    }

    public function testParticipantMayChangeOnlyUntilTheDeadline(): void
    {
        $event = $this->event(dateFrom: '2026-11-20', deadline: '2026-11-10', state: EventState::Draft);
        $registration = $this->member($event);
        self::assertFalse($registration->canBeChangedByParticipant($this->now), 'draft');

        $event->changeState(EventState::Open, $this->now);
        self::assertTrue($registration->canBeChangedByParticipant($this->now));
        self::assertFalse($registration->canBeCancelledByParticipant($this->now), 'nothing to cancel yet');
        self::assertFalse($registration->canBeChangedByParticipant(new DateTimeImmutable('2026-11-11')), 'pending after the deadline');

        $registration->respond($this->answer(attending: true), true, $this->now);
        self::assertTrue($registration->canBeCancelledByParticipant(new DateTimeImmutable('2026-11-10 20:00')), 'the deadline day still counts');
        self::assertFalse($registration->canBeChangedByParticipant(new DateTimeImmutable('2026-11-11')), 'one limit: no change after the deadline either');
        self::assertFalse($registration->canBeCancelledByParticipant(new DateTimeImmutable('2026-11-11')));

        $event->changeState(EventState::Closed, $this->now);
        self::assertFalse($registration->canBeChangedByParticipant($this->now), 'closed');
    }

    public function testFieldValuesAreStoredPerType(): void
    {
        $event = $this->event();
        $text = new EventField($event, EventFieldType::Text, 'Alergie', null, false, 1);
        $flag = new EventField($event, EventFieldType::Boolean, 'Vlastní doprava', null, false, 2);
        $choice = new EventField($event, EventFieldType::MultiChoice, 'Dny', null, false, 3, ['pátek', 'sobota', 'neděle']);
        $registration = $this->member($event);

        $registration->setTextValue($text, 'ořechy');
        $registration->setBooleanValue($flag, true);
        $registration->setOptionValues($choice, [$choice->getOptions()[0], $choice->getOptions()[2]]);

        self::assertSame('ořechy', $registration->getFieldValueText($text));
        self::assertSame('ano', $registration->getFieldValueText($flag));
        self::assertSame('pátek, neděle', $registration->getFieldValueText($choice));

        $registration->setOptionValues($choice, [$choice->getOptions()[2], $choice->getOptions()[1]]);
        self::assertSame('sobota, neděle', $registration->getFieldValueText($choice));
        self::assertCount(2, $registration->getSelectedOptions($choice));

        $registration->setTextValue($text, '');
        $registration->setBooleanValue($flag, false);
        $registration->setOptionValues($choice, []);

        self::assertSame('', $registration->getFieldValueText($text));
        self::assertSame('ne', $registration->getFieldValueText($flag));
        self::assertSame('', $registration->getFieldValueText($choice));
    }

    public function testRecipientsAreUniqueLowercaseAndOnlySelectedOnesGetMail(): void
    {
        $registration = $this->member();

        self::assertTrue($registration->addRecipient('Matka@Example.cz', 'Matka', RecipientSource::Mother));
        self::assertTrue($registration->addRecipient('anna@example.cz', null, RecipientSource::Main, selected: false));
        self::assertFalse($registration->addRecipient('matka@example.cz ', null, RecipientSource::Manual));
        self::assertFalse($registration->addRecipient('  ', null, RecipientSource::Manual));
        self::assertSame(['matka@example.cz', 'anna@example.cz'], $registration->getRecipientEmails());
        self::assertSame(['matka@example.cz'], $registration->getSelectedRecipientEmails());
        self::assertSame(['matka@example.cz'], $registration->getMailAddresses());

        $registration->findRecipient('anna@example.cz')?->setSelected(true);
        self::assertSame(['matka@example.cz', 'anna@example.cz'], $registration->getMailAddresses());

        $registration->respond($this->answer(attending: true), true, $this->now);
        self::assertSame(['matka@example.cz', 'anna@example.cz'], $registration->getMailAddresses(), 'an invited member gets mail on the chosen addresses, not on a contact e-mail');

        foreach ($registration->getRecipients() as $recipient) {
            $recipient->setSelected(false);
        }
        self::assertTrue($registration->asksForContactEmail(), 'with no chosen address the form asks for one');
        self::assertSame(['rodic@example.cz'], $registration->getMailAddresses());
    }

    public function testTokenIsStableUntilReplaced(): void
    {
        $registration = $this->member();
        $hash = $registration->getTokenHash();
        $encrypted = $registration->getTokenEncrypted();

        self::assertSame($hash, RegistrationToken::hash($this->crypto->decrypt($encrypted)), 'hash and encrypted token belong together');

        $registration->markInvited($this->now);
        $registration->markSeen($this->now);
        self::assertSame($hash, $registration->getTokenHash(), 'sending and opening never change the link');

        $registration->replaceToken($this->token(), 7, $this->now);
        self::assertNotSame($hash, $registration->getTokenHash());
    }

    private function answer(bool $attending, ?string $declineReason = null, ?string $note = null): RegistrationAnswer
    {
        return new RegistrationAnswer(attending: $attending, declineReason: $declineReason, contactEmail: 'rodic@example.cz', note: $note);
    }

    public function testNonMemberKeepsTheCodeAfterTheSyncCreatedTheirPerson(): void
    {
        $guest = $this->nonMember();
        $guest->respond(new RegistrationAnswer(attending: true, contactEmail: 'pavel@example.cz', firstName: 'Pavel', lastName: 'Host', sex: Sex::Male, birthDate: new DateTimeImmutable('2012-02-02'), street: 'A 1', city: 'Brno', postcode: '60200'), true, $this->now);
        self::assertSame(VerificationMethod::Code, $guest->getVerificationMethod());

        $guest->recordSkautisParticipant(7001, 9001, LogActor::User, 1, $this->now);
        self::assertSame(9001, $guest->getSkautisPersonId());
        self::assertSame(VerificationMethod::Code, $guest->getVerificationMethod(), 'the birth date the non-member typed is no secret');
    }

    private function member(?Event $event = null): Registration
    {
        $person = new Person(101, 'Anna', 'Malá', '', new DateTimeImmutable('2015-03-12'), 'female', 1003);

        return Registration::inviteMember($event ?? $this->event(), $person, $this->token(), 1, $this->now);
    }

    private function nonMember(?Event $event = null): Registration
    {
        return Registration::inviteNonMember($event ?? $this->event(), 'pavel@example.cz', null, null, $this->token(), 1, $this->now);
    }

    private function token(): RegistrationToken
    {
        return RegistrationToken::generate($this->crypto);
    }

    private function event(string $dateFrom = '2026-11-20', ?string $deadline = null, EventState $state = EventState::Open): Event
    {
        $event = new Event(1003, 'vyprava', new EventInput(
            name: 'Výprava',
            skautisTypeId: '2',
            skautisScopeId: '2',
            dateFrom: new DateTimeImmutable($dateFrom),
            dateTo: new DateTimeImmutable($dateFrom),
            place: null,
            description: null,
            registrationDeadline: $deadline === null ? null : new DateTimeImmutable($deadline),
            capacity: null,
            price: null,
            contactName: 'Jan Novák',
            contactEmail: 'jan@example.cz',
            contactPhone: null,
        ), 'web-token', 1, $this->now);

        if ($state === EventState::Open) {
            $event->changeState(EventState::Open, $this->now);
        }

        return $event;
    }
}
