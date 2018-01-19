<?php

/**
 * Npc Custom Repository.
 * Npc Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Npc;

class NpcRepository extends EntityRepository
{

    /**
     * @param $keyword
     * @return Npc|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where($qb->expr()->like('n.name', $qb->expr()->literal($keyword . '%')));
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
