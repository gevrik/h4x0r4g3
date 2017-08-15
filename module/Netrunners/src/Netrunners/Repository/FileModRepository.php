<?php

/**
 * FileMod Custom Repository.
 * FileMod Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class FileModRepository extends EntityRepository
{

    /**
     * @param Profile|NULL $profile
     * @return array
     */
    public function findForCoding(Profile $profile = NULL)
    {
        $qb = $this->createQueryBuilder('fm');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $keyword
     * @return array
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('fm');
        $qb->where($qb->expr()->like('fm.name', $qb->expr()->literal($keyword . '%')));
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
