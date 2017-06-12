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

    public function findForCoding()
    {
        $qb = $this->createQueryBuilder('ft');
        return $qb->getQuery()->getResult();
    }

}
