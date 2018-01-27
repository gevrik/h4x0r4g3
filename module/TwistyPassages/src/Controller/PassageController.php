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
use TwistyPassages\Entity\Story;
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
     * @return \Zend\Http\Response|ViewModel
     * @throws OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createAction()
    {
        $user = $this->getUserIdentity();
        /** @var Request $request */
        $request = $this->getRequest();
        $form = $this->getService()->getForm();
        $storyId = $this->params()->fromQuery('sid');
        $story = $this->service->findEntity(Story::class, $storyId);
        if (!$story) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_404);
        }
        if ($story->getAuthor() != $user) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->getResponse()->setStatusCode(Response::STATUS_CODE_403);
        }
        $viewModel = new ViewModel([
            'form' => $form,
            'story' => $story,
            'section' => self::ACTION_PASSAGES
        ]);
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
        return $this->redirect()->toRoute($this->getService()->getRouteName(), ['action' => self::ACTION_DETAIL, 'id' => $entity->getId()]);
    }

    /**
     * @param array $entities
     * @return array
     */
    protected function populateXhrData($entities)
    {
        $data = [];
        foreach ($entities as $entity) {
            /** @var Passage $entity */
            $data[] = [
                'id' => $entity->getId(),
                'title' => $entity->getTitle(),
                'description' => $entity->getDescription(),
                'status' => PassageService::$status[$entity->getStatus()],
                'added' => $entity->getAdded()->format('Y/m/d H:i:s'),
                'sub' => ($entity->getAllowChoiceSubmissions()) ? "yes" : "no",
            ];
        }
        return $data;
    }

}
