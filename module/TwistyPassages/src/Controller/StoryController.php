<?php

/**
 * Controller for Entity Story.
 * Controller for Entity Story.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use Doctrine\ORM\OptimisticLockException;
use TwistyPassages\Entity\Story;
use TwistyPassages\Service\StoryService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\View\Model\ViewModel;

class StoryController extends TwistyPassagesAbstractEntityController
{

    /**
     * @var StoryService
     */
    protected $service;


    /**
     * TwistyPassagesAbstractController constructor.
     * @param StoryService $service
     */
    public function __construct(
        StoryService $service
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
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     */
    public function createAction()
    {
        /** @var Request $request */
        $request = $this->getRequest();
        $form = $this->service->getForm();
        $viewModel = new ViewModel(['form' => $form]);
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
     * @return Response|ViewModel
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function updateAction()
    {
        $user = $this->getUserIdentity();
        /** @var Request $request */
        $id = $this->params()->fromRoute('id');
        $entity = $this->service->find($id);
        if (!$entity) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        if ($entity->getAuthor() !== $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $request = $this->getRequest();
        $form = $this->service->getForm();
        $form->bind($entity);
        $viewModel = new ViewModel(['form' => $form, 'entity' => $entity]);
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
     * @param array $entities
     * @return array
     */
    protected function populateXhrData($entities): array
    {
        $data = [];
        foreach ($entities as $entity) {
            /** @var Story $entity */
            $data[] = [
                'id' => $entity->getId(),
                'title' => $entity->getTitle()
            ];
        }
        return $data;
    }

    /**
     * @return ViewModel
     */
    public function indexEditorAction(): ViewModel
    {
        return new ViewModel();
    }

    /**
     * @return string
     */
    public function getSectionname(): string
    {
        return "stories";
    }

}
