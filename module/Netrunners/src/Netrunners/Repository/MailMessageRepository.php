<?php

/**
 * MailMessage Custom Repository.
 * MailMessage Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class MailMessageRepository extends EntityRepository
{

    /**
     * Returns the amount of mails for the given profile.
     * @param Profile $profile
     * @return mixed
     */
    public function countByTotalMails(Profile $profile)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('count(mm.id)');
        $qb->from('Netrunners\Entity\MailMessage', 'mm');
        $qb->where('mm.recipient = :profile');
        $qb->setParameter('profile', $profile);
        $query = $qb->getQuery();
        $result = $query->getSingleScalarResult();
        return $result;
    }

    /**
     * Returns the amount of unread mails for the given profile.
     * @param Profile $profile
     * @return mixed
     */
    public function countByUnreadMails(Profile $profile)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('count(mm.id)');
        $qb->from('Netrunners\Entity\MailMessage', 'mm');
        $qb->where('mm.recipient = :profile AND mm.readDateTime IS NULL');
        $qb->setParameter('profile', $profile);
        $query = $qb->getQuery();
        $result = $query->getSingleScalarResult();
        return $result;
    }

}
