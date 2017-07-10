<?php

/**
 * NpcInstance Custom Repository.
 * NpcInstance Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Node;
use Netrunners\Entity\System;

class NpcInstanceRepository extends EntityRepository
{

    public function countBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->select($qb->expr()->count('ni.id'));
        $qb->where('ni.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getSingleScalarResult();
    }

    public function findByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->where('ni.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getResult();
    }

    public function findOneByHomeNode(Node $node)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->where('ni.homeNode = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
