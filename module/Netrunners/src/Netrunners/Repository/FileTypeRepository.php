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

    public function findForCoding()
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->where('ft.codable > 0');
        return $qb->getQuery()->getResult();
    }

}
