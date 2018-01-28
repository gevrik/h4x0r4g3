<?php

/**
 * TWisty Passages Abstract Entity Service.
 * TWisty Passages Abstract Entity Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use TwistyPassages\Entity\Story;
use Zend\Form\Form;

abstract class TwistyPassagesAbstractEntityService extends TwistyPassagesAbstractService implements TwistyPassagesEntityServiceInterface
{

    const ROUTE = 'tp';

    /**
     * @var Form
     */
    protected $form;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;


    /**
     * TwistyPassagesAbstractEntityService constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager);
        $this->queryBuilder = $this->entityManager->getRepository($this->getClassName())->createQueryBuilder('e');
    }

    /**
     * @return string
     */
    abstract public function getClassName(): string;

    /**
     * @return Form
     */
    abstract public function getForm(): Form;

    /**
     * @param string $searchValue
     * @return TwistyPassagesEntityServiceInterface
     */
    abstract public function getSearchWhere($searchValue): TwistyPassagesEntityServiceInterface;

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    abstract public function initQueryBuilder(): TwistyPassagesEntityServiceInterface;

    /**
     * @param $columnName
     * @param $dir
     * @return TwistyPassagesEntityServiceInterface
     */
    abstract public function addOrderBy($columnName, $dir): TwistyPassagesEntityServiceInterface;

    /**
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    abstract public function delete($entity);

    /**
     * @param $entity
     */
    public function persist($entity)
    {
        $this->entityManager->persist($entity);
    }

    /**
     * @param mixed $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function flush($entity = null)
    {
        if ($entity) {
            $this->entityManager->flush($entity);
        }
        else {
            $this->entityManager->flush();
        }
    }

    /**
     * @param int $id
     * @return mixed|null|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function find(int $id)
    {
        return $this->entityManager->find($this->getClassName(), $id);
    }

    /**
     * @param string $class
     * @param int $id
     * @return null|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findEntity(string $class, int $id)
    {
        return $this->entityManager->find($class, $id);
    }

    /**
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAll()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select($qb->expr()->count('e.id'));
        $qb->from($this->getClassName(), 'e');
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return string
     */
    public function getRouteName(): string
    {
        return $this::ROUTE;
    }

    /**
     * @param int $start
     * @param int $length
     * @param array $columns
     * @param array $order
     * @param string $searchValue
     * @param int|null $storyId
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function getEntities(int $start, int $length, array $columns, array $order, string $searchValue = "", int $storyId = null): array
    {
        $this->initQueryBuilder();
        $this->queryBuilder->select('e');
        if ($storyId) {
            $story = $this->findEntity(Story::class, $storyId);
            if ($story) {
                $this->queryBuilder->where('e.story = :story');
                $this->queryBuilder->setParameter('story', $story);
            }
        }
        if (!empty($searchValue)) {
            $this->getSearchWhere($searchValue);
        }
        foreach ($order as $orderData) {
            $column = $orderData['column'];
            $columnName = $columns[$column]['data'];
            if ($columnName == 'actions') continue;
            $dir = $orderData['dir'];
            $this->addOrderBy($columnName, $dir);
        }
        $this->queryBuilder->setFirstResult($start);
        $this->queryBuilder->setMaxResults($length);
        $entities = $this->queryBuilder->getQuery()->getResult();
        return $entities;
    }

    /**
     * @param int $entityId
     * @return array
     */
    protected function getDefaultActionButtons(int $entityId)
    {
        return [
            ['route' => $this->getRouteName(), 'action' => 'detail', 'id' => $entityId, 'icon' => 'fa-info', 'class' => 'btn-primary'],
            ['route' => $this->getRouteName(), 'action' => 'update', 'id' => $entityId, 'icon' => 'fa-pencil', 'class' => 'btn-primary'],
            ['route' => $this->getRouteName(), 'action' => 'delete', 'id' => $entityId, 'icon' => 'fa-trash', 'class' => 'btn-danger'],
        ];
    }

}
