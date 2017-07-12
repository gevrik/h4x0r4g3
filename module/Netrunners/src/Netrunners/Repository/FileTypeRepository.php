<?php

/**
 * FileType Custom Repository.
 * FileType Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class FileTypeRepository extends EntityRepository
{

    /**
     * @return array
     */
    public function findForCoding()
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->where('ft.codable > 0');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $keyword
     * @return array
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->where($qb->expr()->like('ft.name', $qb->expr()->literal($keyword . '%')));
        return $qb->getQuery()->getOneOrNullResult();
    }

}
