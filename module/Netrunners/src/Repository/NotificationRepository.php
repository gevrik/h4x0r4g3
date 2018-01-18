<?php

/**
 * Notification Custom Repository.
 * Notification Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class NotificationRepository extends EntityRepository
{
    /**
     * Returns the amount of unread notifications for the given profile.
     * @param Profile $profile
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countUnreadByProfile(Profile $profile)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('count(n.id)');
        $qb->from('Netrunners\Entity\Notification', 'n');
        $qb->where('n.profile = :profile AND n.readDateTime IS NULL');
        $qb->setParameter('profile', $profile);
        $query = $qb->getQuery();
        $result = $query->getSingleScalarResult();
        return $result;
    }

    /**
     * Returns the unread notifications for the given profile.
     * @param Profile $profile
     * @return mixed
     */
    public function findUnreadByProfile(Profile $profile)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.profile = :profile AND n.readDateTime IS NULL');
        $qb->setParameter('profile', $profile);
        $query = $qb->getQuery();
        return $query->getResult();
    }

}
