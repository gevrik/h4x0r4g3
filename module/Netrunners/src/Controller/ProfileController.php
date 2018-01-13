<?php

/**
 * Controller for Entity Profile.
 * Controller for Entity Profile.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ProfileController extends AbstractActionController
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Netrunners\Service\ProfileService
     */
    protected $profileService;

    /**
     * ProfileController constructor.
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param \Netrunners\Service\ProfileService $profileService
     */
    public function __construct(
        $entityManager,
        $profileService
    )
    {
        $this->entityManager = $entityManager;
        $this->profileService = $profileService;
    }

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        // get user
        $user = $this->zfcUserAuthentication()->getIdentity();
        return new ViewModel(array(
            'user' => $user
        ));
    }

}
