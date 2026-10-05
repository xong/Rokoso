<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BlockedSender;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\BlockedSenderRepository;
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
 * Blocked senders of an organization: their mails go to the spam folder (blocking from a mail: MessageActions).
 */
final class BlockedSenderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BlockedSenderRepository $blockedSenders,
    ) {
    }

    #[Route('/organizations/{id<\d+>}/blocked-senders', name: 'blocked_sender_add', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, 'organization')]
    #[IsCsrfTokenValid(new Expression('"blocked-sender-" ~ args["organization"].getId()'))]
    public function add(Request $request, Organization $organization, #[CurrentUser] User $user): Response
    {
        $address = BlockedSender::normalize($request->getPayload()->getString('address'));
        $valid = 1 === preg_match('/^[^@\s]*@[^@\s]+\.[^@\s]+$/', $address) && mb_strlen($address) <= 255;
        if (!$valid) {
            $this->addFlash('error', 'blocked_sender.invalid');
        } elseif (null === $this->blockedSenders->findOneFor($organization, $address)) {
            $this->em->persist(new BlockedSender($organization, $address, $user));
            $this->em->flush();
            $this->addFlash('success', 'blocked_sender.added');
        }

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId(), '_fragment' => 'blocked-heading']);
    }

    #[Route('/blocked-senders/{id<\d+>}/delete', name: 'blocked_sender_delete', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, new Expression('args["blocked"].getOrganization()'))]
    #[IsCsrfTokenValid(new Expression('"delete-blocked-sender-" ~ args["blocked"].getId()'))]
    public function delete(BlockedSender $blocked): Response
    {
        $organization = $blocked->getOrganization();
        $this->em->remove($blocked);
        $this->em->flush();
        $this->addFlash('success', 'blocked_sender.removed');

        return $this->redirectToRoute('organization_show', ['id' => $organization->getId(), '_fragment' => 'blocked-heading']);
    }
}
