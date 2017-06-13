<?php

/**
 * Connection Custom Repository.
 * Connection Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Node;

class ConnectionRepository extends EntityRepository
{

    /**
     * Finds all connections for the given node.
     * @param Node $node
     * @return array
     */
    public function findBySourceNode(Node $node)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where('c.sourceNode = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getResult();
    }

}
