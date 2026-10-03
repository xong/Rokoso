<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MailRule;
use App\Entity\Organization;
use App\Entity\User;
use App\Form\MailRuleFormType;
use App\Repository\OrganizationRepository;
use App\Security\Voter\OrganizationVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Inbox rules (Postfach-Regeln), managed by organization administrators.
 */
final class MailRuleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    #[Route('/organizations/{id<\d+>}/mail-rules/new', name: 'mail_rule_new')]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    public function new(Request $request, Organization $organization, #[CurrentUser] User $user): Response
    {
        return $this->form($request, new MailRule($organization), $user);
    }

    #[Route('/mail-rules/{id<\d+>}/edit', name: 'mail_rule_edit')]
    #[IsGranted(OrganizationVoter::MANAGE, 'rule.organization')]
    public function edit(Request $request, MailRule $rule, #[CurrentUser] User $user): Response
    {
        return $this->form($request, $rule, $user);
    }

    #[Route('/mail-rules/{id<\d+>}/delete', name: 'mail_rule_delete', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, 'rule.organization')]
    #[IsCsrfTokenValid(new Expression('"delete-mail-rule-" ~ args["rule"].getId()'))]
    public function delete(MailRule $rule): Response
    {
        $organization = $rule->getOrganization();
        $this->em->remove($rule);
        $this->em->flush();
        $this->addFlash('success', 'flash.deleted');

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId(), '_fragment' => 'rules-heading']);
    }

    private function form(Request $request, MailRule $rule, User $user): Response
    {
        $organization = $rule->getOrganization();
        $form = $this->createForm(MailRuleFormType::class, $rule, ['organization' => $organization]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($rule);
            $this->em->flush();
            $this->addFlash('success', 'flash.saved');

            return $this->redirectToRoute('organization_show', ['id' => $organization->getId(), '_fragment' => 'rules-heading']);
        }

        return $this->render('mail_rule/form.html.twig', [
            'organizations' => $this->organizations->findForUser($user),
            'organization' => $organization,
            'rule' => null === $rule->getId() ? null : $rule,
            'form' => $form,
        ]);
    }
}
