<?php

/**
 * MilkrunInstance Custom Repository.
 * MilkrunInstance Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class MilkrunInstanceRepository extends EntityRepository
{

    /**
     * Returns the currently runing milkrun or null.
     * @param Profile $profile
     * @return mixed
     */
    public function findCurrentMilkrun(Profile $profile)
    {
        $qb = $this->createQueryBuilder('mri');
        $qb->where('mri.expires > :now AND mri.profile = :profile');
        $qb->setParameters([
            'now' => new \DateTime(),
            'profile' => $profile
        ]);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
