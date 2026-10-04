<?php

declare(strict_types=1);

namespace App\Controller;

use App\Asset\PublicFiles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Installable web app: manifest, service worker and offline page.
 */
final class PwaController extends AbstractController
{
    #[Route('/manifest.webmanifest', name: 'pwa_manifest')]
    public function manifest(TranslatorInterface $translator, PublicFiles $files): JsonResponse
    {
        $response = new JsonResponse([
            'name' => $translator->trans('app.name'),
            'short_name' => $translator->trans('app.name'),
            'description' => $translator->trans('pwa.description'),
            'lang' => 'de',
            'start_url' => $this->generateUrl('home'),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f9f9f9',
            'theme_color' => '#417230',
            'icons' => [
                ['src' => $files->url('/icons/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml'],
                ['src' => $files->url('/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $files->url('/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $files->url('/icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => $translator->trans('nav.mail_inbox'), 'url' => $this->generateUrl('mail_inbox')],
                ['name' => $translator->trans('nav.calendar'), 'url' => $this->generateUrl('calendar_month')],
                ['name' => $translator->trans('nav.mail_compose'), 'url' => $this->generateUrl('mail_compose')],
            ],
            // "Share" from other apps (see ShareTargetController)
            'share_target' => [
                'action' => $this->generateUrl('share_target_receive'),
                'method' => 'POST',
                'enctype' => 'multipart/form-data',
                'params' => [
                    'title' => 'title',
                    'text' => 'text',
                    'url' => 'url',
                    'files' => [['name' => 'files', 'accept' => ['*/*']]],
                ],
            ],
        ]);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }

    /**
     * Served from the root so the worker's scope covers the whole app.
     */
    #[Route('/sw.js', name: 'pwa_service_worker')]
    public function serviceWorker(Packages $packages, PublicFiles $files): Response
    {
        // new cache name on every JS/CSS/icon change: the browser installs the worker again and drops old files
        $version = substr(hash('xxh128', $packages->getUrl('build/app.js').$packages->getUrl('styles/app.css').$files->url('/icons/icon-192.png')), 0, 10);
        $response = $this->render('pwa/sw.js.twig', ['cache_version' => $version]);
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    #[Route('/offline', name: 'pwa_offline')]
    public function offline(): Response
    {
        return $this->render('pwa/offline.html.twig');
    }
}
