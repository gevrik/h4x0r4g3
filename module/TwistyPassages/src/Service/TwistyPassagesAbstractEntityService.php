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

abstract class TwistyPassagesAbstractEntityService extends TwistyPassagesAbstractService
{

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $repository;


    /**
     * TwistyPassagesAbstractEntityService constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        parent::__construct($entityManager);
        $this->repository = $this->entityManager->getRepository($this->getClassName());
    }

    /**
     * @return string
     */
    abstract public function getClassName(): string;

}
