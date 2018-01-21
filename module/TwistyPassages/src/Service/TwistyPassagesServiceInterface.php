<?php

/**
 * TwistyPassagesService Interface.
 * TwistyPassagesService Interface.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use Doctrine\ORM\EntityManager;

interface TwistyPassagesServiceInterface
{

    /**
     * @return EntityManager
     */
    public function getEntityManager();

}
