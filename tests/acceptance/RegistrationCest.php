<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Tests\Support\AcceptanceTester;

/**
 * The whole flow with the skautIS fixture dataset: unit 1003 has Anna Malá (101, born 2015-03-12,
 * parents' e-mails) and Karel Bezmailu (105, born 2014-06-06, no contacts).
 */
final class RegistrationCest
{
    public function leaderInvitesAndParentsRegister(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $eventId = $this->createOpenEvent($I, 'Zimní výprava', '2027-02-05', capacity: 1);

        $I->amOnPage('/akce/'.$eventId);
        $I->click('[data-test="invite-link"]');
        $I->see('Přidat účastníky', 'h1');
        $I->seeNumberOfElements('[data-test="member-row"]', 3);
        // The e-mails come in AJAX batches with the default choice: the parents of a child.
        $I->waitForElementNotVisible('[data-test="address-loader"]', 15);
        $anna = '[data-addresses="101"]';
        $I->seeCheckboxIsChecked($anna.' input[value="matka.mala@example.cz"]');
        $I->seeCheckboxIsChecked($anna.' input[value="otec.maly@example.cz"]');
        $I->dontSeeCheckboxIsChecked($anna.' input[value="anna.mala@example.cz"]');
        $I->see('bez e-mailu ve skautISu', '[data-addresses="105"]');
        // A type for everyone at once, then one address for one member only.
        $I->checkOption('[data-test="address-type-father"]');
        $I->uncheckOption('[data-test="address-type-father"]');
        $I->dontSeeCheckboxIsChecked($anna.' input[value="otec.maly@example.cz"]');
        $I->checkOption($anna.' input[value="anna.mala@example.cz"]');
        $I->checkOption('Malá Anna');
        $I->checkOption('Bezmailu Karel');
        $I->clickByJs('[data-test="invite-submit"]');
        $I->waitForText('Přidáno 2 členů', 10);
        $I->see('matka.mala@example.cz', '[data-test="registration-list"]');
        $I->see('anna.mala@example.cz', '[data-test="registration-list"]');
        $I->dontSee('otec.maly@example.cz', '[data-test="registration-list"] [data-test="recipient"]');
        $I->see('bez e-mailu', '[data-test="registration-list"]');
        $I->see('2', '[data-test="count-invited"]');
        $I->dontSeeElement('[data-test="bulk-toggles"]');
        // The row is edited in place, like the e-mail column of payments in h.skauting.
        $row = '//div[@data-test="registration-item"][.//a[normalize-space()="Malá Anna"]]';
        $I->click($row.'//summary');
        $I->checkOption($row.'//label[contains(., "otec.maly@example.cz")]//input');
        $I->click($row.'//input[@name="save"]');
        $I->waitForText('Adresy u Malá Anna byly uloženy', 10);
        $I->see('otec.maly@example.cz', $row);

        $I->amOnPage('/akce/'.$eventId.'/pozvani');
        $I->fillField('[data-test="non-member-form"] input[name="email"]', 'pavel@example.cz');
        $I->click('Přidat nečlena');
        $I->waitForText('Nečlen pavel@example.cz byl přidán', 10);

        $annaLink = $this->personalLink($I, $eventId, 'Malá Anna');
        $I->amOnUrl($annaLink);
        $I->seeInTitle('Ověření');
        $I->fillDate('birthDate', '2015-03-13');
        $I->click('Pokračovat');
        $I->waitForText('Datum narození nesouhlasí', 10);
        $I->fillDate('birthDate', '2015-03-12');
        $I->click('Pokračovat');
        $I->waitForElement('input[name="attending"]', 10);
        $I->see('Malá Anna', '[data-test="participant-name"]');
        // The choice comes first, it decides what the rest of the form shows.
        $I->assertTrue((bool) $I->executeJS("return document.querySelector('input[name=attending]').compareDocumentPosition(document.querySelector('input[name=agreement]')) & Node.DOCUMENT_POSITION_FOLLOWING;"));
        $I->selectOption('input[name="attending"]', 'yes');
        // An invited member gives no contact e-mail: the mail goes to the addresses the leader chose.
        $I->dontSeeElement('input[name="contactEmail"]');
        $I->see('E-maily k akci chodí na matka.mala@example.cz, otec.maly@example.cz, anna.mala@example.cz', '[data-test="mail-addresses"]');
        $I->fillField('Alergie a léky', 'ořechy');
        $I->checkOption('autobus');
        $I->checkOption('sobota');
        $I->checkOption('agreement');
        // The required yes/no field has to be ticked; nette-forms shows the message in a modal dialog.
        $I->click('Odeslat přihlášku');
        $I->waitForText('Toto pole je třeba zaškrtnout.', 10);
        $I->executeJS("document.querySelector('dialog.netteFormsModal')?.close();");
        $I->waitForElementNotVisible('dialog.netteFormsModal', 5);
        $I->checkOption('Má zdravotní posudek');
        $I->click('Odeslat přihlášku');
        $I->waitForText('Přihláška byla uložena', 10);
        $I->see('přihlášen', '[data-test="registration-state"]');
        $I->see('ořechy', '[data-test="summary"]');
        $I->see('autobus', '[data-test="summary"]');
        $I->see('sobota', '[data-test="summary"]');

        $karelLink = $this->personalLink($I, $eventId, 'Bezmailu Karel');
        $I->amOnUrl($karelLink);
        $I->fillDate('birthDate', '2014-06-06');
        $I->click('Pokračovat');
        $I->waitForElement('input[name="attending"]', 10);
        $I->selectOption('input[name="attending"]', 'yes');
        $I->fillField('contactEmail', 'karel@example.cz');
        $I->fillField('note', '=HYPERLINK("http://evil.example")');
        $I->checkOption('vlastní');
        $I->checkOption('Má zdravotní posudek');
        $I->checkOption('agreement');
        $I->click('Odeslat přihlášku');
        // The last place is gone: the submit tells right away that Karel is a substitute.
        $I->waitForText('účastník je proto zapsaný jako náhradník', 10);
        $I->see('čekací listina', '[data-test="registration-state"]');

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        // The confirmations of both answers are one summary, not a panel each (no worker runs in the test).
        $I->seeNumberOfElements('[data-test="automatic-emails"]', 1);
        $I->see('čeká na odeslání 2', '[data-test="automatic-emails"]');
        $I->dontSeeElement('[data-test="task-queued"]');
        $I->see('1', '[data-test="count-registered"]');
        $I->see('1', '[data-test="count-waitlisted"]');
        $I->see('Akce je plná, na čekací listině je 1', '[data-test="waitlist-panel"]');
        $I->click('Bezmailu Karel');
        $I->see('vlastní', '[data-test="field-values"]');
        // The event is full: the waitlisted person comes in only over the capacity, after a confirmation.
        $I->dontSee('Přesunout mezi přihlášené');
        $I->click('Přijmout nad kapacitu (1 míst je obsazeno)');
        $I->acceptPopup();
        $I->waitForText('Přijato nad kapacitu akce.', 10);
        $I->see('nad kapacitu', '[data-test="registration-log"]');
        $I->see('přihlášen', '[data-test="registration-state"]');

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->seeElement('[data-test="export-link"]');
        $csv = $I->executeAsyncJS("const done = arguments[0]; fetch('/akce/".$eventId."/prihlasky/export').then(r => r.text()).then(done);");
        $I->assertStringContainsString('Doprava', (string) $csv);
        $I->assertStringContainsString('ořechy', (string) $csv);
        $I->assertStringContainsString('"\'=HYPERLINK', (string) $csv, 'a cell starting with = is neutralised');
        $I->assertStringContainsString('Malá;Anna', (string) $csv);
    }

