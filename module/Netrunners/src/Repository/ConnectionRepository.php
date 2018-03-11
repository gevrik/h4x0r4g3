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
use Netrunners\Entity\Connection;
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

    /**
     * @param Node $node
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countBySourceNode(Node $node)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select($qb->expr()->count('c.id'));
        $qb->where('c.sourceNode = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Finds the connection for the given source and target node combination.
     * @param Node $sourceNode
     * @param Node $targetNode
     * @return Connection
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findBySourceNodeAndTargetNode(Node $sourceNode, Node $targetNode)
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where('c.sourceNode = :sourceNode AND c.targetNode = :targetNode');
        $qb->setParameters([
            'sourceNode' => $sourceNode,
            'targetNode' => $targetNode
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
