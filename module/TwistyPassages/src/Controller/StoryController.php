<?php

/**
 * Controller for Entity Story.
 * Controller for Entity Story.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Entity\Story;
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

    public function indexAction()
    {
        $this->layout('layout/tp');
        $viewModel = new ViewModel();
        return $viewModel;
    }

    public function welcomeAction()
    {
        $this->layout('layout/tp');
        $topStories = $this->service->getForTopList();
        $viewModel = new ViewModel();
        $viewModel->setVariable('topStories', $topStories);
        return $viewModel;
    }

    public function createAction()
    {
        $this->layout('layout/tp');
        $form = $this->service->getForm();
        $entity = new Story();
        $form->bind($entity);
        $view = new ViewModel();
        $view->setVariable('form', $form);
        return $view;
    }

}
