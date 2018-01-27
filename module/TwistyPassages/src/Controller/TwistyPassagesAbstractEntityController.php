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
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

abstract class TwistyPassagesAbstractEntityController extends TwistyPassagesAbstractController
{

    const ACTION_DETAIL = 'detail';
    const ACTION_PASSAGES = 'passages';

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     */
    abstract public function createAction();

    /**
     * @param array $entities
     * @return array
     */
    abstract protected function populateXhrData($entities);

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
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function detailAction(): ViewModel
    {
        $id = $this->params()->fromRoute('id');
        $entity = $this->getService()->find($id);
        $viewModel = new ViewModel();
        $viewModel->setVariables([
            'story' => $entity,
            'entity' => $entity,
            'section' => self::ACTION_DETAIL
        ]);
        return $viewModel;
    }

    /**
     * @return JsonModel
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function xhrDataAction()
    {
        $draw = $this->params()->fromQuery('draw');
        $start = $this->params()->fromQuery('start');
        $length = $this->params()->fromQuery('length');
        $search = $this->params()->fromQuery('search');
        $columns = $this->params()->fromQuery('columns');
        $order = $this->params()->fromQuery('order');
        $searchValue = $search['value'];
        $entities = $this->getService()->getEntities($start, $length, $columns, $order, $searchValue);
        $data = [];
        $data['draw'] = (int)$draw;
        $recordsTotal = (int)$this->getService()->countAll();
        $data['recordsTotal'] = $recordsTotal;
        $data['recordsFiltered'] = $recordsTotal;
        $data['data'] = $this->populateXhrData($entities);
        $jsonModel = new JsonModel($data);
        return $jsonModel;
    }

}
