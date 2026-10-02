<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\EmailOnlyType;
use App\Form\ProfileFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'profile_edit')]
    public function edit(Request $request, #[CurrentUser] User $user, ImageUploader $uploader): Response
    {
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('avatarFile')->getData();
            if ($file instanceof UploadedFile) {
                $user->setAvatar($uploader->store($file, 'avatars', $user->getAvatar()));
            } elseif (true === $form->get('removeAvatar')->getData()) {
                $uploader->remove($user->getAvatar());
                $user->setAvatar(null);
            }
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('profile_edit');
        }
        if ($form->isSubmitted()) {
            // Ungültige Eingaben nicht in Navigation/Avatar übernehmen.
            $this->em->refresh($user);
        }

        return $this->render('profile/edit.html.twig', ['form' => $form, 'section' => 'profile']);
    }

    /** Mobile: section list only. */
    #[Route('/menu', name: 'profile_menu')]
    public function menu(): Response
    {
        return $this->render('profile/menu.html.twig', ['section' => 'menu']);
    }

    #[Route('/password', name: 'profile_password')]
    public function password(Request $request, #[CurrentUser] User $user, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $this->em->flush();
            $this->addFlash('success', 'profile.password_changed');

            return $this->redirectToRoute('profile_password');
        }

        return $this->render('profile/password.html.twig', ['form' => $form, 'section' => 'password']);
    }

    #[Route('/email', name: 'profile_email')]
    public function email(Request $request, #[CurrentUser] User $user, UserRepository $users, EmailVerifier $verifier, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(EmailOnlyType::class, null, ['field_label' => 'profile.new_email']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = mb_strtolower(trim((string) $form->get('email')->getData()));
            if ($email === $user->getEmail()) {
                $form->get('email')->addError(new FormError($translator->trans('profile.email_unchanged')));
            } elseif (null !== $users->findOneByEmail($email)) {
                $form->get('email')->addError(new FormError($translator->trans('user.email_taken', [], 'validators')));
            } else {
                $user->setPendingEmail($email);
                $this->em->flush();
                $verifier->sendEmailChangeConfirmation($user);
                $this->addFlash('success', 'profile.email_sent');

                return $this->redirectToRoute('profile_email');
            }
        }

        return $this->render('profile/email.html.twig', ['form' => $form, 'section' => 'email']);
    }

    #[Route('/email/confirm', name: 'profile_email_confirm')]
    public function confirmEmail(Request $request, #[CurrentUser] User $user, VerifyEmailHelperInterface $helper, UserRepository $users): Response
    {
        $pending = $user->getPendingEmail();
        if (null === $pending || $request->query->getInt('id') !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        try {
            $helper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $pending);
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'flash.link_invalid');

            return $this->redirectToRoute('profile_email');
        }

        if (null !== $users->findOneByEmail($pending)) {
            $this->addFlash('error', 'user.email_taken');
        } else {
            $user->setEmail($pending);
            $this->addFlash('success', 'profile.email_changed');
        }
        $user->setPendingEmail(null);
        $this->em->flush();

        return $this->redirectToRoute('profile_email');
    }
}
