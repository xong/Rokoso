<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MailAccount;
use App\Entity\Organization;
use App\Entity\User;
use App\Form\MailAccountFormType;
use App\Mail\MailboxReader;
use App\Mail\SmtpTransportFactory;
use App\Repository\OrganizationRepository;
use App\Security\Voter\MailAccountVoter;
use App\Security\Voter\OrganizationVoter;
use App\Service\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mail accounts are managed by organization administrators (shown on the organization page).
 */
final class MailAccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SecretBox $secretBox,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    #[Route('/organizations/{id<\d+>}/mail-accounts/new', name: 'mail_account_new')]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    public function new(Request $request, Organization $organization, #[CurrentUser] User $user): Response
    {
        $account = new MailAccount($organization);
        $form = $this->createForm(MailAccountFormType::class, $account, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storePasswords($form, $account);
            $this->em->persist($account);
            $this->em->flush();
            $this->addFlash('success', 'mail_account.created');

            return $this->redirectToRoute('mail_account_edit', ['id' => $account->getId()]);
        }

        return $this->render('mail_account/form.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'account' => null,
            'form' => $form,
        ]);
    }

    #[Route('/mail-accounts/{id<\d+>}/edit', name: 'mail_account_edit')]
    #[IsGranted(MailAccountVoter::MANAGE, 'account')]
    public function edit(Request $request, MailAccount $account, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(MailAccountFormType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storePasswords($form, $account);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('mail_account_edit', ['id' => $account->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->em->refresh($account);
        }

        return $this->render('mail_account/form.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $account->getOrganization(),
            'account' => $account,
            'form' => $form,
        ]);
    }

    #[Route('/mail-accounts/{id<\d+>}/test', name: 'mail_account_test', methods: ['POST'])]
    #[IsGranted(MailAccountVoter::MANAGE, 'account')]
    #[IsCsrfTokenValid('mail-account-test')]
    public function test(MailAccount $account, MailboxReader $reader, SmtpTransportFactory $smtp, TranslatorInterface $translator): Response
    {
        foreach (['imap' => static fn () => $reader->test($account), 'smtp' => static fn () => $smtp->test($account)] as $protocol => $check) {
            try {
                $check();
                $this->addFlash('success', $translator->trans('mail_account.test_ok', ['%protocol%' => strtoupper($protocol)]));
            } catch (\Throwable $e) {
                $this->addFlash('error', $translator->trans('mail_account.test_failed', ['%protocol%' => strtoupper($protocol), '%error%' => $e->getMessage()]));
            }
        }

        return $this->redirectToRoute('mail_account_edit', ['id' => $account->getId()]);
    }

    #[Route('/mail-accounts/{id<\d+>}/delete', name: 'mail_account_delete', methods: ['POST'])]
    #[IsGranted(MailAccountVoter::MANAGE, 'account')]
    #[IsCsrfTokenValid(new Expression('"delete-mail-account-" ~ args["account"].getId()'))]
    public function delete(#[MapEntity] MailAccount $account): Response
    {
        $organization = $account->getOrganization();
        $this->em->remove($account);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId()]);
    }

    /**
     * @param FormInterface<MailAccount> $form
     */
    private function storePasswords(FormInterface $form, MailAccount $account): void
    {
        $imap = (string) $form->get('imapPasswordPlain')->getData();
        if ('' !== $imap) {
            $account->setImapPassword($this->secretBox->encrypt($imap));
        }
        $smtp = (string) $form->get('smtpPasswordPlain')->getData();
        if ('' !== $smtp) {
            $account->setSmtpPassword($this->secretBox->encrypt($smtp));
        }
    }
}
