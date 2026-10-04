<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FileShare;
use App\Enum\Feature;
use App\Repository\FileShareRepository;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public download page for a file shared by link (no login).
 */
#[Route('/s/{token<[a-f0-9]{32}>}')]
final class FileShareController extends AbstractController
{
    public function __construct(private readonly FileShareRepository $shares)
    {
    }

    #[Route('', name: 'file_share_show', methods: ['GET'])]
    public function show(string $token): Response
    {
        $response = $this->render('file/share.html.twig', ['share' => $this->find($token)]);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    #[Route('/download', name: 'file_share_download', methods: ['GET'])]
    public function download(string $token, FileStorage $storage, EntityManagerInterface $em): BinaryFileResponse
    {
        $share = $this->find($token);
        $share->countDownload();
        $em->flush();
        $file = $share->getFile();
        $response = FileController::fileResponse($storage->absolutePath($file->getStoragePath()), $file->getFilename(), $file->getMimeType(), false);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function find(string $token): FileShare
    {
        $share = $this->shares->findValid($token);
        // links stop working while the organization has files switched off
        if (null === $share || !$share->getFile()->getOrganization()->hasFeature(Feature::Files)) {
            throw $this->createNotFoundException();
        }

        return $share;
    }
}
