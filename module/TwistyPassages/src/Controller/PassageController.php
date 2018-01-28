<?php

/**
 * Controller for Entity Passage.
 * Controller for Entity Passage.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use Doctrine\ORM\OptimisticLockException;
use TwistyPassages\Entity\Passage;
use TwistyPassages\Filter\StringLengthFilter;
use TwistyPassages\Service\PassageService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\View\Model\ViewModel;

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

    /**
     * @return ViewModel
     */
    public function indexEditorAction(): ViewModel
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
            'section' => self::SECTION_PASSAGES
        ]);
        return $viewModel;
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
        $form = $this->service->getForm();
        $story = $user->getProfile()->getCurrentEditorStory();
        if (!$story) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        if ($story->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $viewModel = new ViewModel([
            'form' => $form,
            'story' => $story,
            'section' => self::SECTION_PASSAGES
        ]);
        $entity = $this->service->getEntity();
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
        $this->service->persist($entity);
        try {
            $this->service->flush($entity);
        }
        catch (OptimisticLockException $e) {
            throw $e;
        }
        return $this->redirect()->toRoute($this->service->getRouteName(), ['action' => self::STRING_DETAIL, 'id' => $entity->getId()]);
    }

    /**
     * @param array $entities
     * @return array
     */
    protected function populateXhrData($entities): array
    {
        $data = [];
        foreach ($entities as $entity) {
            /** @var Passage $entity */
            $description = $entity->getDescription();
            $filter = new StringLengthFilter();
            $description = $filter->filter($description);
            $data[] = [
                'actions' => $this->service->getActionButtonsDefinitions($entity->getId()),
                'title' => $entity->getTitle(),
                'description' => $description,
                'status' => PassageService::$status[$entity->getStatus()],
                'added' => $entity->getAdded()->format('Y/m/d H:i:s'),
                'sub' => ($entity->getAllowChoiceSubmissions()) ? "yes" : "no",
            ];
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getSectionname(): string
    {
        return "passages";
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
        $entity = $this->service->find($entityId);
        if (!$entity) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        if ($entity->getStory()->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $request = $this->getRequest();
        $form = $this->service->getForm();
        $form->bind($entity);
        $viewModel = new ViewModel([
            'form' => $form,
            'entity' => $entity,
            'section' => self::SECTION_PASSAGES,
            'story' => $entity->getStory()
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
            $this->service->flush($entity);
        }
        catch (OptimisticLockException $e) {
            throw $e;
        }
        return $this->redirect()->toRoute($this->service->getRouteName(), ['action' => self::STRING_DETAIL, 'id' => $entity->getId()]);
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
        $entity = $this->service->find($entityId);
        if (!$entity) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        if ($entity->getStory()->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $viewModel = new ViewModel([
            'entity' => $entity,
            'section' => self::SECTION_PASSAGES,
            'story' => $entity->getStory()
        ]);
        // show form if no post
        if (!$confirm) {
            return $viewModel;
        }
        try {
            $this->service->delete($entity);
        }
        catch (\Exception $e) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_400);
        }
        return $this->redirect()->toRoute($this->service->getRouteName(), ['action' => self::STRING_INDEX_EDITOR]);
    }

}
