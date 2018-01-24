<?php

/**
 * TwistyPassages Abstract Entity Controller.
 * TwistyPassages Abstract Entity Controller.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use Zend\View\Model\ViewModel;

abstract class TwistyPassagesAbstractEntityController extends TwistyPassagesAbstractController
{

    /**
     * @return ViewModel
     */
    public function indexAction(): ViewModel
    {
        $viewModel = new ViewModel();
        return $viewModel;
    }

    /**
     * @return ViewModel
     */
    public function detailAction(): ViewModel
    {
        $id = $this->params()->fromRoute('id');
        $entity = $this->getService()->find($id);
        $viewModel = new ViewModel();
        $viewModel->setVariable('entity', $entity);
        return $viewModel;
    }

}
