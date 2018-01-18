<?php

/**
 * PlaySession Custom Repository.
 * PlaySession Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class PlaySessionRepository extends EntityRepository
{

    /**
     * @param Profile|NULL $profile
     * @return array
     */
    public function findOrphaned(Profile $profile = NULL)
    {
        $qb = $this->createQueryBuilder('ps');
        if ($profile) {
            $qb->where('ps.profile = :profile AND ps.end IS NULL');
            $qb->setParameter('profile', $profile);
        }
        else {
            $qb->where('ps.end IS NULL');
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $profile
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findCurrentPlaySession(Profile $profile)
    {
        $qb = $this->createQueryBuilder('ps');
        $qb->where('ps.profile = :profile AND ps.end IS NULL');
        $qb->setParameter('profile', $profile);
        $qb->orderBy('ps.id', 'DESC');
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param Profile $profile
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLastPlaySession(Profile $profile)
    {
        $qb = $this->createQueryBuilder('ps');
        $qb->where('ps.profile = :profile AND ps.end IS NOT NULL');
        $qb->setParameter('profile', $profile);
        $qb->orderBy('ps.end', 'DESC');
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
