<?php

/**
 * Story Service.
 * Story Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

class StoryService extends TwistyPassagesAbstractService implements TwistyPassagesServiceInterface
{

    /**
     * StoryService constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        parent::__construct($entityManager);
    }

}
