<?php

/**
 * Manpage Custom Repository.
 * Manpage Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Manpage;

class ManpageRepository extends EntityRepository
{

    /**
     * @param $keyword
     * @return array
     */
    public function findByKeyword($keyword)
    {
        $qb = $this->createQueryBuilder('m');
        $qb->where($qb->expr()->like('m.subject', $qb->expr()->literal('%' . $keyword . '%')));
        $qb->andWhere('m.status != :status');
        $qb->setParameter('status', Manpage::STATUS_INVALID);
        return $qb->getQuery()->getResult();
    }

}
