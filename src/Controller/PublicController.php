<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\CalendarService;
use App\Entity\CalendarItem;
use App\Entity\ContactGroup;
use App\Entity\PublicRequest;
use App\Entity\PublicSettings;
use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Enum\Feature;
use App\Enum\SurveyQuestionType;
use App\Form\PublicForm\ContactType;
use App\Form\PublicForm\SignupType;
use App\Form\PublicForm\SubscribeType;
use App\Form\PublicForm\SurveyAnswerType;
use App\Participation\PublicGuard;
use App\Participation\PublicSubmissionHandler;
use App\Repository\PublicSettingsRepository;
use App\Repository\ResolutionRepository;
use App\Repository\SurveyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Public participation pages of an organization (no login): info page, contact form, surveys,
 * event signups and distribution list subscriptions. ?embed=1 hides the page frame for iframes.
 */
#[Route('/p')]
final class PublicController extends AbstractController
{
    public function __construct(
        private readonly PublicSettingsRepository $settings,
        private readonly PublicGuard $guard,
        private readonly PublicSubmissionHandler $handler,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/confirm/{token<[a-f0-9]{64}>}', name: 'public_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request, string $token): Response
    {
        $publicRequest = $this->em->getRepository(PublicRequest::class)->findOneBy(['token' => $token]);
        $settings = null === $publicRequest ? null : $this->settings->findOneBy(['organization' => $publicRequest->getOrganization()]);
        if (null === $publicRequest || null === $settings || $publicRequest->isExpired()) {
            return $this->render('public/message.html.twig', ['settings' => $settings, 'title' => 'public.confirm.title', 'text' => 'public.confirm.invalid'], new Response(null, Response::HTTP_NOT_FOUND));
        }
        // Mail scanners follow links: confirmation needs a button press (POST)
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('public-confirm', $request->request->getString('_token'))) {
            $done = $this->handler->confirm($publicRequest);

            return $this->render('public/message.html.twig', [
                'settings' => $settings,
                'title' => 'public.confirm.title',
                'text' => $done ? 'public.confirm.done_'.$publicRequest->getKind()->value : 'public.confirm.failed',
            ]);
        }

