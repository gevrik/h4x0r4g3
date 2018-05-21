<?php

/**
 * Netrunners Abstract Service.
 * Netrunners Abstract Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

abstract class NetrunnersAbstractService extends BaseService implements NetrunnersEntityServiceInterface
{

    /**
     * NetrunnersAbstractService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator, $entityGenerator);
    }

    /**
     * @return string
     */
    public abstract function getEntityName();

    /**
     * @return string
     */
    public abstract function getSectionName();

    /**
     * @param QueryBuilder $qb
     * @param string $searchValue
     * @return QueryBuilder
     */
    public abstract function getSearchWhere(QueryBuilder $qb, $searchValue);

    /**
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public abstract function initQueryBuilder(QueryBuilder $qb);

    /**
     * @param QueryBuilder $qb
     * @param $columnName
     * @param $dir
     * @return QueryBuilder
     */
    public abstract function addOrderBy(QueryBuilder $qb, $columnName, $dir);

}
