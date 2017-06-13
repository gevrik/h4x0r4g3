<?php

/**
 * Base Service.
 * The service supplies a base for all complex services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;

class BaseService
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    protected $viewRenderer;

    /**
     * BaseService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer
    )
    {
        $this->entityManager = $entityManager;
        $this->viewRenderer = $viewRenderer;
    }

}
