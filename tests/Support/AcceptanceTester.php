<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Codeception\Actor;

final class AcceptanceTester extends Actor
{
    use _generated\AcceptanceTesterActions;
}
