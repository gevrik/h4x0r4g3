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

}
