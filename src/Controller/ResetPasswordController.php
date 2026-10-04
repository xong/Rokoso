<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\EmailOnlyType;
use App\Form\NewPasswordType;
use App\Repository\UserRepository;
use App\Security\SecurityLog;
use App\Service\SystemMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $em,
        private readonly SecurityLog $log,
    ) {
    }

    #[Route('', name: 'app_forgot_password')]
    public function request(Request $request, UserRepository $users, SystemMailer $mailer): Response
    {
        $form = $this->createForm(EmailOnlyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $users->findOneByEmail((string) $form->get('email')->getData());
            if (null !== $user) {
                try {
                    $token = $this->resetPasswordHelper->generateResetToken($user);
                    $mailer->send($user->getEmail(), 'email.reset.subject', 'reset_password', [
                        'name' => $user->getName(),
                        'url' => $this->generateUrl('app_reset_password', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
                    ], $user->getName());
                } catch (ResetPasswordExceptionInterface) {
                    // Zu viele Anfragen: still ignorieren, keine Auskunft über Konten.
                }
            }

            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('reset_password/check_email.html.twig');
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $hasher, ?string $token = null): Response
    {
        if (null !== $token) {
            // Token aus der URL in die Session verschieben, damit er nicht in Verlauf/Referer landet.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException();
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', 'flash.link_invalid');

            return $this->redirectToRoute('app_forgot_password');
        }
        \assert($user instanceof User);

        $form = $this->createFormBuilder()->add('plainPassword', NewPasswordType::class)->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            // Wer den Link aus der Mail nutzt, hat die Adresse bestätigt.
            $user->setVerified(true);
            $this->em->flush();
            $this->log->record('password_reset', $user);
            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'reset.done');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form]);
    }
}
