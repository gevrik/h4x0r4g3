<?php

/**
 * FileCategory Custom Repository.
 * FileCategory Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class FileCategoryRepository extends EntityRepository
{

    /**
     * @param $keyword
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('fc');
        $qb->where($qb->expr()->like('fc.name', $qb->expr()->literal($keyword . '%')));
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
