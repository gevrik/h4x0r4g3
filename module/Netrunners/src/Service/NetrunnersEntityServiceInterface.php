<?php

/**
 * NetunnersEntityService Interface.
 * NetunnersEntityService Interface.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;


use Doctrine\ORM\QueryBuilder;
use Netrunners\Entity\Profile;

interface NetrunnersEntityServiceInterface
{

    /**
     * @return string
     */
    public function getEntityName();

    /**
     * @return string
     */
    public function getSectionName();

    /**
     * @param QueryBuilder $qb
     * @param string $searchValue
     * @return QueryBuilder
     */
    public function getSearchWhere(QueryBuilder $qb, $searchValue);

    /**
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function initQueryBuilder(QueryBuilder $qb);

    /**
     * @param QueryBuilder $qb
     * @param $columnName
     * @param $dir
     * @return QueryBuilder
     */
    public function addOrderBy(QueryBuilder $qb, $columnName, $dir);

    /**
     * @param string $locale
     * @param int $value
     * @return string
     */
    public function getNumberFormat($locale = 'en-US', $value = 0);

}
