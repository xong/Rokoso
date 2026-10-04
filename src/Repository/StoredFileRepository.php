<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Folder;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\StoredFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoredFile>
 */
class StoredFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoredFile::class);
    }

    /**
     * @return list<StoredFile>
     */
    public function forProject(Project $project): array
    {
        /* @var list<StoredFile> */
        return $this->createQueryBuilder('f')
            ->andWhere('f.project = :project')
            ->setParameter('project', $project)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * All files of an organization by name (e.g. to attach them to an agenda item).
     *
     * @return list<StoredFile>
     */
    public function forOrganization(Organization $organization): array
    {
        /* @var list<StoredFile> */
        return $this->createQueryBuilder('f')
            ->join('f.folder', 'fo')
            ->andWhere('fo.organization = :org')
            ->setParameter('org', $organization)
            ->orderBy('f.filename')
            ->getQuery()->getResult();
    }

    public function findInFolder(Folder $folder, string $filename): ?StoredFile
    {
        return $this->findOneBy(['folder' => $folder, 'filename' => $filename], ['id' => 'ASC']);
    }

    /**
     * Files in the given folders (e.g. a subtree before deleting it).
     *
     * @param list<Folder> $folders
     *
     * @return list<StoredFile>
     */
    public function inFolders(array $folders): array
    {
        if ([] === $folders) {
            return [];
        }

        /* @var list<StoredFile> */
        return $this->createQueryBuilder('f')
            ->andWhere('f.folder IN (:folders)')
            ->setParameter('folders', $folders)
            ->orderBy('f.filename')
            ->getQuery()->getResult();
    }
}
