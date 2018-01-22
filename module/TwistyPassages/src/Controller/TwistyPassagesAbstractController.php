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
use Zend\View\Model\ViewModel;

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
        $this->layout('layout/tp');
        $viewModel = new ViewModel();
        return $viewModel;
    }

}
