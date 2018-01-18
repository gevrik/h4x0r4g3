<?php

/**
 * Invitation Custom Repository.
 * Invitation Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class InvitationRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @return array
     */
    public function findAllByProfile(Profile $profile)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.givenTo = :profile');
        $qb->setParameter(':profile', $profile);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findUnusedByProfile(Profile $profile)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.givenTo = :profile AND i.used IS NULL');
        $qb->setParameter(':profile', $profile);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $code
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneUnusedByCode($code)
    {
        $qb = $this->createQueryBuilder('i');
        $qb->where('i.code = :code AND i.used IS NULL');
        $qb->setParameter(':code', $code);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
