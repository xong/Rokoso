<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Draft;
use App\Entity\Message;
use App\Repository\DraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * "Undo send": a sent draft waits a few seconds in the outbox and goes out afterwards
 * (after the next request or via app:mail:outbox). Without delay it is sent at once.
 */
final readonly class Outbox
{
    public function __construct(
        private MailSender $sender,
        private DraftRepository $drafts,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:MAIL_SEND_DELAY')]
        private int $delay,
    ) {
    }

    public function getDelay(): int
    {
        return max(0, $this->delay);
    }

    /**
     * @return Message|null the sent message, or null when it is queued
     */
    public function queue(Draft $draft): ?Message
    {
        if ($this->getDelay() <= 0) {
            return $this->sender->send($draft);
        }
        $draft->queue($this->now()->modify(\sprintf('+%d seconds', $this->getDelay())));
        $this->em->flush();

        return null;
    }

    /**
     * Sends all queued drafts whose delay has passed. Failed ones go back to the drafts with the error.
     */
    public function sendDue(): int
    {
        $sent = 0;
        foreach ($this->drafts->findDue($this->now()) as $draft) {
            if (!$this->claim($draft)) {
                continue;
            }
            try {
                $this->sender->send($draft);
                ++$sent;
            } catch (\Throwable $e) {
                $this->logger->error('Sending queued mail failed: {error}', ['error' => $e->getMessage()]);
                if ($this->em->isOpen() && $this->em->contains($draft)) {
                    $draft->unqueue($e->getMessage());
                    $this->em->flush();
                }
            }
        }

        return $sent;
    }

    /**
     * Marks the draft as being sent, so a parallel run does not send it twice.
     */
    private function claim(Draft $draft): bool
    {
        $claimed = $this->em->createQuery(\sprintf('UPDATE %s d SET d.sendAt = :later WHERE d.id = :id AND d.sendAt <= :now', Draft::class))
            ->setParameter('later', $this->now()->modify('+1 hour'))
            ->setParameter('id', $draft->getId())
            ->setParameter('now', $this->now())
            ->execute();

        return 1 === $claimed;
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
