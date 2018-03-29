<?php

/**
 * Netrunners Abstract Repository.
 * Netrunners Abstract Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

abstract class NetrunnersAbstractRepo extends EntityRepository
{

    /**
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAll()
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select($qb->expr()->count('e.id'));
        return $qb->getQuery()->getSingleScalarResult();
    }

}
