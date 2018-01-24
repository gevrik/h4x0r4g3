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
    public function getClassName(): string;

    /**
     * @param int $id
     * @return mixed
     */
    public function find(int $id);

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
     */
    public function persist($entity);

    /**
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function flush($entity);

}
