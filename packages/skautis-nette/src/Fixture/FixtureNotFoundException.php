<?php

declare(strict_types=1);

namespace Webwings\SkautisNette\Fixture;

use RuntimeException;
use Skaut\Skautis\Exception;

final class FixtureNotFoundException extends RuntimeException implements Exception
{
}
