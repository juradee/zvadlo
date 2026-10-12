<?php

declare(strict_types=1);

namespace App\Model\Mailing;

use App\Model\Registration\Registration;
use App\Model\Registration\RegistrationRepository;
use DateTimeImmutable;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * The one-time code a non-member needs to open an already filled registration. It is the only mail sent
 * inside the request: the person is waiting for it.
 */
final class EditCodeService
{
    public const string VALIDITY = '+15 minutes';
    public const int MIN_INTERVAL_SECONDS = 60;
    public const int MAX_PER_HOUR = 5;

    private readonly Cache $cache;

    public function __construct(
        private readonly EventMailer $mailer,
        private readonly RegistrationRepository $registrations,
        private readonly string $appSecret,
        Storage $storage,
    ) {
        $this->cache = new Cache($storage, 'registration.editCode');
    }

    /**
     * @return bool whether the code could be sent (the registration has an address)
     *
     * @throws EditCodeThrottledException when a code was sent less than a minute ago or too often this hour;
     *                                    the code already sent stays valid
     * @throws EditCodeNotSentException   when the mail could not be delivered (does not count towards the limits)
     */
    public function issue(Registration $registration): bool
    {
        $now = new DateTimeImmutable();
        $key = 'r'.$registration->getId();
        /** @var list<int> $sent unix times of the codes sent during the last hour */
        $sent = array_values(array_filter((array) ($this->cache->load($key) ?? []), static fn (mixed $at): bool => \is_int($at) && $at > $now->getTimestamp() - 3600));
        if ($sent !== [] && max($sent) > $now->getTimestamp() - self::MIN_INTERVAL_SECONDS) {
            throw new EditCodeThrottledException('Kód jsme poslali před chvílí, zkontrolujte e-mail (i složku hromadné pošty). Nový lze poslat za minutu.');
        }
        if (\count($sent) >= self::MAX_PER_HOUR) {
            throw new EditCodeThrottledException('Kód jsme poslali už několikrát. Použijte poslední z nich, nebo to zkuste za hodinu.');
        }

        $code = str_pad((string) random_int(0, 9999), 4, '0', \STR_PAD_LEFT);
        $registration->issueEditCode($this->hash($registration, $code), $now->modify(self::VALIDITY), $now);
        $this->registrations->save();

        $messages = $this->mailer->sendToRegistration($registration, EmailType::EditCode, null, ['kod' => $code]);
        if ($messages === []) {
            return false;
        }
        if (array_filter($messages, static fn (EmailMessage $message): bool => $message->isSent()) === []) {
            throw new EditCodeNotSentException('Kód se nepodařilo odeslat. Zkuste to prosím za chvíli znovu, případně napište vedoucímu.');
        }
        $sent[] = $now->getTimestamp();
        $this->cache->save($key, $sent, [Cache::Expire => '1 hour']);

        return true;
    }

    public function verify(Registration $registration, string $code): bool
    {
        $verified = $registration->verifyEditCode($this->hash($registration, trim($code)), new DateTimeImmutable());
        $this->registrations->save();

        return $verified;
    }

    private function hash(Registration $registration, string $code): string
    {
        return hash_hmac('sha256', $registration->getId().':'.$code, $this->appSecret);
    }
}
