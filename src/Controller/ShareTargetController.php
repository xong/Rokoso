<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Folder;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Repository\FolderRepository;
use App\Security\Voter\FolderVoter;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Target of the installed app's "share" menu (Web Share Target): shared files wait in an
 * incoming area until the user picks a folder; shared text or links can become an email.
 */
#[Route('/share')]
final class ShareTargetController extends AbstractController
{
    private const string SESSION_KEY = 'share_target';

    private const int MAX_FILES = 10;

    private const int MAX_SIZE = 50 * 1024 * 1024;

    public function __construct(
        private readonly FileStorage $storage,
        private readonly FolderRepository $folders,
    ) {
    }

    /**
     * Receives the share from the operating system (no CSRF token possible: nothing is
     * saved before the user confirms on the following page).
     */
    #[Route('', name: 'share_target_receive', methods: ['POST'])]
    public function receive(Request $request, SessionInterface $session): Response
    {
        $this->discardPending($session);
        $files = [];
        foreach (\array_slice($this->uploads($request->files->all()), 0, self::MAX_FILES) as $upload) {
            if (!$upload->isValid() || $upload->getSize() > self::MAX_SIZE) {
                continue;
            }
            $files[] = [
                'name' => mb_substr($upload->getClientOriginalName(), 0, 255),
                'mime' => $upload->getMimeType() ?? 'application/octet-stream',
                'size' => (int) $upload->getSize(),
                'path' => $this->storage->storeIncoming($upload),
            ];
        }
        $payload = $request->getPayload();
        $session->set(self::SESSION_KEY, [
            'title' => mb_substr(trim($payload->getString('title')), 0, 500),
            'text' => mb_substr(trim($payload->getString('text')), 0, 10000),
            'url' => mb_substr(trim($payload->getString('url')), 0, 2000),
            'files' => $files,
        ]);

        return $this->redirectToRoute('share_target_show', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('', name: 'share_target_show', methods: ['GET'])]
    public function show(SessionInterface $session, #[CurrentUser] User $user, Request $request): Response
    {
        $shared = $this->pending($session);
        $folders = $this->writableFolders($user);
        $selected = $request->query->getInt('folder');

        return $this->render('share/show.html.twig', [
            'shared' => $shared,
            'folders' => $folders,
            'selected' => $selected,
            'mail_body' => null !== $shared ? trim($shared['text']."\n".$shared['url']) : '',
        ]);
    }

    #[Route('/save', name: 'share_target_save', methods: ['POST'])]
    #[IsCsrfTokenValid('share-target')]
    public function save(Request $request, SessionInterface $session, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $shared = $this->pending($session);
        $folder = $this->folders->find((int) $request->getPayload()->getString('folder'));
        if (null === $shared || [] === $shared['files']) {
            return $this->redirectToRoute('share_target_show');
        }
        if (!$folder instanceof Folder || !$this->isGranted(FolderVoter::EDIT, $folder)) {
            $this->addFlash('error', 'share.folder_required');

            return $this->redirectToRoute('share_target_show');
        }
        foreach ($shared['files'] as $file) {
            $em->persist(new StoredFile($folder, $file['name'], $file['mime'], $file['size'], $this->storage->copy($file['path']), $user));
            $this->storage->remove($file['path']);
        }
        $em->flush();
        $session->remove(self::SESSION_KEY);
        $this->addFlash('success', 'file.uploaded');

        return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
    }

    #[Route('/discard', name: 'share_target_discard', methods: ['POST'])]
    #[IsCsrfTokenValid('share-target')]
    public function discard(SessionInterface $session): Response
    {
        $this->discardPending($session);
        $this->addFlash('success', 'share.discarded');

        return $this->redirectToRoute('mail_inbox');
    }

    /**
     * @return array{title: string, text: string, url: string, files: list<array{name: string, mime: string, size: int, path: string}>}|null
     */
    private function pending(SessionInterface $session): ?array
    {
        /** @var array{title: string, text: string, url: string, files: list<array{name: string, mime: string, size: int, path: string}>}|null $shared */
        $shared = $session->get(self::SESSION_KEY);
        if (null === $shared) {
            return null;
        }
        // files may have been purged in the meantime
        $shared['files'] = array_values(array_filter(
            $shared['files'],
            fn (array $f): bool => $this->storage->isIncoming($f['path']) && is_file($this->storage->absolutePath($f['path'])),
        ));

        return $shared;
    }

    private function discardPending(SessionInterface $session): void
    {
        foreach ($this->pending($session)['files'] ?? [] as $file) {
            $this->storage->remove($file['path']);
        }
        $session->remove(self::SESSION_KEY);
    }

    /**
     * @return list<Folder>
     */
    private function writableFolders(User $user): array
    {
        return array_values(array_filter($this->folders->findVisibleFor($user), fn (Folder $f): bool => $this->isGranted(FolderVoter::EDIT, $f)));
    }

    /**
     * @param array<mixed> $files
     *
     * @return list<UploadedFile>
     */
    private function uploads(array $files): array
    {
        $result = [];
        array_walk_recursive($files, static function (mixed $file) use (&$result): void {
            if ($file instanceof UploadedFile) {
                $result[] = $file;
            }
        });

        return $result;
    }
}
