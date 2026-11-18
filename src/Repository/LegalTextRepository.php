<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LegalText;
use App\Enum\LegalDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalText>
 */
class LegalTextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalText::class);
    }

    /** Text of the document, new (empty) if not yet written */
    public function get(LegalDocument $document): LegalText
    {
        return $this->find($document) ?? new LegalText($document);
    }

    /**
     * Documents with text, in enum order – only these are linked.
     *
     * @return list<LegalDocument>
     */
    public function published(): array
    {
        $documents = $this->createQueryBuilder('l')
            ->select('l.document')
            ->where("l.content <> ''")
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter(LegalDocument::cases(), static fn (LegalDocument $d): bool => \in_array($d, $documents, true) || \in_array($d->value, $documents, true)));
    }
}
