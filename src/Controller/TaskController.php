<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CalendarItem;
use App\Entity\User;
use App\Enum\TaskStatus;
use App\Repository\CalendarItemRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\CalendarItemVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Task list and board. Tasks are calendar items of type task (see CalendarItem).
 */
#[Route('/tasks')]
final class TaskController extends AbstractController
{
    #[Route('', name: 'task_index')]
    public function index(Request $request, #[CurrentUser] User $user, CalendarItemRepository $items, ProjectRepository $projects): Response
    {
        $mine = 'all' !== $request->query->getString('scope');
        $board = 'board' === $request->query->getString('view');
        $withDone = $board || $request->query->getBoolean('done');
        $visibleProjects = $projects->findVisibleFor($user);
        $projectId = $request->query->getInt('project') ?: null;
        if (null !== $projectId && [] === array_filter($visibleProjects, static fn ($p): bool => $p->getId() === $projectId)) {
            $projectId = null;
        }

        $tasks = $items->findTasks($user, $mine, $projectId, $withDone);
        $columns = [];
        foreach (TaskStatus::cases() as $status) {
            $columns[$status->value] = ['status' => $status, 'tasks' => []];
        }
        foreach ($tasks as $task) {
            $columns[$task->getStatus()->value]['tasks'][] = $task;
        }

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'columns' => $columns,
            'mine' => $mine,
            'board' => $board,
            'with_done' => $withDone,
            'projects' => $visibleProjects,
            'project_filter' => $projectId,
        ]);
    }

    #[Route('/{id<\d+>}/status', name: 'task_status', methods: ['POST'])]
    #[IsGranted(CalendarItemVoter::EDIT, 'item')]
    #[IsCsrfTokenValid('task-status')]
    public function status(Request $request, CalendarItem $item, EntityManagerInterface $em): Response
    {
        $status = TaskStatus::tryFrom($request->getPayload()->getString('status'));
        if (!$item->isTask() || null === $status) {
            throw $this->createNotFoundException();
        }
        $item->setStatus($status);
        $em->flush();
        $this->addFlash('success', 'task.moved');

        $return = $request->getPayload()->getString('return');

        return $this->redirect(str_starts_with($return, '/') && !str_starts_with($return, '//') ? $return : $this->generateUrl('task_index'));
    }
}