        return $this->render('public/confirm.html.twig', ['settings' => $settings, 'public_request' => $publicRequest]);
    }

    #[Route('/{slug<[a-z0-9-]+>}', name: 'public_home')]
    public function home(Request $request, string $slug, ResolutionRepository $resolutions, CalendarService $calendar, SurveyRepository $surveys): Response
    {
        $settings = $this->load($slug);
        $organization = $settings->getOrganization();
        $today = new \DateTimeImmutable('today');

        return $this->render('public/home.html.twig', [
            'settings' => $settings,
            'resolutions' => $settings->offers('info') && $organization->hasFeature(Feature::Resolutions) ? $resolutions->findPublic($organization) : [],
            'events' => $settings->offers('events') ? \array_slice($calendar->publicOccurrences($organization, $today, $today->modify('+6 months')), 0, 20) : [],
            'surveys' => $settings->offers('surveys') ? $surveys->findListed($organization) : [],
            'groups' => $settings->offers('subscribe') ? $this->subscribableGroups($settings) : [],
        ]);
    }

    #[Route('/{slug<[a-z0-9-]+>}/contact', name: 'public_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, string $slug): Response
    {
        $settings = $this->load($slug, 'contact');
        if (null === $settings->getContactAccount()) {
            throw new NotFoundHttpException();
        }
        $form = $this->createForm(ContactType::class, null, ['topics' => $settings->getActiveTopics()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && $this->passes($settings, $form, $request)) {
            /** @var array{name: string, email: string, institution?: ?string, topic?: ?\App\Entity\PublicTopic, subject: string, message: string, attachments?: list<\Symfony\Component\HttpFoundation\File\UploadedFile>, copy?: bool} $data */
            $data = $form->getData();
            $confirm = $this->handler->contact($settings, $data);
            $this->em->flush();

            return $this->done($request, $settings, $confirm ? 'public.sent_confirm' : 'public.contact.sent');
        }

        return $this->render('public/contact.html.twig', ['settings' => $settings, 'form' => $form], $this->status($form));
    }

    #[Route('/{slug<[a-z0-9-]+>}/survey/{token<[a-f0-9]{32}>}', name: 'public_survey', methods: ['GET', 'POST'])]
    public function survey(Request $request, string $slug, string $token): Response
    {
        $settings = $this->load($slug, 'surveys');
        $survey = $this->em->getRepository(Survey::class)->findOneBy(['token' => $token, 'organization' => $settings->getOrganization()]);
        if (null === $survey || $survey->isDraft()) {
            throw new NotFoundHttpException();
        }
        if ($survey->isClosed()) {
            return $this->render('public/message.html.twig', ['settings' => $settings, 'title' => $survey->getTitle(), 'title_raw' => true, 'text' => 'public.survey.closed']);
        }
        $form = $this->createForm(SurveyAnswerType::class, null, ['survey' => $survey]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && $this->passes($settings, $form, $request)) {
            $answers = [];
            foreach ($survey->getQuestions() as $question) {
                $value = $form->get('q'.$question->getId())->getData();
                if (null === $value || '' === $value || [] === $value) {
                    continue;
                }
                $answers[(string) $question->getId()] = match ($question->getType()) {
                    SurveyQuestionType::Multiple => array_values(array_map(strval(...), (array) $value)),
                    SurveyQuestionType::Scale => (int) $value,
                    default => trim((string) $value),
                };
            }
            $anonymous = $survey->isAnonymous();
            $this->em->persist(new SurveyResponse($survey, $answers,
                $anonymous ? null : (string) $form->get('name')->getData(),
                $anonymous ? null : ($form->get('email')->getData() ?: null)));
            $this->em->flush();

            return $this->done($request, $settings, 'public.survey.thanks');
        }

        return $this->render('public/survey.html.twig', ['settings' => $settings, 'survey' => $survey, 'form' => $form], $this->status($form));
    }

    #[Route('/{slug<[a-z0-9-]+>}/event/{id<\d+>}', name: 'public_event', methods: ['GET', 'POST'])]
    public function event(Request $request, string $slug, int $id): Response
    {
        $settings = $this->load($slug, 'events');
        $item = $this->em->find(CalendarItem::class, $id);
        if (null === $item || !$item->isPublic() || $item->isTask() || $item->getOrganization() !== $settings->getOrganization()) {
            throw new NotFoundHttpException();
        }
        $form = null;
        if ($item->isSignupOpen()) {
            $form = $this->createForm(SignupType::class);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid() && $this->passes($settings, $form, $request)) {
                /** @var array{name: string, email: string, persons: int} $data */
                $data = $form->getData();
                $outcome = $this->handler->signup($settings, $item, $data);
                $this->em->flush();
                if (PublicSubmissionHandler::SIGNUP_FULL !== $outcome) {
                    return $this->done($request, $settings, PublicSubmissionHandler::SIGNUP_CONFIRM === $outcome ? 'public.sent_confirm' : 'public.event.signed_up');
                }
                $form->addError(new FormError($this->translator->trans('public.event.full')));
            }
        }

        return $this->render('public/event.html.twig', ['settings' => $settings, 'item' => $item, 'form' => $form], null !== $form ? $this->status($form) : null);
    }

    #[Route('/{slug<[a-z0-9-]+>}/subscribe', name: 'public_subscribe', methods: ['GET', 'POST'])]
    public function subscribe(Request $request, string $slug): Response
    {
        $settings = $this->load($slug, 'subscribe');
        $groups = $this->subscribableGroups($settings);
        if ([] === $groups) {
            throw new NotFoundHttpException();
        }
        $form = $this->createForm(SubscribeType::class, null, ['groups' => $groups]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && $this->passes($settings, $form, $request)) {
            /** @var array{name: string, email: string, groups: iterable<ContactGroup>} $data */
            $data = $form->getData();
            $this->handler->subscribe($settings, $data);
            $this->em->flush();

            return $this->done($request, $settings, 'public.sent_confirm');
        }

        return $this->render('public/subscribe.html.twig', ['settings' => $settings, 'form' => $form], $this->status($form));
    }

    #[Route('/{slug<[a-z0-9-]+>}/unsubscribe/{group<\d+>}', name: 'public_unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribe(Request $request, string $slug, int $group, UriSigner $signer): Response
    {
        $settings = $this->settings->findOneBy(['slug' => $slug]) ?? throw new NotFoundHttpException();
        $contactGroup = $this->em->find(ContactGroup::class, $group);
        if (null === $contactGroup || $contactGroup->getOrganization() !== $settings->getOrganization() || !$signer->checkRequest($request)) {
            throw new NotFoundHttpException();
        }
        $email = $request->query->getString('email');
        if ($request->isMethod('POST') && $this->isCsrfTokenValid('public-unsubscribe', $request->request->getString('_token'))) {
            $this->handler->unsubscribe($contactGroup, $email);

            return $this->render('public/message.html.twig', ['settings' => $settings, 'title' => 'public.unsubscribe.title', 'text' => 'public.unsubscribe.done']);
        }

        return $this->render('public/unsubscribe.html.twig', ['settings' => $settings, 'group' => $contactGroup, 'email' => $email]);
    }

    #[Route('/{slug<[a-z0-9-]+>}/privacy', name: 'public_privacy')]
    public function privacy(string $slug): Response
    {
        return $this->render('public/privacy.html.twig', ['settings' => $this->load($slug)]);
    }

    /** Settings of an active public page (organization using it), optionally requiring a part ({@see PublicSettings::offers()}) */
    private function load(string $slug, ?string $part = null): PublicSettings
    {
        $settings = $this->settings->findActiveBySlug($slug);
        if (null === $settings || !$settings->getOrganization()->hasFeature(Feature::PublicPage) || (null !== $part && !$settings->offers($part))) {
            throw new NotFoundHttpException();
        }

        return $settings;
    }

    /**
     * @return list<ContactGroup>
     */
    private function subscribableGroups(PublicSettings $settings): array
    {
        /* @var list<ContactGroup> */
        return $this->em->getRepository(ContactGroup::class)->findBy(['organization' => $settings->getOrganization(), 'publicSubscribe' => true], ['name' => 'ASC']);
    }

    /**
     * Spam check after validation; adds the error to the form.
     *
     * @param FormInterface<mixed> $form
     */
    private function passes(PublicSettings $settings, FormInterface $form, Request $request): bool
    {
        $error = $this->guard->check($settings, $form, $request->getClientIp());
        if (null === $error) {
            return true;
        }
        $this->em->flush();
        $form->addError(new FormError($this->translator->trans($error)));

        return false;
    }

    private function done(Request $request, PublicSettings $settings, string $text): Response
    {
        return $this->render('public/message.html.twig', ['settings' => $settings, 'title' => 'public.thanks', 'text' => $text, 'embed' => $request->query->getBoolean('embed')]);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function status(FormInterface $form): ?Response
    {
        return $form->isSubmitted() && !$form->isValid() ? new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY) : null;
    }
}
