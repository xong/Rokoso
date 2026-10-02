<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Folder;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\StoredFile;
use App\Entity\User;
use App\Form\FileFormType;
use App\Form\FolderFormType;
use App\Form\UploadFormType;
use App\Repository\CommentRepository;
use App\Repository\FolderRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Repository\StoredFileRepository;
use App\Security\Voter\FolderVoter;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Shared file area: folder hierarchy per organization, upload, download, comments.
 */
#[Route('/files')]
final class FileController extends AbstractController
{
    /** Images that may be shown inline (no SVG: could contain scripts) */
    private const array INLINE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FolderRepository $folders,
        private readonly StoredFileRepository $files,
        private readonly ProjectRepository $projects,
        private readonly FileStorage $storage,
    ) {
    }

    #[Route('', name: 'file_index')]
    public function index(#[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        return $this->render('file/index.html.twig', $this->treeContext($user, null) + [
            'organizations' => $organizations->findForUser($user),
        ]);
    }

    #[Route('/folder/new', name: 'folder_new')]
    public function newFolder(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        $parent = $this->folders->find($request->query->getInt('parent'));
        if (null !== $parent) {
            $this->denyAccessUnlessGranted(FolderVoter::EDIT, $parent);
        }
        $userOrganizations = $organizations->findForUser($user);
        $preselected = $organizations->find($request->query->getInt('organization'));
        $organization = $parent?->getOrganization()
            ?? (\in_array($preselected, $userOrganizations, true) ? $preselected : ($userOrganizations[0] ?? null));
        if (null === $organization) {
            return $this->redirectToRoute('file_index');
        }
        $folder = new Folder($organization, $user, $parent);
        if (null === $parent) {
            $project = $this->projects->find($request->query->getInt('project'));
            if (null !== $project && null !== $project->getOrganization() && $this->isGranted('PROJECT_VIEW', $project)) {
                $folder->setProject($project)->setOrganization($project->getOrganization());
            }
        }

        $form = $this->createForm(FolderFormType::class, $folder, [
            'root' => null === $parent,
            'organizations' => $userOrganizations,
            'projects' => $this->projectChoices($user, $parent?->getOrganization()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($folder);
            $this->em->flush();
            $this->addFlash('success', 'folder.created');

            return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
        }

        return $this->render('file/folder_form.html.twig', $this->treeContext($user, $parent) + [
            'form' => $form,
            'folder' => null,
            'parent' => $parent,
        ]);
    }

    #[Route('/folder/{id<\d+>}', name: 'folder_show')]
    #[IsGranted(FolderVoter::VIEW, 'folder')]
    public function showFolder(Folder $folder, #[CurrentUser] User $user): Response
    {
        return $this->renderFolder($folder, $user, $this->createUploadForm($folder));
    }

    #[Route('/folder/{id<\d+>}/edit', name: 'folder_edit')]
    #[IsGranted(FolderVoter::EDIT, 'folder')]
    public function editFolder(Request $request, Folder $folder, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(FolderFormType::class, $folder, [
            'projects' => $this->projectChoices($user, $folder->getOrganization()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($folder);
        }

        return $this->render('file/folder_form.html.twig', $this->treeContext($user, $folder) + [
            'form' => $form,
            'folder' => $folder,
            'parent' => $folder->getParent(),
        ]);
    }

    #[Route('/folder/{id<\d+>}/delete', name: 'folder_delete', methods: ['POST'])]
    #[IsGranted(FolderVoter::DELETE, 'folder')]
    #[IsCsrfTokenValid(new Expression('"delete-folder-" ~ args["folder"].getId()'))]
    public function deleteFolder(Folder $folder): Response
    {
        $parent = $folder->getParent();
        // Files of the whole subtree leave the storage; rows go via ON DELETE CASCADE
        foreach ($this->files->inFolders(FolderRepository::subtree($folder)) as $file) {
            $this->storage->remove($file->getStoragePath());
        }
        $this->em->remove($folder);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return null === $parent
            ? $this->redirectToRoute('file_index')
            : $this->redirectToRoute('folder_show', ['id' => $parent->getId()]);
    }

    #[Route('/folder/{id<\d+>}/upload', name: 'folder_upload', methods: ['POST'])]
    #[IsGranted(FolderVoter::EDIT, 'folder')]
    public function upload(Request $request, Folder $folder, #[CurrentUser] User $user): Response
    {
        $form = $this->createUploadForm($folder);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderFolder($folder, $user, $form);
        }

        /** @var list<UploadedFile> $uploads */
        $uploads = $form->get('files')->getData();
        foreach ($uploads as $upload) {
            $file = new StoredFile(
                $folder,
                $upload->getClientOriginalName(),
                $upload->getMimeType() ?? 'application/octet-stream',
                (int) $upload->getSize(),
                $this->storage->storeUpload($upload),
                $user,
            );
            $this->em->persist($file);
        }
        $this->em->flush();
        $this->addFlash('success', 'file.uploaded');

        return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
    }

    #[Route('/{id<\d+>}', name: 'file_show')]
    #[IsGranted(FolderVoter::VIEW, 'file')]
    public function show(Request $request, StoredFile $file, #[CurrentUser] User $user, CommentRepository $comments): Response
    {
        $form = $this->createForm(FileFormType::class, $file, [
            'projects' => $this->projectChoices($user, $file->getOrganization()),
            'folders' => array_values(array_filter(
                $this->folders->findVisibleFor($user),
                static fn (Folder $f): bool => $f->getOrganization() === $file->getOrganization(),
            )),
            'action' => $this->generateUrl('file_show', ['id' => $file->getId()]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $this->denyAccessUnlessGranted(FolderVoter::EDIT, $file);
            if ($form->isValid()) {
                $this->em->flush();
                $this->addFlash('success', 'flash.saved');

                return $this->redirectToRoute('file_show', ['id' => $file->getId()]);
            }
            $this->em->refresh($file);
        }

        return $this->render('file/show.html.twig', $this->treeContext($user, $file->getFolder()) + [
            'file' => $file,
            'form' => $form,
            'comments' => $comments->forTarget($file),
        ]);
    }

    #[Route('/{id<\d+>}/download', name: 'file_download')]
    #[IsGranted(FolderVoter::VIEW, 'file')]
    public function download(Request $request, StoredFile $file): BinaryFileResponse
    {
        $inline = $request->query->getBoolean('inline') && \in_array($file->getMimeType(), self::INLINE_TYPES, true);
        $response = new BinaryFileResponse($this->storage->absolutePath($file->getStoragePath()));
        $response->headers->set('Content-Type', $inline ? $file->getMimeType() : 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getFilename(),
            'datei',
        );

        return $response;
    }

    #[Route('/{id<\d+>}/delete', name: 'file_delete', methods: ['POST'])]
    #[IsGranted(FolderVoter::DELETE, 'file')]
    #[IsCsrfTokenValid(new Expression('"delete-file-" ~ args["file"].getId()'))]
    public function delete(StoredFile $file): Response
    {
        $folder = $file->getFolder();
        $this->storage->remove($file->getStoragePath());
        $this->em->remove($file);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
    }

    #[Route('/{id<\d+>}/comment', name: 'file_comment', methods: ['POST'])]
    #[IsGranted(FolderVoter::VIEW, 'file')]
    #[IsCsrfTokenValid('file-comment')]
    public function comment(Request $request, StoredFile $file, #[CurrentUser] User $user): Response
    {
        $comment = Comment::onFile($file, $user)->setBody($request->getPayload()->getString('body'));
        if ('' !== $comment->getBody()) {
            $this->em->persist($comment);
            $this->em->flush();
        }

        return $this->redirect($this->generateUrl('file_show', ['id' => $file->getId()]).'#comments');
    }

    #[Route('/{id<\d+>}/comment/{comment<\d+>}/delete', name: 'file_comment_delete', methods: ['POST'])]
    #[IsGranted(FolderVoter::VIEW, 'file')]
    #[IsCsrfTokenValid('file-comment')]
    public function deleteComment(StoredFile $file, Comment $comment, #[CurrentUser] User $user): Response
    {
        if ($comment->getFile() !== $file || $comment->getAuthor() !== $user) {
            throw $this->createAccessDeniedException();
        }
        $this->em->remove($comment);
        $this->em->flush();

        return $this->redirect($this->generateUrl('file_show', ['id' => $file->getId()]).'#comments');
    }

    /**
     * @param FormInterface<mixed> $uploadForm
     */
    private function renderFolder(Folder $folder, User $user, FormInterface $uploadForm): Response
    {
        return $this->render('file/folder.html.twig', $this->treeContext($user, $folder) + [
            'folder' => $folder,
            'upload_form' => $uploadForm,
        ]);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createUploadForm(Folder $folder): FormInterface
    {
        return $this->createForm(UploadFormType::class, null, [
            'action' => $this->generateUrl('folder_upload', ['id' => $folder->getId()]),
        ]);
    }

    /**
     * Folder tree for the middle column: per organization the top-level folders; the subfolders
     * are only expanded along the path of the current folder.
     *
     * @return array{tree: list<array{organization: Organization, folders: list<Folder>}>, current: ?Folder, open_ids: list<int|null>}
     */
    private function treeContext(User $user, ?Folder $current): array
    {
        $tree = [];
        foreach ($this->folders->findVisibleFor($user) as $folder) {
            if (null !== $folder->getParent()) {
                continue;
            }
            $organization = $folder->getOrganization();
            $key = spl_object_id($organization);
            $tree[$key] ??= ['organization' => $organization, 'folders' => []];
            $tree[$key]['folders'][] = $folder;
        }

        return [
            'tree' => array_values($tree),
            'current' => $current,
            'open_ids' => array_map(static fn (Folder $f): ?int => $f->getId(), $current?->getPath() ?? []),
        ];
    }

    /**
     * Projects that folders and files can be assigned to: those of the given organization or of all the user's organizations.
     *
     * @return list<Project>
     */
    private function projectChoices(User $user, ?Organization $organization): array
    {
        return array_values(array_filter(
            $this->projects->findVisibleFor($user),
            static fn (Project $p): bool => null !== $p->getOrganization() && (null === $organization || $p->getOrganization() === $organization),
        ));
    }
}
