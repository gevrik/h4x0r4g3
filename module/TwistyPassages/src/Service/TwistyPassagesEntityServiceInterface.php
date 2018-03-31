<?php

/**
 * TWisty Passages Entity Service Interface.
 * TWisty Passages Entity Service Interface.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use Zend\Form\Form;

interface TwistyPassagesEntityServiceInterface
{

    /**
     * @return string
     */
    public function getClassName();

    /**
     * @return string
     */
    public function getRouteName();

    /**
     * @return Form
     */
    public function getForm();

    /**
     * @return mixed
     */
    public function getEntity();

    /**
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function delete($entity);

    /**
     * @param $entity
     */
    public function persist($entity);

    /**
     * @param mixed $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function flush($entity = null);

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    public function initQueryBuilder();

    /**
     * @param string $searchValue
     * @return TwistyPassagesEntityServiceInterface
     */
    public function getSearchWhere($searchValue);

    /**
     * @param $columnName
     * @param $dir
     * @return TwistyPassagesEntityServiceInterface
     */
    public function addOrderBy($columnName, $dir);

    /**
     * @param int $id
     * @return mixed|null|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function find(int $id);

    /**
     * @param string $class
     * @param int $id
     * @return null|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findEntity(string $class, int $id);

    /**
     * @param int $start
     * @param int $length
     * @param array $columns
     * @param array $order
     * @param string $searchValue
     * @param int|null $storyId
     * @return array
     */
    public function getEntities(int $start, int $length, array $columns, array $order, string $searchValue = "", int $storyId = null);

    /**
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAll();

}
