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
use Netrunners\Entity\FileMod;
use Netrunners\Entity\Profile;

class FileModInstanceRepository extends EntityRepository
{

    public function findByProfileAndTypeAndMinLevel(Profile $profile, FileMod $fileMod, $minLevel = 1, $orderByLevel = false)
    {
        $qb = $this->createQueryBuilder('fmi');
        $qb->where('fmi.profile = :profile AND fmi.fileMod = :fileMod AND fmi.level >= :minLevel');
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

}
