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
use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Mapper\BannedIpMapper;
use Netrunners\Mapper\GeocoordMapper;
use Netrunners\Service\LoginService;
use Netrunners\Service\LoopService;
use Netrunners\Service\ParserService;
use Netrunners\Service\ServerDataService;
use Netrunners\Service\UtilityService;
use Zend\Console\Console;
use Zend\ServiceManager\Factory\FactoryInterface;

class IndexControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return IndexController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new IndexController(
            $container->get(EntityManager::class),
            $container->get(UtilityService::class),
            $container->get(ParserService::class),
            $container->get(LoopService::class),
            $container->get(LoginService::class),
            $container->get(ServerDataService::class),
            $container->get('configuration'),
            $container->get('console')
        );
    }

}
