<?php

/**
 * Feedback Custom Repository.
 * Feedback Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class FeedbackRepository extends EntityRepository
{

    /**
     * @param \DateTime $lastLogoutDate
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByNewForProfile(\DateTime $lastLogoutDate)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->select($qb->expr()->count('f.id'));
        $qb->where('f.added >= :lastLogoutDate');
        $qb->setParameter('lastLogoutDate', $lastLogoutDate);
        return $qb->getQuery()->getSingleScalarResult();
    }

}
