<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\CalendarService;
use App\Calendar\Occurrence;
use App\Entity\Comment;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectFormType;
use App\Mail\MessageFilter;
use App\Notification\ActivityNotifier;
use App\Repository\CalendarItemRepository;
use App\Repository\CommentRepository;
use App\Repository\ForumTopicRepository;
use App\Repository\MembershipRepository;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Repository\StoredFileRepository;
use App\Security\Voter\OrganizationVoter;
use App\Security\Voter\ProjectVoter;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectRepository $projects,
        private readonly ImageUploader $uploader,
    ) {
    }

    #[Route('', name: 'project_index')]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->render('project/index.html.twig', $this->listContext($request, $user));
    }

    #[Route('/new', name: 'project_new')]
    public function new(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations, MembershipRepository $memberships): Response
    {
        $project = new Project($user);
        $preselected = $organizations->find($request->query->getInt('organization'));
        if (null !== $preselected && $this->isGranted(OrganizationVoter::MANAGE, $preselected)) {
            $project->setOrganization($preselected);
        }

        $form = $this->createForm(ProjectFormType::class, $project, ['user' => $user, 'users' => $memberships->colleaguesOf($user)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeImage($form, $project);
            $this->em->persist($project);
            $this->em->flush();
            $this->addFlash('success', 'project.created');

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/form.html.twig', [
            'project' => null,
            'form' => $form,
        ] + $this->listContext($request, $user));
    }

    #[Route('/{id<\d+>}', name: 'project_show')]
    #[IsGranted(ProjectVoter::VIEW, 'project')]
    public function show(Request $request, Project $project, #[CurrentUser] User $user, MessageRepository $messages, CommentRepository $comments, StoredFileRepository $files, ForumTopicRepository $topics, CalendarService $calendar, CalendarItemRepository $items): Response
    {
        $forumTopics = $topics->forProject($project, $user);
        $now = new \DateTimeImmutable();
        $events = array_values(array_filter(
            $calendar->occurrences($user, $now->setTime(0, 0), $now->modify('+60 days'), $project->getId()),
            static fn (Occurrence $o): bool => !$o->item->isTask() && $o->end >= $now,
        ));

        return $this->render('project/show.html.twig', [
            'upcoming_events' => \array_slice($events, 0, 5),
            'open_tasks' => $items->findTasks($user, false, $project->getId(), false, 8),
            'topics' => $forumTopics,
            'unread_ids' => $topics->unreadIds($user, $forumTopics),
            'project' => $project,
            'comments' => $comments->forTarget($project),
            'files' => $files->forProject($project),
            'recent_messages' => \array_slice($messages->findForList($user, new MessageFilter('all', project: $project->getId())), 0, 5),
        ] + $this->listContext($request, $user, $project));
    }

    #[Route('/{id<\d+>}/comment', name: 'project_comment', methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project')]
    #[IsCsrfTokenValid('project-comment')]
    public function comment(Request $request, Project $project, ActivityNotifier $notifier, #[CurrentUser] User $user): Response
    {
        $comment = Comment::onProject($project, $user)->setBody($request->getPayload()->getString('body'));
        if ('' !== $comment->getBody()) {
            $this->em->persist($comment);
            $this->em->flush();
            $notifier->commentAdded($comment, $user);
            $this->em->flush();
        }

        return $this->redirect($this->generateUrl('project_show', ['id' => $project->getId()]).'#comments');
    }

    #[Route('/{id<\d+>}/comment/{comment<\d+>}/delete', name: 'project_comment_delete', methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, 'project')]
    #[IsCsrfTokenValid('project-comment')]
    public function deleteComment(Project $project, Comment $comment, #[CurrentUser] User $user): Response
    {
        if ($comment->getProject() !== $project || $comment->getAuthor() !== $user) {
            throw $this->createAccessDeniedException();
        }
        $this->em->remove($comment);
        $this->em->flush();

        return $this->redirect($this->generateUrl('project_show', ['id' => $project->getId()]).'#comments');
    }

    #[Route('/{id<\d+>}/edit', name: 'project_edit')]
    #[IsGranted(ProjectVoter::MANAGE, 'project')]
    public function edit(Request $request, Project $project, #[CurrentUser] User $user, MembershipRepository $memberships): Response
    {
        $form = $this->createForm(ProjectFormType::class, $project, ['user' => $user, 'users' => $memberships->colleaguesOf($user)]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeImage($form, $project);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($project);
        }

        return $this->render('project/form.html.twig', [
            'project' => $project,
            'form' => $form,
        ] + $this->listContext($request, $user, $project));
    }

    /**
     * Completed projects are archived: hidden from selection lists, still readable.
     */
    #[Route('/{id<\d+>}/archive', name: 'project_archive', methods: ['POST'])]
    #[IsGranted(ProjectVoter::MANAGE, 'project')]
    #[IsCsrfTokenValid('project-archive')]
    public function archive(Project $project): Response
    {
        if ($project->isArchived()) {
            $project->unarchive();
            $this->addFlash('success', 'project.unarchived_flash');
        } else {
            $project->archive();
            $this->addFlash('success', 'project.archived_flash');
        }
        $this->em->flush();

        return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
    }

    #[Route('/{id<\d+>}/delete', name: 'project_delete', methods: ['POST'])]
    #[IsGranted(ProjectVoter::MANAGE, 'project')]
    #[IsCsrfTokenValid(new Expression('"delete-project-" ~ args["project"].getId()'))]
    public function delete(Project $project): Response
    {
        $this->uploader->remove($project->getImage());
        $this->em->remove($project);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('project_index');
    }

    /**
     * @return array<string, mixed>
     */
    private function listContext(Request $request, User $user, ?Project $current = null): array
    {
        $archived = $request->query->getBoolean('archived', $current?->isArchived() ?? false);

        return [
            'projects' => $archived ? $this->projects->findArchivedFor($user) : $this->projects->findVisibleFor($user),
            'archived' => $archived,
        ];
    }

    /**
     * @param FormInterface<Project> $form
     */
    private function storeImage(FormInterface $form, Project $project): void
    {
        $file = $form->get('imageFile')->getData();
        if ($file instanceof UploadedFile) {
            $project->setImage($this->uploader->store($file, 'projects', $project->getImage()));
        } elseif (true === $form->get('removeImage')->getData()) {
            $this->uploader->remove($project->getImage());
            $project->setImage(null);
        }
    }
}
