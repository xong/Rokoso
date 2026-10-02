<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\MessageType;
use App\Form\InternalMessageFormType;
use App\Mail\InternalMessageData;
use App\Mail\ReadTracker;
use App\Repository\MembershipRepository;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use App\Security\Voter\MessageVoter;
use App\Service\AttachmentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Internal messages: stored as Message (type internal) and shown in the same list as emails.
 */
final class InternalMessageController extends AbstractController
{
    #[Route('/mail/message/new', name: 'mail_message_new')]
    public function new(
        Request $request,
        #[CurrentUser] User $user,
        MembershipRepository $memberships,
        OrganizationRepository $organizations,
        ProjectRepository $projects,
        MessageRepository $messages,
        AttachmentStorage $storage,
        ReadTracker $readTracker,
        EntityManagerInterface $em,
    ): Response {
        $users = array_values(array_filter($memberships->colleaguesOf($user), static fn (User $u): bool => $u !== $user));
        $data = new InternalMessageData();

        if ($request->query->getInt('reply') > 0) {
            $original = $messages->find($request->query->getInt('reply'));
            if (null === $original) {
                throw $this->createNotFoundException();
            }
            $this->denyAccessUnlessGranted(MessageVoter::VIEW, $original);
            $data->original = $original;
            $data->subject = preg_match('/^(re|aw):/i', $original->getSubject()) ? $original->getSubject() : 'Re: '.$original->getSubject();
            $data->project = $original->isInternal() ? $original->getProject() : null;
            $data->organization = $original->isInternal() && null === $data->project ? $original->getOrganization() : null;
            $data->recipients = array_values(array_filter(
                [$original->getAuthor(), ...$original->getRecipientUsers()->toArray()],
                static fn (?User $u): bool => null !== $u && $u !== $user && \in_array($u, $users, true),
            ));
        }
        if ($request->query->getInt('to') > 0) {
            foreach ($users as $candidate) {
                if ($candidate->getId() === $request->query->getInt('to')) {
                    $data->recipients = [$candidate];
                }
            }
        }

        $form = $this->createForm(InternalMessageFormType::class, $data, [
            'users' => $users,
            'organizations' => $organizations->findForUser($user),
            'projects' => $projects->findVisibleFor($user),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = (new Message(MessageType::Internal))
                ->setAuthor($user)
                ->setFrom($user->getEmail(), $user->getName())
                ->setSubject($data->subject)
                ->setBody($data->body)
                ->setProject($data->project)
                ->setOrganization($data->project?->getOrganization() ?? $data->organization)
                ->setInReplyTo(null !== $data->original ? (string) $data->original->getId() : null);
            foreach ($data->recipients as $recipient) {
                $message->addRecipientUser($recipient);
            }
            foreach ((array) $form->get('files')->getData() as $file) {
                if ($file instanceof UploadedFile) {
                    $content = (string) file_get_contents($file->getPathname());
                    $message->addAttachment(new Attachment($message, $file->getClientOriginalName(), $file->getClientMimeType(), \strlen($content), $storage->store($content)));
                }
            }
            $em->persist($message);
            $em->flush();
            $readTracker->markRead($message, $user);
            $this->addFlash('success', 'internal.sent');

            return $this->redirectToRoute('mail_show', ['folder' => 'sent', 'id' => $message->getId()]);
        }

        return $this->render('internal_message/form.html.twig', ['form' => $form, 'original' => $data->original]);
    }
}
