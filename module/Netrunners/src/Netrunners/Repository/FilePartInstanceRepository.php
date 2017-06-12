<?php

/**
 * FilePartInstance Custom Repository.
 * FilePartInstance Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Profile;

class FilePartInstanceRepository extends EntityRepository
{

    public function findByProfile(Profile $profile)
    {
        return $this->findBy(['profile' => $profile]);
    }

    public function findByProfileAndType(Profile $profile, FileType $fileType)
    {
        return $this->findBy(['profile' => $profile, 'fileType' => $fileType]);
    }

    public function findByProfileAndTypeAndMinLevel(Profile $profile, FilePart $filePart, $minLevel = 1)
    {
        $qb = $this->createQueryBuilder('fpi');
        $qb->where('fpi.profile = :profile AND fpi.filePart = :filePart AND fpi.level >= :minLevel');
        $qb->setParameters([
            'profile' => $profile,
            'filePart' => $filePart,
            'minLevel' => $minLevel
        ]);
        return $qb->getQuery()->getResult();
    }

    public function findForPartsCommand(Profile $profile)
    {
        $qb = $this->createQueryBuilder('fpi');
        $qb->leftJoin('fpi.filePart', 'fp');
        $qb->where('fpi.profile = :profile');
        $qb->setParameter('profile', $profile);
        $qb->select('COUNT(fpi.id) AS fpicount');
        $qb->addSelect('fp.name AS fpname');
        $qb->addSelect('MIN(fpi.level) AS minlevel');
        $qb->addSelect('MAX(fpi.level) AS maxlevel');
        $qb->groupBy('fpname');
        return $qb->getQuery()->getResult();
    }

}
