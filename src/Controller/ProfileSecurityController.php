<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ConfirmPasswordType;
use App\Repository\SecurityEventRepository;
use App\Security\AccountDeleter;
use App\Security\SecurityLog;
use App\Service\AccountExport;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Profile sections "Sicherheit" (two-factor login, sessions, security log) and "Konto" (export, deletion).
 */
#[Route('/profile')]
final class ProfileSecurityController extends AbstractController
{
    private const string SETUP_SECRET = 'totp_setup_secret';
    private const string NEW_CODES = 'totp_backup_codes';

    public function __construct(private readonly EntityManagerInterface $em, private readonly SecurityLog $log)
    {
    }

    #[Route('/security', name: 'profile_security')]
    public function security(Request $request, #[CurrentUser] User $user, SecurityEventRepository $events): Response
    {
        $session = $request->getSession();

        return $this->render('profile/security.html.twig', [
            'section' => 'security',
            'events' => $events->findLatest($user, 30),
            // shown exactly once after enabling or renewing
            'new_codes' => $session->remove(self::NEW_CODES),
            'disable_form' => $this->createForm(ConfirmPasswordType::class, null, ['action' => $this->generateUrl('profile_2fa_disable')]),
        ]);
    }

    #[Route('/security/2fa', name: 'profile_2fa_setup')]
    public function setupTwoFactor(Request $request, #[CurrentUser] User $user, TotpAuthenticatorInterface $totp): Response
    {
        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('profile_security');
        }
        $session = $request->getSession();
        $secret = $session->get(self::SETUP_SECRET);
        if (!\is_string($secret)) {
            $secret = $totp->generateSecret();
            $session->set(self::SETUP_SECRET, $secret);
        }
        // a copy with the secret to build the QR content and check the code before saving
        $candidate = (clone $user)->setTotpSecret($secret);

        $error = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('totp-setup', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            if ($totp->checkCode($candidate, preg_replace('/\s+/', '', $request->getPayload()->getString('code')) ?? '')) {
                $user->setTotpSecret($secret);
                $session->set(self::NEW_CODES, $user->generateBackupCodes());
                $session->remove(self::SETUP_SECRET);
                $this->em->flush();
                $this->log->record('two_factor_enabled', $user);
                $this->addFlash('success', 'two_factor.enabled');

                return $this->redirectToRoute('profile_security');
            }
            $error = true;
        }

        $qr = (new SvgWriter())->write(new QrCode($totp->getQRContent($candidate)));

        return $this->render('profile/two_factor_setup.html.twig', [
            'section' => 'security',
            'qr' => $qr->getDataUri(),
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'error' => $error,
        ], new Response(status: $error ? 422 : 200));
    }

    #[Route('/security/2fa/codes', name: 'profile_2fa_codes', methods: ['POST'])]
    #[IsCsrfTokenValid('totp-codes')]
    public function renewCodes(Request $request, #[CurrentUser] User $user): Response
    {
        if ($user->isTotpAuthenticationEnabled()) {
            $request->getSession()->set(self::NEW_CODES, $user->generateBackupCodes());
            $this->em->flush();
            $this->log->record('backup_codes_renewed', $user);
        }

        return $this->redirectToRoute('profile_security');
    }

    #[Route('/security/2fa/disable', name: 'profile_2fa_disable', methods: ['POST'])]
    public function disableTwoFactor(Request $request, #[CurrentUser] User $user, SecurityEventRepository $events): Response
    {
        $form = $this->createForm(ConfirmPasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setTotpSecret(null);
            $this->em->flush();
            $this->log->record('two_factor_disabled', $user);
            $this->addFlash('success', 'two_factor.disabled');

            return $this->redirectToRoute('profile_security');
        }

        return $this->render('profile/security.html.twig', [
            'section' => 'security',
            'events' => $events->findLatest($user, 30),
            'new_codes' => null,
            'disable_form' => $form,
        ], new Response(status: 422));
    }

    #[Route('/security/logout-everywhere', name: 'profile_logout_everywhere', methods: ['POST'])]
    #[IsCsrfTokenValid('logout-everywhere')]
    public function logoutEverywhere(#[CurrentUser] User $user, Security $security): Response
    {
        $user->renewSessionStamp();
        $this->em->flush();
        $this->log->record('logout_everywhere', $user);
        $security->logout(false);

        return $this->redirectToRoute('app_login');
    }

    #[Route('/account', name: 'profile_account')]
    public function account(#[CurrentUser] User $user, AccountDeleter $deleter): Response
    {
        return $this->render('profile/account.html.twig', [
            'section' => 'account',
            'form' => $this->createForm(ConfirmPasswordType::class, null, ['action' => $this->generateUrl('profile_delete')]),
            'blockers' => $deleter->blockers($user),
        ]);
    }

    #[Route('/account/export', name: 'profile_export')]
    public function export(#[CurrentUser] User $user, AccountExport $export): Response
    {
        $response = new JsonResponse($export->export($user));
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'coop-daten-'.date('Y-m-d').'.json'));
        $this->log->record('data_exported', $user);

        return $response;
    }

    #[Route('/account/delete', name: 'profile_delete', methods: ['POST'])]
    public function delete(Request $request, #[CurrentUser] User $user, AccountDeleter $deleter, Security $security): Response
    {
        $form = $this->createForm(ConfirmPasswordType::class);
        $form->handleRequest($request);
        $blockers = $deleter->blockers($user);
        if ($form->isSubmitted() && $form->isValid() && [] === $blockers) {
            $security->logout(false);
            $deleter->delete($user, $user);
            $this->addFlash('success', 'account.deleted');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('profile/account.html.twig', [
            'section' => 'account',
            'form' => $form,
            'blockers' => $blockers,
        ], new Response(status: 422));
    }
}
