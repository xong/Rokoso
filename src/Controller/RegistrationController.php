<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\EmailOnlyType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\InvitationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $hasher, InvitationManager $invitations): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('mail_inbox');
        }

        $session = $request->getSession();
        $invitation = $invitations->findValid($session->get(InvitationController::SESSION_KEY));
        $user = new User();
        if (null !== $invitation) {
            $user->setEmail($invitation->getEmail());
        }
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->persist($user);

            if (null !== $invitation && $invitation->getEmail() === $user->getEmail()) {
                // Der Einladungslink kam an diese Adresse: sie gilt damit als bestätigt.
                $user->setVerified(true);
                $invitations->join($invitation, $user);
                $session->remove(InvitationController::SESSION_KEY);
                $this->removeTargetPath($session, 'main');
                $this->addFlash('success', 'invitation.registered');

                return $this->redirectToRoute('app_login');
            }

            $this->em->flush();
            $this->emailVerifier->sendRegistrationConfirmation($user);

            return $this->redirectToRoute('app_register_check', ['email' => $user->getEmail()]);
        }

        return $this->render('registration/register.html.twig', ['form' => $form]);
    }

    #[Route('/register/check-email', name: 'app_register_check')]
    public function checkEmail(Request $request): Response
    {
        return $this->render('registration/check_email.html.twig', [
            'email' => $request->query->getString('email'),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyEmail(Request $request, VerifyEmailHelperInterface $helper, UserRepository $users): Response
    {
        $user = $users->find($request->query->getInt('id'));
        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        try {
            $helper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'flash.link_invalid');

            return $this->redirectToRoute('app_verify_resend');
        }

        $user->setVerified(true);
        $this->em->flush();
        $this->addFlash('success', 'registration.verified');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/resend', name: 'app_verify_resend')]
    public function resend(Request $request, UserRepository $users): Response
    {
        $form = $this->createForm(EmailOnlyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $users->findOneByEmail($email);
            // Keine Auskunft darüber, ob die Adresse existiert.
            if (null !== $user && !$user->isVerified()) {
                $this->emailVerifier->sendRegistrationConfirmation($user);
            }

            return $this->redirectToRoute('app_register_check', ['email' => $email]);
        }

        return $this->render('registration/resend.html.twig', ['form' => $form]);
    }
}
