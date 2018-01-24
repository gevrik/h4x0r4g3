<?php

/**
 * TwistyPassages Abstract Entity Controller.
 * TwistyPassages Abstract Entity Controller.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use Doctrine\ORM\OptimisticLockException;
use Zend\Http\Request;
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

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     */
    public function createAction()
    {
        $user = $this->getUserIdentity();
        /** @var Request $request */
        $request = $this->getRequest();
        $form = $this->getService()->getForm();
        $viewModel = new ViewModel(['form' => $form]);
        $entity = $this->getService()->getEntity();
        $form->bind($entity);
        // show form if no post
        if (!$request->isPost()) {
            return $viewModel;
        }
        // set form data from post
        $form->setData($request->getPost());
        // if form is not valid show form again
        if (!$form->isValid()) {
            return $viewModel;
        }
        $this->getService()->persist($entity);
        try {
            $this->getService()->flush($entity);
        }
        catch (OptimisticLockException $e) {
            throw $e;
        }
        return $this->redirect()->toRoute('story', ['action' => 'detail', 'id' => $entity->getId()]);
    }

}
