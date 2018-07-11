<?php

/**
 * SystemRoleInstance Custom Repository.
 * SystemRoleInstance Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;

class SystemRoleInstanceRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @param System|null $system
     * @return array
     */
    public function getProfileSystemRoles(Profile $profile, System $system = null)
    {
        if ($system === null) {
            $system = $profile->getCurrentNode()->getSystem();
        }
        $qb = $this->createQueryBuilder('sr');
        $qb->where('sr.profile = :profile AND sr.system = :system AND sr.expires <= :now');
        $qb->orWhere('sr.profile = :profile AND sr.system = :system AND sr.expires IS NULL');
        $qb->setParameters([
            'profile' => $profile,
            'system' => $system,
            'now' => new \DateTime()
        ]);
        return $qb->getQuery()->getResult();
    }

}
