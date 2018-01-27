<?php

/**
 * Controller for Story Editor.
 * Controller for Story Editor.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Service\StoryService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;
use Zend\Http\Response;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class StoryEditorController extends TwistyPassagesAbstractController
{

    /**
     * @var StoryService
     */
    protected $service;


    /**
     * StoryEditorController constructor.
     * @param StoryService $service
     */
    public function __construct(
        StoryService $service
    )
    {
        $this->service = $service;
    }

    /**
     * Override the parent's onDispatch() method.
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        $response = parent::onDispatch($e);
        $this->layout()->setTemplate('layout/tp-editor');
        return $response;
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
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function indexAction()
    {
        $user = $this->getUserIdentity();
        $id = $this->params()->fromRoute('id');
        $story = $this->getService()->find($id);
        if (!$story) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        if ($user != $story->getAuthor()) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $profile = $user->getProfile();
        $profile->setCurrentEditorStory($story);
        $this->getService()->flush($profile);
        $viewModel = new ViewModel();
        $viewModel->setVariables([
            'story' => $story,
            'section' => 'overview'
        ]);
        return $viewModel;
    }

}
