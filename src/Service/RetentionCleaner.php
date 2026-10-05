<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CalendarItem;
use App\Entity\EventSignup;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Repository\ConfidentialCaseRepository;
use App\Repository\SecurityEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;

/**
 * Applies the deletion periods of each organization (trash, old messages, public submissions,
 * confidential conversations) and drops security log entries after one year. Called by `app:notify`.
 */
final readonly class RetentionCleaner
{
    private const int BATCH = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private AttachmentStorage $attachments,
        private SecurityEventRepository $securityEvents,
        private ConfidentialCaseRepository $confidentialCases,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{messages: int, submissions: int, confidential: int, security: int}
     */
    public function clean(): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $messages = 0;
        $submissions = 0;
        $confidential = 0;
        foreach ($this->em->getRepository(Organization::class)->findAll() as $organization) {
            $messages += $this->removeMessages($this->em->createQueryBuilder()->select('m')->from(Message::class, 'm')
                ->where('m.organization = :org')->andWhere('m.trashedAt < :before OR m.spamAt < :before')
                ->setParameter('org', $organization)->setParameter('before', $now->modify(\sprintf('-%d days', $organization->getTrashDays()))));
            if (null !== $years = $organization->getMessageRetentionYears()) {
                $messages += $this->removeMessages($this->em->createQueryBuilder()->select('m')->from(Message::class, 'm')
                    ->where('m.organization = :org')->andWhere('m.date < :before')
                    ->setParameter('org', $organization)->setParameter('before', $now->modify(\sprintf('-%d years', $years))));
            }
            if (null !== $years = $organization->getSubmissionRetentionYears()) {
                $before = $now->modify(\sprintf('-%d years', $years));
                $surveys = $this->em->createQueryBuilder()->select('s.id')->from(Survey::class, 's')->where('s.organization = :org');
                $submissions += (int) $this->em->createQueryBuilder()->delete(SurveyResponse::class, 'r')
                    ->where('r.createdAt < :before')->andWhere('r.survey IN ('.$surveys->getDQL().')')
                    ->setParameter('before', $before)->setParameter('org', $organization)->getQuery()->execute();
                $items = $this->em->createQueryBuilder()->select('i.id')->from(CalendarItem::class, 'i')->where('i.organization = :org');
                $submissions += (int) $this->em->createQueryBuilder()->delete(EventSignup::class, 'e')
                    ->where('e.createdAt < :before')->andWhere('e.item IN ('.$items->getDQL().')')
                    ->setParameter('before', $before)->setParameter('org', $organization)->getQuery()->execute();
            }
            $confidential += $this->confidentialCases->deleteInactiveSince($organization, $now->modify(\sprintf('-%d months', $organization->getConfidentialRetentionMonths())));
        }

        return [
            'messages' => $messages,
            'submissions' => $submissions,
            'confidential' => $confidential,
            'security' => $this->securityEvents->deleteOlderThan($now->modify('-1 year')),
        ];
    }

    /**
     * Removes through the ORM (attachments, comments, events cascade) and then the attachment files.
     */
    private function removeMessages(QueryBuilder $query): int
    {
        $count = 0;
        do {
            /** @var list<Message> $batch */
            $batch = $query->setMaxResults(self::BATCH)->getQuery()->getResult();
            $paths = [];
            foreach ($batch as $message) {
                foreach ($message->getAttachments() as $attachment) {
                    $paths[] = $attachment->getStoragePath();
                }
                $this->em->remove($message);
            }
            $this->em->flush();
            foreach ($paths as $path) {
                $this->attachments->remove($path);
            }
            $count += \count($batch);
        } while (self::BATCH === \count($batch));

        return $count;
    }
}
