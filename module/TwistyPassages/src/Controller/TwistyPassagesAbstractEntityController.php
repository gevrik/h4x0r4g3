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

    const STRING_DETAIL = 'detail';

    const SECTION_PASSAGES = 'passages';

    /**
     * @return ViewModel
     */
    abstract public function indexEditorAction(): ViewModel;

    /**
     * @return string
     */
    abstract public function getSectionname(): string;

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     */
    abstract public function createAction();

    /**
     * @param array $entities
     * @return array
     */
    abstract protected function populateXhrData($entities): array;

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
        $user = $this->getUserIdentity();
        $id = $this->params()->fromRoute('id');
        $entity = $this->getService()->find($id);
        $story = $user->getProfile()->getCurrentEditorStory();
        $viewModel = new ViewModel();
        $viewModel->setVariables([
            'story' => $story,
            'entity' => $entity,
            'section' => $this->getSectionName()
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
        $storyId = $this->params()->fromRoute('id');
        $draw = $this->params()->fromQuery('draw');
        $start = $this->params()->fromQuery('start');
        $length = $this->params()->fromQuery('length');
        $search = $this->params()->fromQuery('search');
        $columns = $this->params()->fromQuery('columns');
        $order = $this->params()->fromQuery('order');
        $searchValue = $search['value'];
        $entities = $this->getService()->getEntities($start, $length, $columns, $order, $searchValue, $storyId);
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
