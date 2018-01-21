<?php

/**
 * Netrunners Abstract Controller.
 * Netrunners Abstract Controller.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Controller;

use Netrunners\Entity\Profile;
use Netrunners\Repository\NetrunnersRepoInterface;
use Netrunners\Service\NetrunnersEntityServiceInterface;
use TmoAuth\Entity\User;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

abstract class NetrunnersAbstractController extends AbstractActionController
{

    /**
     * @const LAYOUT_NAME
     */
    const LAYOUT_NAME = 'layout/web';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var NetrunnersEntityServiceInterface
     */
    protected $entityService;

    /**
     * @var NetrunnersRepoInterface
     */
    protected $entityRepo;

    /**
     * @var AuthenticationService|NULL
     */
    protected $auth;

    /**
     * @var User|NULL
     */
    protected $user;

    /**
     * @var Profile|NULL
     */
    protected $profile;


    /**
     * NetrunnersAbstractController constructor.
     * @param $entityManager
     * @param $entityService
     */
    public function __construct(
        $entityManager,
        $entityService
    )
    {
        $this->entityManager = $entityManager;
        $this->entityService = $entityService;
        $this->entityRepo = $this->entityManager->getRepository($this->entityService->getEntityName());
        $this->auth = NULL;
        $this->user = NULL;
        $this->profile = NULL;
    }

    /**
     *
     */
    protected function init()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->auth = $this->zfcUserAuthentication();
        $this->user = ($this->auth->hasIdentity()) ? $this->auth->getIdentity() : NULL;
        $this->profile = ($this->user) ? $this->user->getProfile() : NULL;
    }

    /**
     * @param array $entities
     * @return array
     */
    protected abstract function populateXhrData($entities);

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        $this->init();
        $this->layout(self::LAYOUT_NAME);
        return new ViewModel();
    }

    /**
     * @return JsonModel
     */
    public function xhrDataAction()
    {
        $draw = $this->params()->fromQuery('draw');
        $start = $this->params()->fromQuery('start');
        $length = $this->params()->fromQuery('length');
        $search = $this->params()->fromQuery('search');
        $columns = $this->params()->fromQuery('columns');
        $order = $this->params()->fromQuery('order');
        $searchValue = $search['value'];
        $qb = $this->entityManager->createQueryBuilder();
        $qb->from($this->entityService->getEntityName(), 'e');
        $qb = $this->entityService->initQueryBuilder($qb);
        $qb->select('e');
        if (!empty($searchValue)) {
            $qb = $this->entityService->getSearchWhere($qb, $searchValue);
        }
        foreach ($order as $orderData) {
            $column = $orderData['column'];
            $columnName = $columns[$column]['data'];
            $dir = $orderData['dir'];
            $this->entityService->addOrderBy($qb, $columnName, $dir);
        }
        $qb->setFirstResult($start);
        $qb->setMaxResults($length);
        $entities = $qb->getQuery()->getResult();
        $data = [];
        $data['draw'] = (int)$draw;
        $recordsTotal = (int)$this->entityRepo->countAll();
        $data['recordsTotal'] = $recordsTotal;
        $data['recordsFiltered'] = $recordsTotal;
        $data['data'] = $this->populateXhrData($entities);
        $jsonModel = new JsonModel($data);
        return $jsonModel;
    }

}
