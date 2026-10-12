<?php

declare(strict_types=1);

namespace App\Presentation\Accessory;

/**
 * What the layout shows about the running environment (banner outside production).
 */
final readonly class EnvironmentInfo
{
    public function __construct(
        public string $appEnv,
        public bool $showBanner,
        public string $label,
        public string $color,
        public string $releaseHash,
        public bool $maintenance = false,
    ) {
    }
}
