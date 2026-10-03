<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ForumBoard;
use App\Entity\ForumPost;
use App\Entity\ForumTopic;
use App\Entity\ForumTopicRead;
use App\Entity\ForumUpload;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ForumBoardFormType;
use App\Form\ForumPostFormType;
use App\Form\ForumTopicFormType;
use App\Notification\ActivityNotifier;
use App\Repository\ForumBoardRepository;
use App\Repository\ForumTopicRepository;
use App\Repository\OrganizationRepository;
use App\Repository\PollRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\ForumVoter;
use App\Service\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Forum: areas (hierarchy per organization) → topics → posts in Markdown with images and attachments.
 */
#[Route('/forum')]
final class ForumController extends AbstractController
{
    private const int MAX_IMAGE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ForumBoardRepository $boards,
        private readonly ForumTopicRepository $topics,
        private readonly ProjectRepository $projects,
        private readonly FileStorage $storage,
    ) {
    }

    #[Route('', name: 'forum_index')]
    public function index(#[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        $recent = $this->topics->recentFor($user);

        return $this->render('forum/index.html.twig', $this->treeContext($user, null) + [
            'organizations' => $organizations->findForUser($user),
            'recent' => $recent,
            'unread_ids' => $this->topics->unreadIds($user, $recent),
        ]);
    }

    #[Route('/read', name: 'forum_mark_read', methods: ['POST'])]
    #[IsCsrfTokenValid('forum')]
    public function markAllRead(#[CurrentUser] User $user): Response
    {
        $unread = $this->topics->recentFor($user, 1000);
        foreach ($this->topics->findBy(['id' => $this->topics->unreadIds($user, $unread)]) as $topic) {
            $this->markRead($user, $topic);
        }
        $this->em->flush();
        $this->addFlash('success', 'forum.marked_read');

        return $this->redirectToRoute('forum_index');
    }

    #[Route('/board/new', name: 'forum_board_new')]
    public function newBoard(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        $parent = $this->boards->find($request->query->getInt('parent'));
        if (null !== $parent) {
            $this->denyAccessUnlessGranted(ForumVoter::VIEW, $parent);
        }
        $userOrganizations = $organizations->findForUser($user);
        $preselected = $organizations->find($request->query->getInt('organization'));
        $organization = $parent?->getOrganization()
            ?? (\in_array($preselected, $userOrganizations, true) ? $preselected : ($userOrganizations[0] ?? null));
        if (null === $organization) {
            return $this->redirectToRoute('forum_index');
        }
        $board = new ForumBoard($organization, $user, $parent);
        if (null === $parent) {
            $project = $this->projects->find($request->query->getInt('project'));
            if (null !== $project && null !== $project->getOrganization() && $this->isGranted('PROJECT_VIEW', $project)) {
                $board->setProject($project)->setOrganization($project->getOrganization());
            }
        }

        $form = $this->createForm(ForumBoardFormType::class, $board, [
            'root' => null === $parent,
            'organizations' => $userOrganizations,
            'projects' => $this->projectChoices($user, $parent?->getOrganization()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($board);
            $this->em->flush();
            $this->addFlash('success', 'forum.board_created');

            return $this->redirectToRoute('forum_board_show', ['id' => $board->getId()]);
        }

        return $this->render('forum/board_form.html.twig', $this->treeContext($user, $parent) + [
            'form' => $form,
            'board' => null,
            'parent' => $parent,
        ]);
    }

    #[Route('/board/{id<\d+>}', name: 'forum_board_show')]
    #[IsGranted(ForumVoter::VIEW, 'board')]
    public function showBoard(ForumBoard $board, #[CurrentUser] User $user): Response
    {
        $topics = $this->topics->inBoard($board);

        return $this->render('forum/board.html.twig', $this->treeContext($user, $board) + [
            'board' => $board,
            'topics' => $topics,
            'unread_ids' => $this->topics->unreadIds($user, $topics),
        ]);
    }

    #[Route('/board/{id<\d+>}/edit', name: 'forum_board_edit')]
    #[IsGranted(ForumVoter::MANAGE, 'board')]
    public function editBoard(Request $request, ForumBoard $board, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(ForumBoardFormType::class, $board, [
            'projects' => $this->projectChoices($user, $board->getOrganization()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('forum_board_show', ['id' => $board->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($board);
        }

        return $this->render('forum/board_form.html.twig', $this->treeContext($user, $board) + [
            'form' => $form,
            'board' => $board,
            'parent' => $board->getParent(),
        ]);
    }

    #[Route('/board/{id<\d+>}/delete', name: 'forum_board_delete', methods: ['POST'])]
    #[IsGranted(ForumVoter::MANAGE, 'board')]
    #[IsCsrfTokenValid(new Expression('"delete-board-" ~ args["board"].getId()'))]
    public function deleteBoard(ForumBoard $board): Response
    {
        $parent = $board->getParent();
        // Uploaded files of the whole subtree leave the storage; rows go via ON DELETE CASCADE
        foreach ($this->em->getRepository(ForumUpload::class)->findBy(['board' => ForumBoardRepository::subtree($board)]) as $upload) {
            $this->storage->remove($upload->getStoragePath());
        }
        $this->em->remove($board);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return null === $parent
            ? $this->redirectToRoute('forum_index')
            : $this->redirectToRoute('forum_board_show', ['id' => $parent->getId()]);
    }

    #[Route('/board/{id<\d+>}/topic/new', name: 'forum_topic_new')]
    #[IsGranted(ForumVoter::VIEW, 'board')]
    public function newTopic(Request $request, ForumBoard $board, ActivityNotifier $notifier, #[CurrentUser] User $user): Response
    {
        $topic = new ForumTopic($board, $user);
        $post = new ForumPost($topic, $user);
        $form = $this->createForm(ForumTopicFormType::class, $topic, [
            'projects' => $this->projectChoices($user, $board->getOrganization()),
            'first_post' => $post,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $topic->addPost($post);
            $this->em->persist($topic);
            $this->attachUploads($form->get('post'), $post, $user);
            $this->markRead($user, $topic);
            $this->em->flush();
            $notifier->topicCreated($topic, $post, $user);
            $this->em->flush();

            return $this->redirectToRoute('forum_topic_show', ['id' => $topic->getId()]);
        }

        return $this->render('forum/topic_form.html.twig', $this->treeContext($user, $board) + [
            'form' => $form,
            'board' => $board,
            'topic' => null,
        ]);
    }

    #[Route('/topic/{id<\d+>}', name: 'forum_topic_show')]
    #[IsGranted(ForumVoter::VIEW, 'topic')]
    public function showTopic(Request $request, ForumTopic $topic, ActivityNotifier $notifier, PollRepository $polls, #[CurrentUser] User $user): Response
    {
        $post = new ForumPost($topic, $user);
        $form = $this->createForm(ForumPostFormType::class, $post, [
            'action' => $this->generateUrl('forum_topic_show', ['id' => $topic->getId()]).'#reply',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $topic->addPost($post);
            $this->em->persist($post);
            $this->attachUploads($form, $post, $user);
            $this->markRead($user, $topic);
            $this->em->flush();
            $notifier->postAdded($post, $user);
            $this->em->flush();

            return $this->redirect($this->generateUrl('forum_topic_show', ['id' => $topic->getId()]).'#post-'.$post->getId());
        }

        $this->markRead($user, $topic);
        $this->em->flush();

        return $this->render('forum/topic.html.twig', $this->treeContext($user, $topic->getBoard()) + [
            'topic' => $topic,
            'form' => $form,
            'polls' => $polls->forTopic($topic),
        ]);
    }

    #[Route('/topic/{id<\d+>}/edit', name: 'forum_topic_edit')]
    #[IsGranted(ForumVoter::MANAGE, 'topic')]
    public function editTopic(Request $request, ForumTopic $topic, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(ForumTopicFormType::class, $topic, [
            'projects' => $this->projectChoices($user, $topic->getOrganization()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('forum_topic_show', ['id' => $topic->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($topic);
        }

        return $this->render('forum/topic_form.html.twig', $this->treeContext($user, $topic->getBoard()) + [
            'form' => $form,
            'board' => $topic->getBoard(),
            'topic' => $topic,
        ]);
    }

    #[Route('/topic/{id<\d+>}/delete', name: 'forum_topic_delete', methods: ['POST'])]
    #[IsGranted(ForumVoter::MANAGE, 'topic')]
    #[IsCsrfTokenValid(new Expression('"delete-topic-" ~ args["topic"].getId()'))]
    public function deleteTopic(ForumTopic $topic): Response
    {
        $board = $topic->getBoard();
        foreach ($topic->getPosts() as $post) {
            foreach ($post->getAttachments() as $upload) {
                $this->storage->remove($upload->getStoragePath());
            }
        }
        $this->em->remove($topic);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('forum_board_show', ['id' => $board->getId()]);
    }

    #[Route('/post/{id<\d+>}/edit', name: 'forum_post_edit')]
    #[IsGranted(ForumVoter::EDIT_POST, 'post')]
    public function editPost(Request $request, ForumPost $post, #[CurrentUser] User $user): Response
    {
        $topic = $post->getTopic();
        $form = $this->createForm(ForumPostFormType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post->markEdited();
            $this->attachUploads($form, $post, $user);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirect($this->generateUrl('forum_topic_show', ['id' => $topic->getId()]).'#post-'.$post->getId());
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($post);
        }

        return $this->render('forum/post_form.html.twig', $this->treeContext($user, $topic->getBoard()) + [
            'form' => $form,
            'post' => $post,
            'topic' => $topic,
        ]);
    }

    #[Route('/post/{id<\d+>}/delete', name: 'forum_post_delete', methods: ['POST'])]
    #[IsGranted(ForumVoter::MANAGE, 'post')]
    #[IsCsrfTokenValid(new Expression('"delete-post-" ~ args["post"].getId()'))]
    public function deletePost(ForumPost $post): Response
    {
        $topic = $post->getTopic();
        if ($post->isFirst()) {
            // The first post carries the topic: delete the topic instead
            throw $this->createAccessDeniedException();
        }
        foreach ($post->getAttachments() as $upload) {
            $this->storage->remove($upload->getStoragePath());
        }
        $topic->removePost($post);
        $this->em->remove($post);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('forum_topic_show', ['id' => $topic->getId()]);
    }

    #[Route('/upload/{id<\d+>}/delete', name: 'forum_upload_delete', methods: ['POST'])]
    #[IsGranted(ForumVoter::MANAGE, 'upload')]
    #[IsCsrfTokenValid(new Expression('"delete-upload-" ~ args["upload"].getId()'))]
    public function deleteUpload(ForumUpload $upload): Response
    {
        $post = $upload->getPost();
        $this->storage->remove($upload->getStoragePath());
        $this->em->remove($upload);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return null === $post
            ? $this->redirectToRoute('forum_board_show', ['id' => $upload->getBoard()->getId()])
            : $this->redirectToRoute('forum_post_edit', ['id' => $post->getId()]);
    }

    /**
     * Image upload while writing (Drag&Drop, paste, button); answers with the Markdown to insert.
     */
    #[Route('/board/{id<\d+>}/image', name: 'forum_image_upload', methods: ['POST'])]
    #[IsGranted(ForumVoter::VIEW, 'board')]
    #[IsCsrfTokenValid('forum-image')]
    public function uploadImage(Request $request, ForumBoard $board, #[CurrentUser] User $user): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > self::MAX_IMAGE_SIZE
            || !\in_array($file->getMimeType(), ForumUpload::INLINE_TYPES, true)) {
            return new JsonResponse(['error' => 'forum.image_invalid'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $upload = $this->createUpload($board, $file, $user);
        $this->em->flush();
        $url = $this->generateUrl('forum_upload', ['id' => $upload->getId()]);
        $alt = str_replace(['[', ']'], '', pathinfo($upload->getFilename(), \PATHINFO_FILENAME));

        return new JsonResponse(['url' => $url, 'markdown' => \sprintf('![%s](%s)', $alt, $url)], Response::HTTP_CREATED);
    }

    #[Route('/upload/{id<\d+>}', name: 'forum_upload')]
    #[IsGranted(ForumVoter::VIEW, 'upload')]
    public function download(Request $request, ForumUpload $upload): BinaryFileResponse
    {
        $inline = $upload->isImage() && !$request->query->getBoolean('download');
        $response = new BinaryFileResponse($this->storage->absolutePath($upload->getStoragePath()));
        $response->headers->set('Content-Type', $inline ? $upload->getMimeType() : 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $upload->getFilename(),
            'datei',
        );
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * @param FormInterface<mixed> $form post form with the unmapped "files" field
     */
    private function attachUploads(FormInterface $form, ForumPost $post, User $user): void
    {
        foreach ((array) $form->get('files')->getData() as $file) {
            if ($file instanceof UploadedFile) {
                $this->createUpload($post->getTopic()->getBoard(), $file, $user)->setPost($post);
            }
        }
    }

    private function createUpload(ForumBoard $board, UploadedFile $file, User $user): ForumUpload
    {
        $upload = new ForumUpload(
            $board,
            $file->getClientOriginalName(),
            $file->getMimeType() ?? 'application/octet-stream',
            (int) $file->getSize(),
            $this->storage->storeUpload($file),
            $user,
        );
        $this->em->persist($upload);

        return $upload;
    }

    private function markRead(User $user, ForumTopic $topic): void
    {
        $read = null === $topic->getId() ? null : $this->em->getRepository(ForumTopicRead::class)->findOneBy(['user' => $user, 'topic' => $topic]);
        if (null === $read) {
            $this->em->persist(new ForumTopicRead($user, $topic));
        } else {
            $read->touch();
        }
    }

    /**
     * Area tree for the middle column: per organization the top-level areas; sub-areas are
     * only expanded along the path of the current area.
     *
     * @return array{tree: list<array{organization: Organization, boards: list<ForumBoard>}>, current: ?ForumBoard, open_ids: list<int|null>}
     */
    private function treeContext(User $user, ?ForumBoard $current): array
    {
        $tree = [];
        foreach ($this->boards->findVisibleFor($user) as $board) {
            if (null !== $board->getParent()) {
                continue;
            }
            $organization = $board->getOrganization();
            $key = spl_object_id($organization);
            $tree[$key] ??= ['organization' => $organization, 'boards' => []];
            $tree[$key]['boards'][] = $board;
        }

        return [
            'tree' => array_values($tree),
            'current' => $current,
            'open_ids' => array_map(static fn (ForumBoard $b): ?int => $b->getId(), $current?->getPath() ?? []),
        ];
    }

    /**
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
