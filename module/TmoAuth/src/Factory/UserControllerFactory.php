<?php

/**
 * UserController Factory.
 * UserController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TmoAuth\Factory;

use Interop\Container\ContainerInterface;
use Zend\Mvc\Controller\ControllerManager;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZfcUser\Controller\RedirectCallback;
use ZfcUser\Controller\UserController;

class UserControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $serviceManager
     * @param string $requestedName
     * @param array|null $options
     * @return object|UserController
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $serviceManager, $requestedName, array $options = null)
    {
        /* @var RedirectCallback $redirectCallback */
        $redirectCallback = $serviceManager->get('zfcuser_redirect_callback');

        /* @var UserController $controller */
        $controller = new UserController($redirectCallback);
        $controller->setServiceLocator($serviceManager);

        $controller->setChangeEmailForm($serviceManager->get('zfcuser_change_email_form'));
        $controller->setOptions($serviceManager->get('zfcuser_module_options'));
        $controller->setChangePasswordForm($serviceManager->get('zfcuser_change_password_form'));
        $controller->setLoginForm($serviceManager->get('zfcuser_login_form'));
        $controller->setRegisterForm($serviceManager->get('zfcuser_register_form'));
        $controller->setUserService($serviceManager->get('zfcuser_user_service'));

        return $controller;
    }

    /**
     * @param ServiceLocatorInterface $controllerManager
     * @return object|UserController
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createService(ServiceLocatorInterface $controllerManager)
    {
        /* @var ControllerManager $controllerManager*/
        $serviceManager = $controllerManager->getServiceLocator();

        return $this->__invoke($serviceManager, null);
    }
}
