<?php

/**
 * LoopService Factory.
 * Factory for the LoopService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\CodingService;
use Netrunners\Service\CombatService;
use Netrunners\Service\EntityGenerator;
use Netrunners\Service\FileService;
use Netrunners\Service\LoopService;
use Netrunners\Service\MainCampaignService;
use Netrunners\Service\SystemService;
use Zend\Mvc\I18n\Translator;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoopServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return LoopService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new LoopService(
            $container->get(EntityManager::class),
            $container->get('ViewRenderer'),
            $container->get(FileService::class),
            $container->get(CodingService::class),
            $container->get(CombatService::class),
            $container->get(SystemService::class),
            $container->get(MainCampaignService::class),
            $container->get(Translator::class),
            $container->get(EntityGenerator::class)
        );
    }

}
