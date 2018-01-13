<?php

/**
 * FileExecutionService Factory.
 * FileExecutionService Factory.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\CodebreakerService;
use Netrunners\Service\FileExecutionService;
use Netrunners\Service\HangmanService;
use Netrunners\Service\MissionService;
use Zend\Mvc\I18n\Translator;
use Zend\ServiceManager\Factory\FactoryInterface;

class FileExecutionServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return FileExecutionService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new FileExecutionService(
            $container->get(EntityManager::class),
            $container->get('ViewRenderer'),
            $container->get(Translator::class),
            $container->get(CodebreakerService::class),
            $container->get(MissionService::class),
            $container->get(HangmanService::class)
        );
    }

}
