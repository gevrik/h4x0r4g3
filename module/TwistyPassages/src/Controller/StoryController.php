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
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;
use Zend\Http\Request;
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
     * @return ViewModel
     */
    public function welcomeAction(): ViewModel
    {
        $topStories = $this->service->getForTopList();
        $viewModel = new ViewModel();
        $viewModel->setVariable('topStories', $topStories);
        return $viewModel;
    }

}
