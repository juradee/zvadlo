<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Security;

use App\Model\Security\Crypto;
use App\Model\Security\Fingerprint;
use Codeception\Test\Unit;
use DateTimeImmutable;
use RuntimeException;

final class CryptoAndFingerprintTest extends Unit
{
    public function testEncryptedValueRoundTripsOnlyWithTheSameSecret(): void
    {
        $crypto = new Crypto('secret-one');
        $encrypted = $crypto->encrypt('token-123');

        self::assertNotSame('token-123', $encrypted);
        self::assertNotSame($encrypted, $crypto->encrypt('token-123'), 'a fresh nonce every time');
        self::assertSame('token-123', $crypto->decrypt($encrypted));

        $this->expectException(RuntimeException::class);
        (new Crypto('secret-two'))->decrypt($encrypted);
    }

    public function testFingerprintsIgnoreCaseDiacriticsAndSpacing(): void
    {
        $fingerprint = new Fingerprint('secret');
        $birthDate = new DateTimeImmutable('2015-03-12');

        self::assertSame(
            $fingerprint->person('Malá', 'Anna', $birthDate),
            $fingerprint->person('  mala ', 'ANNA', new DateTimeImmutable('2015-03-12 15:00')),
        );
        self::assertNotSame($fingerprint->person('Malá', 'Anna', $birthDate), $fingerprint->person('Malá', 'Anna', new DateTimeImmutable('2015-03-13')));
        self::assertNotSame($fingerprint->person('Malá', 'Anna', $birthDate), (new Fingerprint('other'))->person('Malá', 'Anna', $birthDate), 'keyed');
        self::assertSame($fingerprint->person('Malá-Nováková', 'Anna Marie', $birthDate), $fingerprint->person('mala  novakova', 'anna-marie', $birthDate), 'hyphens and spaces are the same');
        self::assertSame('d artagnan', Fingerprint::normalize(" D'Artagnan "));
        self::assertSame($fingerprint->email('Matka@Example.cz '), $fingerprint->email('matka@example.cz'));
        self::assertSame('mala anna', Fingerprint::normalize(' Malá   Anna '));
    }
}
