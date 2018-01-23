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
use TwistyPassages\Service\StoryService;
use Zend\View\Model\ViewModel;

class StoryController extends TwistyPassagesAbstractController
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
     * @return ViewModel
     */
    public function welcomeAction(): ViewModel
    {
        $topStories = $this->service->getForTopList();
        $viewModel = new ViewModel();
        $viewModel->setVariable('topStories', $topStories);
        return $viewModel;
    }

    /**
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     */
    public function createAction()
    {
        $request   = $this->getRequest();
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
        } catch (OptimisticLockException $e) {
            throw $e;
        }
        return $this->redirect()->toRoute('story/detail', ['id' => $entity->getId()]);
    }

}
