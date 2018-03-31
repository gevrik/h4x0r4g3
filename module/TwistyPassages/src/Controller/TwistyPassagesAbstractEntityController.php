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
use TwistyPassages\Entity\Story;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

abstract class TwistyPassagesAbstractEntityController extends TwistyPassagesAbstractController
{

    const STRING_DETAIL = 'detail';
    const STRING_INDEX_EDITOR = 'index-editor';

    const SECTION_PASSAGES = 'passages';
    const SECTION_STORIES = 'stories';
    const SECTION_CHOICES = 'choices';

    /**
     * @return string
     */
    abstract public function getSectionname();

    /**
     * @param array $entities
     * @return array
     */
    abstract protected function populateXhrData($entities): array;

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        $viewModel = new ViewModel();
        return $viewModel;
    }

    /**
     * @return ViewModel|\Zend\Http\PhpEnvironment\Response
     */
    public function indexEditorAction()
    {
        $user = $this->getUserIdentity();
        $story = $user->getProfile()->getCurrentEditorStory();
        if (!$story) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        $viewModel = new ViewModel();
        $viewModel->setVariables([
            'story' => $story,
            'section' => $this->getSectionname()
        ]);
        return $viewModel;
    }

    /**
     * @return ViewModel|\Zend\Http\PhpEnvironment\Response
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function detailAction()
    {
        $user = $this->getUserIdentity();
        $id = $this->params()->fromRoute('id');
        $entity = $this->getService()->find($id);
        $story = ($entity instanceof Story) ? $entity : $entity->getStory();
        if ($story->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
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

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     */
    public function createAction()
    {
        $user = $this->getUserIdentity();
        /** @var Request $request */
        $request = $this->getRequest();
        $form = $this->getService()->getForm();
        $story = $user->getProfile()->getCurrentEditorStory();
        $entity = $this->getService()->getEntity();
        if (!$entity instanceof Story && !$story) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
        }
        $viewModel = new ViewModel([
            'form' => $form,
            'story' => $story,
            'section' => $this->getSectionname()
        ]);
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
        return $this->redirect()->toRoute($this->getService()->getRouteName(), ['action' => self::STRING_DETAIL, 'id' => $entity->getId()]);
    }

    /**
     * @return Response|ViewModel
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function deleteAction()
    {
        $user = $this->getUserIdentity();
        /** @var Request $request */
        $entityId = $this->params()->fromRoute('id');
        $confirm = $this->params()->fromQuery('confirm');
        $entity = $this->getService()->find($entityId);
        if (!$entity) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        $story = ($entity instanceof Story) ? $entity : $entity->getStory();
        if ($story->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $viewModel = new ViewModel([
            'entity' => $entity,
            'section' => self::SECTION_PASSAGES,
            'story' => $story
        ]);
        // show form if no post
        if (!$confirm) {
            return $viewModel;
        }
        try {
            $this->getService()->delete($entity);
        }
        catch (\Exception $e) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
        }
        return $this->redirect()->toRoute($this->getService()->getRouteName(), ['action' => self::STRING_INDEX_EDITOR]);
    }

    /**
     * @return Response|ViewModel
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function updateAction()
    {
        $user = $this->getUserIdentity();
        /** @var Request $request */
        $entityId = $this->params()->fromRoute('id');
        $entity = $this->getService()->find($entityId);
        if (!$entity) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        $story = ($entity instanceof Story) ? $entity : $entity->getStory();
        if ($story->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $request = $this->getRequest();
        $form = $this->getService()->getForm();
        $form->bind($entity);
        $viewModel = new ViewModel([
            'form' => $form,
            'entity' => $entity,
            'section' => $this->getSectionname(),
            'story' => $story,
        ]);
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
        try {
            $this->getService()->flush($entity);
        }
        catch (OptimisticLockException $e) {
            throw $e;
        }
        return $this->redirect()->toRoute($this->getService()->getRouteName(), ['action' => self::STRING_DETAIL, 'id' => $entity->getId()]);
    }

}
