<?php

/**
 * TwistyPassages Abstract Controller.
 * TwistyPassages Abstract Controller.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class TwistyPassagesAbstractController extends AbstractActionController
{

    /**
     * Override the parent's onDispatch() method.
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        $response = parent::onDispatch($e);
        $this->layout()->setTemplate('layout/tp');
        return $response;
    }

    /**
     * @return ViewModel
     */
    public function indexAction(): ViewModel
    {
        $viewModel = new ViewModel();
        return $viewModel;
    }

}