    public function nonMemberRegistersDeclinesAndCancels(AcceptanceTester $I): void
    {
        $I->loginAsFixtureUser();
        $eventId = $this->createOpenEvent($I, 'Výlet na hrad', '2027-03-06', capacity: null);

        $I->amOnPage('/akce/'.$eventId.'/pozvani');
        $I->fillField('[data-test="non-member-form"] input[name="email"]', 'eliska@example.cz');
        $I->fillField('[data-test="non-member-form"] input[name="firstName"]', 'Eliška');
        $I->fillField('[data-test="non-member-form"] input[name="lastName"]', 'Nováková');
        $I->click('Přidat nečlena');
        $I->waitForText('Nečlen Nováková Eliška byl přidán', 10);
        $I->amOnPage('/akce/'.$eventId.'/pozvani');
        $I->checkOption('Malá Anna');
        $I->clickByJs('[data-test="invite-submit"]');
        $I->waitForText('Přidáno 1 členů', 10);

        $guestLink = $this->personalLink($I, $eventId, 'Nováková Eliška');
        $I->amOnUrl($guestLink);
        $I->see('Nováková Eliška', '[data-test="participant-name"]');
        $I->dontSee('Datum narození účastníka');
        $I->fillField('email', 'jiny@example.cz');
        $I->click('Pokračovat');
        $I->waitForText('E-mail nesouhlasí', 10);
        $I->fillField('email', 'eliska@example.cz');
        $I->click('Pokračovat');
        $I->waitForElement('input[name="attending"]', 10);
        $I->selectOption('input[name="attending"]', 'yes');
        $I->fillField('firstName', 'Eliška');
        $I->fillField('lastName', 'Nováková');
        $I->checkOption('žena');
        $I->fillDate('birthDate', '2013-05-05');
        $I->fillField('street', 'Krátká 3');
        $I->fillField('city', 'Plzeň');
        $I->fillField('postcode', '30100');
        $I->fillField('contactEmail', 'eliska@example.cz');
        $I->fillField('Alergie a léky', 'pyl');
        $I->checkOption('vlastní');
        $I->checkOption('Má zdravotní posudek');
        $I->checkOption('agreement');
        $I->click('Odeslat přihlášku');
        $I->waitForText('Přihláška byla uložena', 10);
        $I->see('přihlášen', '[data-test="registration-state"]');

        $I->click('[data-test="cancel-link"]');
        $I->click('Ano, zrušit účast');
        $I->waitForText('Účast byla zrušena', 10);
        $I->see('zrušeno', '[data-test="registration-state"]');

        // A new browser (no session) may not see the filled-in data without the code from phase 3.
        $I->resetCookie('PHPSESSID');
        $I->amOnUrl($guestLink);
        $I->seeElement('[data-test="code"]');
        $I->loginAsFixtureUser();

        $annaLink = $this->personalLink($I, $eventId, 'Malá Anna');
        $I->amOnUrl($annaLink);
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $I->fillDate('birthDate', '2001-01-01');
            $I->click('Pokračovat');
            $I->wait(0.3);
        }
        $I->waitForElement('[data-test="blocked"]', 10);

