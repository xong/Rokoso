<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectFormType;
use App\Mail\MessageFilter;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
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
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $this->projects->findVisibleFor($user),
        ]);
    }

    #[Route('/new', name: 'project_new')]
    public function new(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        $project = new Project($user);
        $preselected = $organizations->find($request->query->getInt('organization'));
        if (null !== $preselected && $this->isGranted(OrganizationVoter::MANAGE, $preselected)) {
            $project->setOrganization($preselected);
        }

        $form = $this->createForm(ProjectFormType::class, $project, ['user' => $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeImage($form, $project);
            $this->em->persist($project);
            $this->em->flush();
            $this->addFlash('success', 'project.created');

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/form.html.twig', [
            'projects' => $this->projects->findVisibleFor($user),
            'project' => null,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'project_show')]
    #[IsGranted(ProjectVoter::VIEW, 'project')]
    public function show(Project $project, #[CurrentUser] User $user, MessageRepository $messages): Response
    {
        return $this->render('project/show.html.twig', [
            'projects' => $this->projects->findVisibleFor($user),
            'project' => $project,
            'recent_messages' => \array_slice($messages->findForList($user, new MessageFilter('all', project: $project->getId())), 0, 5),
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'project_edit')]
    #[IsGranted(ProjectVoter::MANAGE, 'project')]
    public function edit(Request $request, Project $project, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(ProjectFormType::class, $project, ['user' => $user]);
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
            'projects' => $this->projects->findVisibleFor($user),
            'project' => $project,
            'form' => $form,
        ]);
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
