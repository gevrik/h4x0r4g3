<?php

/**
 * Profile Custom Repository.
 * Profile Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\System;

class ProfileRepository extends EntityRepository
{

    /**
     * Returns all profiles that are currently connected to the given system.
     * @param System $system
     * @return array
     */
    public function findByCurrentSystem(System $system)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.currentSystem = :currentSystem');
        $qb->setParameter('currentSystem', $system);
        return $qb->getQuery()->getResult();
    }

}
