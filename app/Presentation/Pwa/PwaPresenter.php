<?php

declare(strict_types=1);

namespace App\Presentation\Pwa;

use App\Presentation\BasePresenter;
use Nette\Application\Responses\TextResponse;
use Nette\Utils\Json;

/**
 * Manifest, service worker and offline page of the leaders' PWA (spec ch. 15). Served from routes so the
 * icon URLs and the worker carry the release hash and a new release replaces the old caches.
 */
final class PwaPresenter extends BasePresenter
{
    public const string THEME_COLOR = '#1f5f8b';

    public function __construct(
        private readonly string $releaseHash,
        private readonly string $manifestPath,
        private readonly string $assetsDir,
    ) {
        parent::__construct();
    }

    public function actionManifest(): void
    {
        $icon = fn (string $file): string => '/pwa/'.$file.'?v='.rawurlencode($this->releaseHash);
        $this->getHttpResponse()->setHeader('Cache-Control', 'public, max-age=3600');
        $this->getHttpResponse()->setContentType('application/manifest+json', 'utf-8');
        $this->sendResponse(new TextResponse(Json::encode([
            'name' => 'Zvadlo',
            'short_name' => 'Zvadlo',
            'description' => 'Přihlášky na akce oddílu: pozvánky, seznam přihlášených a propsání do skautISu.',
            'lang' => 'cs',
            'dir' => 'ltr',
            'id' => '/prehled',
            'start_url' => $this->link(':Dashboard:default'),
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f4f6f4',
            'theme_color' => self::THEME_COLOR,
            'icons' => [
                ['src' => $icon('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $icon('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $icon('icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Akce', 'url' => $this->link(':Events:Overview:default')],
            ],
        ])));
    }

    /**
     * The worker is built by Vite (frontend/sw.ts); the release hash names its caches, so every deploy
     * starts with empty ones.
     */
    public function actionServiceWorker(): void
    {
        $file = $this->builtWorker();
        if ($file === null) {
            $this->error('Service worker není sestavený, spusťte `make assets`.');
        }

        $this->getHttpResponse()->setHeader('Cache-Control', 'no-cache');
        $this->getHttpResponse()->setHeader('Service-Worker-Allowed', '/');
        $this->getHttpResponse()->setContentType('text/javascript', 'utf-8');
        $this->sendResponse(new TextResponse(str_replace('__ZVADLO_RELEASE__', $this->releaseHash, (string) file_get_contents($file))));
    }

    public function renderOffline(): void
    {
    }

    private function builtWorker(): ?string
    {
        $json = is_file($this->manifestPath) ? file_get_contents($this->manifestPath) : false;
        $manifest = $json === false ? null : json_decode($json, true);
        $file = \is_array($manifest) && \is_array($manifest['frontend/sw.ts'] ?? null) ? ($manifest['frontend/sw.ts']['file'] ?? null) : null;
        if (! \is_string($file) || ! is_file($this->assetsDir.'/'.$file)) {
            return null;
        }

        return $this->assetsDir.'/'.$file;
    }
}
