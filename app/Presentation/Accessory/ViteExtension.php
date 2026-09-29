<?php

declare(strict_types=1);

namespace App\Presentation\Accessory;

use Latte\Extension;
use Latte\Runtime\Html;

/**
 * {vite('frontend/app.ts')} renders the <link> and <script> tags for an entry from the Vite manifest.
 */
final class ViteExtension extends Extension
{
    /** @var array<string, array{file: string, css?: list<string>}>|null */
    private ?array $manifest = null;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $publicPath = '/assets/',
    ) {
    }

    public function getFunctions(): array
    {
        return ['vite' => $this->render(...)];
    }

    private function render(string $entry): Html
    {
        $manifest = $this->loadManifest();
        if (! isset($manifest[$entry])) {
            return new Html('<!-- Vite: entry '.htmlspecialchars($entry).' not built, run `make assets` -->');
        }

        $tags = [];
        foreach ($manifest[$entry]['css'] ?? [] as $css) {
            $tags[] = '<link rel="stylesheet" href="'.htmlspecialchars($this->publicPath.$css).'">';
        }
        $tags[] = '<script type="module" src="'.htmlspecialchars($this->publicPath.$manifest[$entry]['file']).'"></script>';

        return new Html(implode("\n", $tags));
    }

    /** @return array<string, array{file: string, css?: list<string>}> */
    private function loadManifest(): array
    {
        if ($this->manifest === null) {
            $json = is_file($this->manifestPath) ? file_get_contents($this->manifestPath) : false;
            $decoded = $json === false ? null : json_decode($json, true);

            /** @var array<string, array{file: string, css?: list<string>}> $manifest */
            $manifest = \is_array($decoded) ? $decoded : [];
            $this->manifest = $manifest;
        }

        return $this->manifest;
    }
}
