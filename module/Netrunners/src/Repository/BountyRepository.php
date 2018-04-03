<?php

/**
 * Bounty Custom Repository.
 * Bounty Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class BountyRepository extends EntityRepository
{

    /**
     * @param $limit
     * @param $offset
     * @return array
     */
    public function findForShowBountiesCommand($limit, $offset)
    {
        $qb = $this->createQueryBuilder('b');
        $qb->leftJoin('b.target', 't');
        $qb->leftJoin('t.user', 'u');
        $qb->select('SUM(b.amount) AS totalamount');
        $qb->addSelect('u.username AS username');
        $qb->groupBy('t');
        $qb->orderBy('totalamount', 'desc');
        $qb->where('b.claimed IS NULL');
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        if ($offset) {
            $qb->setFirstResult($offset);
        }
        return $qb->getQuery()->getResult();
    }

}
