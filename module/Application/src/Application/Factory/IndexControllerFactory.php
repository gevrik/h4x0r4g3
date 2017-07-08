<?php

/**
 * IndexController Factory.
 * IndexController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Application\Factory;

use Application\Controller\IndexController;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class IndexControllerFactory implements FactoryInterface
{

    /**
     * Create service.
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $realServiceLocator = $serviceLocator->getServiceLocator();
        $entityManager = $realServiceLocator->get('Doctrine\ORM\EntityManager');
        $utilityService = $realServiceLocator->get('Netrunners\Service\UtilityService');
        $parserService = $realServiceLocator->get('Netrunners\Service\ParserService');
        $loopService = $realServiceLocator->get('Netrunners\Service\LoopService');
        $nodeService = $realServiceLocator->get('Netrunners\Service\NodeService');
        $loginService = $realServiceLocator->get('Netrunners\Service\LoginService');
        $manpageService = $realServiceLocator->get('Netrunners\Service\ManpageService');

        return new IndexController(
            $entityManager,
            $utilityService,
            $parserService,
            $loopService,
            $nodeService,
            $loginService,
            $manpageService
        );
    }

}
