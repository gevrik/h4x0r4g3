<?php

/**
 * Node Custom Repository.
 * Node Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\System;

class NodeRepository extends EntityRepository
{

    /**
     * @param System $system
     * @return array
     */
    public function findBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @return mixed
     */
    public function countBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->select($qb->expr()->count('n.id'));
        $qb->where('n.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param $type
     * @return array
     */
    public function findByType($type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.nodeType = :type');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', $type);
        $qb->setParameter('type', $nodeType);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @param $type
     * @return array
     */
    public function findBySystemAndType(System $system, $type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.nodeType = :type and n.system = :system');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', $type);
        $qb->setParameters([
            'type' => $nodeType,
            'system' => $system
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @param $type
     * @return mixed
     */
    public function countBySystemAndType(System $system, $type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->select($qb->expr()->count('n.id'));
        $qb->where('n.nodeType = :type and n.system = :system');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', $type);
        $qb->setParameters([
            'type' => $nodeType,
            'system' => $system
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

}
