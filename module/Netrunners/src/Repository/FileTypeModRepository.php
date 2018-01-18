<?php

/**
 * FileTypeMod Custom Repository.
 * FileTypeMod Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileType;

class FileTypeModRepository extends EntityRepository
{

    /**
     * If the count is 0, then the filemod can be used on all filetypes.
     * @param FileMod $fileMod
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByFileMod(FileMod $fileMod)
    {
        $qb = $this->createQueryBuilder('ftm');
        $qb->select($qb->expr()->count('ftm.id'));
        $qb->where('ftm.fileMod = :fileMod');
        $qb->setParameter('fileMod', $fileMod);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param FileMod $fileMod
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByFileType(FileMod $fileMod)
    {
        $qb = $this->createQueryBuilder('ftm');
        $qb->select($qb->expr()->count('f.id'));
        $qb->where('ftm.fileMod = :fileMod');
        $qb->setParameter('fileMod', $fileMod);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param FileType $fileType
     * @return array
     */
    public function findByFileType(FileType $fileType)
    {
        $qb = $this->createQueryBuilder('ftm');
        $qb->where('ftm.fileType = :fileType');
        $qb->setParameter('fileType', $fileType);
        return $qb->getQuery()->getResult();
    }

}
