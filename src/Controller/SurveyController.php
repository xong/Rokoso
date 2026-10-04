<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Survey;
use App\Entity\SurveyQuestion;
use App\Entity\User;
use App\Form\SurveyFormType;
use App\Form\SurveyQuestionFormType;
use App\Participation\SurveyResults;
use App\Repository\OrganizationRepository;
use App\Repository\PublicSettingsRepository;
use App\Repository\SurveyRepository;
use App\Security\Voter\SurveyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Surveys for outsiders: prepare questions, open via public link, evaluate and export.
 */
#[Route('/surveys')]
final class SurveyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SurveyRepository $surveys,
    ) {
    }

    #[Route('', name: 'survey_index')]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('survey/index.html.twig', $this->listContext($user));
    }

    #[Route('/new', name: 'survey_new')]
    public function new(Request $request, #[CurrentUser] User $user, OrganizationRepository $organizations): Response
    {
        $choices = $organizations->findForUser($user);
        if ([] === $choices) {
            $this->addFlash('error', 'survey.no_organization');

            return $this->redirectToRoute('survey_index');
        }
        $survey = new Survey($choices[0], $user);
        $form = $this->createForm(SurveyFormType::class, $survey, ['organizations' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($survey);
            $this->em->flush();
            $this->addFlash('success', 'survey.created');

            return $this->redirectToRoute('survey_show', ['id' => $survey->getId()]);
        }

        return $this->render('survey/form.html.twig', ['form' => $form, 'survey' => null] + $this->listContext($user));
    }

    #[Route('/{id<\d+>}', name: 'survey_show')]
    #[IsGranted(SurveyVoter::VIEW, 'survey')]
    public function show(Survey $survey, #[CurrentUser] User $user, SurveyResults $results, PublicSettingsRepository $settings): Response
    {
        $public = $settings->findOneBy(['organization' => $survey->getOrganization()]);

        return $this->render('survey/show.html.twig', [
            'survey' => $survey,
            'results' => $results->summarize($survey),
            'can_manage' => $this->isGranted(SurveyVoter::MANAGE, $survey),
            'public_url' => null !== $public && $public->isSurveysEnabled() && null !== $public->getSlug()
                ? $this->generateUrl('public_survey', ['slug' => $public->getSlug(), 'token' => $survey->getToken()], UrlGeneratorInterface::ABSOLUTE_URL)
                : null,
            'question_form' => $survey->isDraft() ? $this->createForm(SurveyQuestionFormType::class, new SurveyQuestion($survey), [
                'action' => $this->generateUrl('survey_question_new', ['id' => $survey->getId()]),
            ]) : null,
        ] + $this->listContext($user, $survey));
    }

    #[Route('/{id<\d+>}/edit', name: 'survey_edit')]
    #[IsGranted(SurveyVoter::MANAGE, 'survey')]
    public function edit(Request $request, Survey $survey, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(SurveyFormType::class, $survey);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('survey_show', ['id' => $survey->getId()]);
        }

        return $this->render('survey/form.html.twig', ['form' => $form, 'survey' => $survey] + $this->listContext($user, $survey));
    }

    #[Route('/{id<\d+>}/delete', name: 'survey_delete', methods: ['POST'])]
    #[IsGranted(SurveyVoter::MANAGE, 'survey')]
    #[IsCsrfTokenValid('survey-delete')]
    public function delete(Survey $survey): Response
    {
        $this->em->remove($survey);
        $this->em->flush();
        $this->addFlash('success', 'survey.deleted');

        return $this->redirectToRoute('survey_index');
    }

    #[Route('/{id<\d+>}/questions', name: 'survey_question_new', methods: ['POST'])]
    #[IsGranted(SurveyVoter::MANAGE, 'survey')]
    public function addQuestion(Request $request, Survey $survey, #[CurrentUser] User $user, SurveyResults $results): Response
    {
        if (!$survey->isDraft()) {
            throw $this->createAccessDeniedException();
        }
        $question = new SurveyQuestion($survey);
        $form = $this->createForm(SurveyQuestionFormType::class, $question);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $survey->addQuestion($question);
            $this->em->flush();

            return $this->redirectToRoute('survey_show', ['id' => $survey->getId(), '_fragment' => 'questions']);
        }

        return $this->render('survey/show.html.twig', [
            'survey' => $survey,
            'results' => $results->summarize($survey),
            'can_manage' => true,
            'public_url' => null,
            'question_form' => $form,
        ] + $this->listContext($user, $survey), new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/questions/{id<\d+>}/edit', name: 'survey_question_edit')]
    public function editQuestion(Request $request, SurveyQuestion $question, #[CurrentUser] User $user): Response
    {
        $survey = $question->getSurvey();
        $this->denyAccessUnlessGranted(SurveyVoter::MANAGE, $survey);
        if (!$survey->isDraft()) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(SurveyQuestionFormType::class, $question);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('survey_show', ['id' => $survey->getId(), '_fragment' => 'questions']);
        }

        return $this->render('survey/question_form.html.twig', ['form' => $form, 'survey' => $survey] + $this->listContext($user, $survey));
    }

    #[Route('/questions/{id<\d+>}/delete', name: 'survey_question_delete', methods: ['POST'])]
    #[IsCsrfTokenValid('survey-question')]
    public function deleteQuestion(SurveyQuestion $question): Response
    {
        $survey = $question->getSurvey();
        $this->denyAccessUnlessGranted(SurveyVoter::MANAGE, $survey);
        if (!$survey->isDraft()) {
            throw $this->createAccessDeniedException();
        }
        $survey->removeQuestion($question);
        $this->em->flush();

        return $this->redirectToRoute('survey_show', ['id' => $survey->getId(), '_fragment' => 'questions']);
    }

    #[Route('/questions/{id<\d+>}/move', name: 'survey_question_move', methods: ['POST'])]
    #[IsCsrfTokenValid('survey-question')]
    public function moveQuestion(Request $request, SurveyQuestion $question): Response
    {
        $survey = $question->getSurvey();
        $this->denyAccessUnlessGranted(SurveyVoter::MANAGE, $survey);
        if (!$survey->isDraft()) {
            throw $this->createAccessDeniedException();
        }
        $list = array_values($survey->getQuestions()->toArray());
        $index = array_search($question, $list, true);
        $target = (int) $index + ('up' === $request->request->getString('direction') ? -1 : 1);
        if (false !== $index && isset($list[$target])) {
            [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
            foreach ($list as $position => $item) {
                $item->setPosition($position);
            }
            $this->em->flush();
        }

        return $this->redirectToRoute('survey_show', ['id' => $survey->getId(), '_fragment' => 'questions']);
    }

    #[Route('/{id<\d+>}/open', name: 'survey_open', methods: ['POST'])]
    #[IsGranted(SurveyVoter::MANAGE, 'survey')]
    #[IsCsrfTokenValid('survey-state')]
    public function open(Survey $survey): Response
    {
        if ($survey->getQuestions()->isEmpty()) {
            $this->addFlash('error', 'survey.no_questions');
        } else {
            $survey->open();
            $this->em->flush();
            $this->addFlash('success', 'survey.opened');
        }

        return $this->redirectToRoute('survey_show', ['id' => $survey->getId()]);
    }

    #[Route('/{id<\d+>}/close', name: 'survey_close', methods: ['POST'])]
    #[IsGranted(SurveyVoter::MANAGE, 'survey')]
    #[IsCsrfTokenValid('survey-state')]
    public function close(Survey $survey): Response
    {
        $survey->close();
        $this->em->flush();
        $this->addFlash('success', 'survey.closed_flash');

        return $this->redirectToRoute('survey_show', ['id' => $survey->getId()]);
    }

    #[Route('/{id<\d+>}/export.csv', name: 'survey_csv')]
    #[IsGranted(SurveyVoter::VIEW, 'survey')]
    public function csv(Survey $survey, SurveyResults $results, TranslatorInterface $translator): Response
    {
        $csv = $results->csv($survey, $translator->trans('survey.csv_date'), $translator->trans('public.field.name'), $translator->trans('public.field.email'));
        $filename = (new AsciiSlugger('de'))->slug($survey->getTitle())->lower()->toString() ?: 'umfrage';
        $response = new Response($csv, Response::HTTP_OK, ['Content-Type' => 'text/csv; charset=utf-8']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename.'.csv'));

        return $response;
    }

    /**
     * @return array{surveys: list<Survey>, current: ?Survey}
     */
    private function listContext(User $user, ?Survey $current = null): array
    {
        return ['surveys' => $this->surveys->findForUser($user), 'current' => $current];
    }
}
