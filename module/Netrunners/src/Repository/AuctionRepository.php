<?php

/**
 * Auction Custom Repository.
 * Auction Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;

class AuctionRepository extends EntityRepository
{

    /**
     * @param Node $node
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countActiveByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select($qb->expr()->count('a.id'));
        $qb->where('a.bought IS NULL AND a.node = :node AND a.expires > :now');
        $qb->setParameters([
            'node' => $node,
            'now' => new \DateTime()
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Node $node
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select($qb->expr()->count('a.id'));
        $qb->where('a.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Node $node
     * @return array
     */
    public function findActiveByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where('a.bought IS NULL AND a.node = :node AND a.expires > :now');
        $qb->setParameters([
            'node' => $node,
            'now' => new \DateTime()
        ]);
        $qb->orderBy('a.expires', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findClaimableForProfile(Profile $profile)
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where('a.buyer = :profile and a.claimed IS NULL');
        $qb->setParameter('profile', $profile);
        return $qb->getQuery()->getResult();
    }

}
