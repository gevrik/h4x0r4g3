<?php

/**
 * FileModInstance Custom Repository.
 * FileModInstance Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\File;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\Profile;

class FileModInstanceRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @param FileMod $fileMod
     * @param int $minLevel
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByProfileAndTypeAndMinLevel(Profile $profile, FileMod $fileMod, $minLevel = 1)
    {
        $qb = $this->createQueryBuilder('fmi');
        $qb->where('fmi.profile = :profile AND fmi.fileMod = :fileMod AND fmi.level >= :minLevel');
        $qb->select($qb->expr()->count('fmi.id'));
        $qb->setParameters([
            'profile' => $profile,
            'fileMod' => $fileMod,
            'minLevel' => $minLevel
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Profile $profile
     * @param FileMod $fileMod
     * @param int $minLevel
     * @param bool $orderByLevel
     * @return array
     */
    public function findByProfileAndTypeAndMinLevel(Profile $profile, FileMod $fileMod, $minLevel = 1, $orderByLevel = false)
    {
        $qb = $this->createQueryBuilder('fmi');
        $qb->where('fmi.profile = :profile AND fmi.fileMod = :fileMod AND fmi.level >= :minLevel AND fmi.file IS NULL');
        $qb->setParameters([
            'profile' => $profile,
            'fileMod' => $fileMod,
            'minLevel' => $minLevel
        ]);
        if ($orderByLevel) {
            $qb->addOrderBy('fpi.level', 'ASC');
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findForPartsCommand(Profile $profile)
    {
        $qb = $this->createQueryBuilder('fmi');
        $qb->leftJoin('fmi.fileMod', 'fm');
        $qb->where('fmi.profile = :profile AND fmi.file IS NULL');
        $qb->setParameter('profile', $profile);
        $qb->select('COUNT(fmi.id) AS fmicount');
        $qb->addSelect('fm.name AS fmname');
        $qb->addSelect('MIN(fmi.level) AS minlevel');
        $qb->addSelect('MAX(fmi.level) AS maxlevel');
        $qb->groupBy('fmname');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param File $file
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByFile(File $file)
    {
        $qb = $this->createQueryBuilder('fmi');
        $qb->select($qb->expr()->count('fmi.id'));
        $qb->where('fmi.file = :file');
        $qb->setParameter('file', $file);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param File $file
     * @return array
     */
    public function findByFile(File $file)
    {
        $qb = $this->createQueryBuilder('fmi');
        $qb->where('fmi.file = :file');
        $qb->setParameter('file', $file);
        return $qb->getQuery()->getResult();
    }

}