        // A forwarded, unverified link must not accept the form, the cancel or a code through a direct signal POST.
        foreach (['registrationForm-submit' => 'attending=yes&contactEmail=utocnik%40example.cz&agreement=1&send=1', 'cancelForm-submit' => 'cancel=1', 'codeForm-submit' => 'code=1234&verify=1'] as $signal => $body) {
            $status = (string) $I->executeAsyncJS('const done = arguments[0]; fetch('.json_encode($annaLink.'?do='.$signal).", {method: 'POST', credentials: 'same-origin', redirect: 'manual', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: ".json_encode($body)."}).then(r => done(String(r.status) + ' ' + r.type));");
            $I->assertStringStartsWith('0 opaqueredirect', $status, $signal.' is redirected away, not processed');
        }

        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->see('0', '[data-test="count-registered"]');
        $I->see('1', '[data-test="count-cancelled"]');
        $I->click('Malá Anna', '[data-test="registration-list"]');
        $I->dontSee('utocnik@example.cz');
        $I->see('zobrazeno', '[data-test="registration-state"]');
        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->click('Nováková Eliška');
        $I->see('Plzeň', '[data-test="registration-data"]');
        $I->see('Účast zrušena účastníkem', '[data-test="registration-log"]');
        $I->fillField('input[name="email"]', 'babicka@example.cz');
        $I->click('Přidat e-mail');
        $I->waitForText('E-mail byl přidán', 10);
        $I->see('babicka@example.cz', '[data-test="recipients"]');
        $I->seeElement('[data-test="personal-link"]');
    }

    private function createOpenEvent(AcceptanceTester $I, string $name, string $date, ?int $capacity): string
    {
        $I->amOnPage('/akce/nova');
        $I->fillField('name', $name);
        $I->selectOption('skautisTypeId', '2) Výprava');
        $I->selectOption('skautisScopeId', 'Oddílová');
        $I->fillDate('dateFrom', $date);
        $I->fillDate('dateTo', $date);
        if ($capacity !== null) {
            $I->fillField('capacity', (string) $capacity);
        }
        $I->fillField('contactEmail', 'jan.novak@example.cz');
        $I->clickByJs('[data-test="event-submit"]');
        $I->waitForText('Akce byla založena', 10);
        $eventId = $I->grabFromCurrentUrl('~/akce/(\d+)~');

        $I->amOnPage('/akce/'.$eventId.'/pole');
        $I->selectOption('type', 'text');
        $I->fillField('label', 'Alergie a léky');
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);
        $I->selectOption('type', 'výběr jedné možnosti');
        $I->fillField('label', 'Doprava');
        $I->fillField('options', "autobus\nvlastní");
        $I->checkOption('required');
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);
        $I->selectOption('type', 'výběr více možností');
        $I->fillField('label', 'Přijede na');
        $I->fillField('options', "pátek\nsobota");
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);
        $I->selectOption('type', 'ano / ne');
        $I->fillField('label', 'Má zdravotní posudek');
        $I->checkOption('required');
        $I->click('Přidat pole');
        $I->waitForText('Pole bylo přidáno', 10);

        $I->amOnPage('/akce/'.$eventId);
        $I->click('Otevřít přihlašování');
        $I->waitForText('Stav akce byl změněn', 10);

        return $eventId;
    }

    private function personalLink(AcceptanceTester $I, string $eventId, string $name): string
    {
        $I->amOnPage('/akce/'.$eventId.'/prihlasky');
        $I->click($name, '[data-test="registration-list"]');
        $I->see($name, '[data-test="registration-name"]');
        $I->waitForElement('[data-test="personal-link"]', 10);

        return (string) $I->grabValueFrom('[data-test="personal-link"]');
    }
}
