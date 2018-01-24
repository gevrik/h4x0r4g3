<?php

/**
 * Controller for Entity Passage.
 * Controller for Entity Passage.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Service\PassageService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;

class PassageController extends TwistyPassagesAbstractEntityController
{

    /**
     * @var PassageService
     */
    protected $service;


    /**
     * TwistyPassagesAbstractController constructor.
     * @param PassageService $service
     */
    public function __construct(
        PassageService $service
    )
    {
        $this->service = $service;
    }

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    protected function getService()
    {
        return $this->service;
    }

}
