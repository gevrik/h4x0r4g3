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

    public function findBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getResult();
    }

    public function findByType($type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.type = :type');
        $qb->setParameter('type', $type);
        return $qb->getQuery()->getResult();
    }

    public function findBySystemAndType(System $system, $type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.type = :type and n.system = :system');
        $qb->setParameters([
            'type' => $type,
            'system' => $system
        ]);
        return $qb->getQuery()->getResult();
    }

}
