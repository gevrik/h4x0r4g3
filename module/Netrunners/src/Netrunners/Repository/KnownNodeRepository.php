<?php

/**
 * KnownNode Custom Repository.
 * KnownNode Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;

class KnownNodeRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @param Node $node
     * @return null|object
     */
    public function findByProfileAndNode(Profile $profile, Node $node)
    {
        return $this->findOneBy([
            'profile' => $profile,
            'node' => $node
        ]);
    }

    /**
     * @param Node $node
     * @return array
     */
    public function findByNode(Node $node)
    {
        return $this->findBy([
            'node' => $node
        ]);
    }

    /**
     * @param Profile $profile
     * @param System $system
     * @return array
     */
    public function findByProfileAndSystem(Profile $profile, System $system)
    {
        $qb = $this->createQueryBuilder('kn');
        $qb->leftJoin('kn.node', 'n');
        $qb->where('n.system = :system AND kn.profile = :profile');
        $qb->setParameters([
            'system' => $system,
            'profile' => $profile
        ]);
        return $qb->getQuery()->getResult();
    }

}
