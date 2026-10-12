<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\BugReport;

use App\Model\BugReport\ScreenshotStorage;
use Codeception\Test\Unit;

final class ScreenshotStorageTest extends Unit
{
    public function testOnlyRasterImagesAreAccepted(): void
    {
        foreach (['image/png', 'image/jpeg', 'image/gif', 'image/webp'] as $type) {
            self::assertTrue(ScreenshotStorage::accepts($type), $type);
        }
        foreach (['image/svg+xml', 'text/html', 'application/pdf', 'image/x-icon', '', null] as $type) {
            self::assertFalse(ScreenshotStorage::accepts($type), var_export($type, true));
        }
    }
}
