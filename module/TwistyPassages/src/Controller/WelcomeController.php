<?php

/**
 * Controller for the Welcome Page.
 * Controller for the Welcome Page.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Service\WelcomeService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class WelcomeController extends AbstractActionController
{

    /**
     * @var WelcomeService
     */
    protected $service;

    /**
     * WelcomeController constructor.
     * @param WelcomeService $welcomeService
     */
    public function __construct(
        WelcomeService $welcomeService
    )
    {
        $this->service = $welcomeService;
    }

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
    public function detailStoryAction()
    {
        $id = $this->params()->fromRoute('id');
        $entity = $this->service->findStory($id);
        $viewModel = new ViewModel();
        $viewModel->setVariable('entity', $entity);
        return $viewModel;
    }

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        $topStories = $this->service->getForTopList();
        $viewModel = new ViewModel();
        $viewModel->setVariable('topStories', $topStories);
        return $viewModel;
    }

}
