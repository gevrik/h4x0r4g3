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
use Zend\Form\Form;

abstract class TwistyPassagesAbstractEntityService extends TwistyPassagesAbstractService implements TwistyPassagesEntityServiceInterface
{

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $repository;

    /**
     * @var Form
     */
    protected $form;

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

    /**
     * @return Form
     */
    abstract public function getForm();

}
