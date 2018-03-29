<?php

/**
 * TP Abstract Service.
 * TP Abstract Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use Doctrine\ORM\EntityManager;

abstract class TwistyPassagesAbstractService
{

    /**
     * @var EntityManager
     */
    protected $entityManager;


    /**
     * TwistyPassagesAbstractService constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        $this->entityManager = $entityManager;
    }

}
