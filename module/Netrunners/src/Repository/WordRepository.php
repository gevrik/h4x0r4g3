<?php

/**
 * Word Custom Repository.
 * Word Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;

class WordRepository extends EntityRepository
{

    /**
     * @param int $amount
     * @param int $length
     * @return array
     */
    public function getRandomWordsByLength($amount = 1, $length = 5)
    {
        return $this->getRandomWordByLengthNativeQuery($amount, $length)->getResult();
    }

    /**
     * @param int $amount
     * @param int $length
     * @return \Doctrine\ORM\NativeQuery
     */
    public function getRandomWordByLengthNativeQuery($amount = 1, $length = 5)
    {
        $table = $this->getClassMetadata()->getTableName();
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult($this->getEntityName(), 'w');
        $rsm->addFieldResult('w', 'id', 'id');
        $rsm->addFieldResult('w', 'content', 'content');
        return $this->getEntityManager()->createNativeQuery("
            SELECT w.id, w.content FROM {$table} w WHERE w.length = {$length} ORDER BY RAND() LIMIT 0, {$amount}
        ", $rsm);
    }

}
