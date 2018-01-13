<?php

/**
 * FilePart Custom Repository.
 * FilePart Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class FilePartRepository extends EntityRepository
{

    /**
     * @param Profile|NULL $profile
     * @return array
     */
    public function findForCoding(Profile $profile = NULL)
    {
        $qb = $this->createQueryBuilder('ft');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $keyword
     * @return array
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('fp');
        $qb->where($qb->expr()->like('fp.name', $qb->expr()->literal($keyword . '%')));
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
