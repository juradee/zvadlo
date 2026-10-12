<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Registration;

use App\Model\Registration\SkautisParticipantLogin;
use Codeception\Test\Unit;

final class SkautisParticipantLoginTest extends Unit
{
    public function testBacklinkRoundTripAndRejectionOfAnythingElse(): void
    {
        $backlink = SkautisParticipantLogin::backlink(1003, 'okresni-zavod', 'AbC123_-xyz9');
        self::assertSame('p:1003:okresni-zavod:AbC123_-xyz9', $backlink);
        self::assertSame(['unitId' => 1003, 'slug' => 'okresni-zavod', 't' => 'AbC123_-xyz9'], SkautisParticipantLogin::parseBacklink($backlink));

        foreach ([null, '', 'p:1003', 'p:1003:slug', 'p:abc:slug:AbC123_-xyz9', 'p:1003:Slug:AbC123_-xyz9', 'p:1003:slug:short', 'https://evil.example/?p:1:a:AbC123_-xyz9', 'p:1003:slug:AbC123_-xyz9/../x'] as $invalid) {
            self::assertNull(SkautisParticipantLogin::parseBacklink($invalid), var_export($invalid, true));
        }
    }
}
