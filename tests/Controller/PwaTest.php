<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppTestCase;

final class PwaTest extends AppTestCase
{
    public function testManifestServiceWorkerAndOfflinePageArePublic(): void
    {
        $this->client->request('GET', '/manifest.webmanifest');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/manifest+json');
        $manifest = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($manifest);
        self::assertSame('standalone', $manifest['display']);
        self::assertIsArray($manifest['icons']);
        foreach ($manifest['icons'] as $icon) {
            $path = (string) strtok($icon['src'], '?');
            // many Apache setups alias /icons/ to their own images, so app icons there are unreachable
            self::assertStringStartsNotWith('/icons/', $path);
            self::assertFileExists(\dirname(__DIR__, 2).'/public'.$path);
        }

        $this->client->request('GET', '/sw.js');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/offline', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/offline');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('link[rel=manifest]');
    }
}
