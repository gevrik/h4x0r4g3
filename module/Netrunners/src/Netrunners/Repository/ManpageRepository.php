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

class ManpageRepository extends EntityRepository
{

    public function findByKeyword($keyword)
    {
        $qb = $this->createQueryBuilder('m');
        $qb->where($qb->expr()->like('m.subject', $qb->expr()->literal('%' . $keyword . '%')));
        return $qb->getQuery()->getResult();
    }

}
