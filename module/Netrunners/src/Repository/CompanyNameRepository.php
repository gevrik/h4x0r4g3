<?php

/**
 * CompanyName Custom Repository.
 * CompanyName Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class CompanyNameRepository extends EntityRepository
{

    /**
     * @return mixed
     */
    public function getRandomCompanyName()
    {
        $qb = $this->createQueryBuilder('cn');
        $qb->select('cn.content');
        $result = $qb->getQuery()->getResult();
        shuffle($result);
        return array_shift($result);
    }

}
