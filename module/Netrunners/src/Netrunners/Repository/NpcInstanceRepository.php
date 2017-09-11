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
use Netrunners\Entity\Npc;
use Netrunners\Entity\System;

class NpcInstanceRepository extends EntityRepository
{

    /**
     * @param System $system
     * @return mixed
     */
    public function countBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->select($qb->expr()->count('ni.id'));
        $qb->where('ni.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Node $node
     * @return array
     */
    public function findByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->where('ni.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @return array
     */
    public function countByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->select($qb->expr()->count('ni.id'));
        $qb->where('ni.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Node $node
     * @return mixed
     */
    public function findOneByHomeNode(Node $node)
    {
        $qb = $this->createQueryBuilder('ni');
        $qb->where('ni.homeNode = :node');
        $qb->setParameter('node', $node);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int $npcId
     * @return array
     */
    public function findByNpcId($npcId)
    {
        $npc = $this->_em->find('Netrunners\Entity\Npc', $npcId);
        $qb = $this->createQueryBuilder('ni');
        $qb->where('ni.npc = :npc');
        $qb->setParameter('npc', $npc);
        return $qb->getQuery()->getResult();
    }

}
