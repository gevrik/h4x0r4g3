<?php

/**
 * TwistyPassages Abstract Controller.
 * TwistyPassages Abstract Controller.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Service\TwistyPassagesServiceInterface;
use Zend\Mvc\Controller\AbstractActionController;

class TwistyPassagesAbstractController extends AbstractActionController
{

    /**
     * @var TwistyPassagesServiceInterface
     */
    protected $service;

    /**
     * TwistyPassagesAbstractController constructor.
     * @param $service
     */
    public function __construct(
        $service
    )
    {
        $this->service = $service;
    }

    public function indexAction()
    {

    }

}
