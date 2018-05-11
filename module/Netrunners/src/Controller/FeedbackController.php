<?php

/**
 * Controller for Entity Feedback.
 * Controller for Entity Feedback.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Controller;

use Netrunners\Entity\Feedback;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

final class FeedbackController extends AbstractActionController
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * ProfileController constructor.
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct(
        $entityManager
    )
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        $this->layout('layout/web');
        // get user
        $user = $this->zfcUserAuthentication()->getIdentity();
        // get all feedbacks
        $feedbacks = $this->entityManager->getRepository('Netrunners\Entity\Feedback')->findAll();
        return new ViewModel(array(
            'feedbacks' => $feedbacks
        ));
    }

    /**
     * @return JsonModel
     */
    public function xhrDataAction()
    {
        $draw = $this->params()->fromQuery('draw');
        $start = $this->params()->fromQuery('start');
        $length = $this->params()->fromQuery('length');
        $qb = $this->entityManager->createQueryBuilder();
        $qb->from('Netrunners\Entity\Feedback', 'f');
        $qb->select('f');
        $qb->setFirstResult($start);
        $qb->setMaxResults($length);
        $feedbacks = $qb->getQuery()->getResult();
        $feedbackData = [];
        $feedbackData['draw'] = $draw;
        $feedbackData['data'] = [];
        $feedbackData['recordsTotal'] = count($feedbacks);
        $feedbackData['recordsFiltered'] = count($feedbacks);
        foreach ($feedbacks as $feedback) {
            /** @var Feedback $feedback */
            $feedbackData['data'][] = [
                'id' => $feedback->getId(),
                'type' => Feedback::$lookup[$feedback->getType()],
                'added' => $feedback->getAdded()->format('d/m/y H:i:s'),
                'profile' => $feedback->getProfile()->getUser()->getUsername(),
                'subject' => $feedback->getSubject()
            ];
        }
        $jsonModel = new JsonModel($feedbackData);
        return $jsonModel;
    }

}
