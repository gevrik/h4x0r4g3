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

class FilePartRepository extends EntityRepository
{

    /**
     * @return array
     */
    public function findForCoding()
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
        return $qb->getQuery()->getOneOrNullResult();
    }

}
