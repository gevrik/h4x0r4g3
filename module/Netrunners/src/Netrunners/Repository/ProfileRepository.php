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
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
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

    /**
     * Returns all profiles that are currently connected to the given node.
     * A profile can be given, this will exclude the given profile from the results.
     * @param Node $node
     * @param Profile|NULL $profile
     * @return array
     */
    public function findByCurrentNode(Node $node, Profile $profile = NULL)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.currentNode = :currentNode');
        $qb->setParameter('currentNode', $node);
        if ($profile) {
            $qb->andWhere('p.id != :profileId');
            $qb->setParameter('profileId', $profile->getId());
        }
        return $qb->getQuery()->getResult();
    }

}
