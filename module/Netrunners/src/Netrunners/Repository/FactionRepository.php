<?php

/**
 * Faction Custom Repository.
 * Faction Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class FactionRepository extends EntityRepository
{

    public function findAllForMilkrun()
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.joinable IS NOT NULL');
        return $qb->getQuery()->getResult();
    }

}
