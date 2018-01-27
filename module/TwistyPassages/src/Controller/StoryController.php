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
        $form = $this->getService()->getForm();
        $viewModel = new ViewModel(['form' => $form]);
        $entity = $this->getService()->getEntity();
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
        return $this->redirect()->toRoute($this->getService()->getRouteName(), ['action' => 'detail', 'id' => $entity->getId()]);
    }

    /**
     * @param array $entities
     * @return array
     */
    protected function populateXhrData($entities)
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

}
