<?php

/**
 * FileService Factory.
 * Factory for the FileService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\FileExecutionService;
use Netrunners\Service\FileService;
use Netrunners\Service\FileUtilityService;
use Zend\Mvc\I18n\Translator;
use Zend\ServiceManager\Factory\FactoryInterface;

class FileServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return FileService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new FileService(
            $container->get(EntityManager::class),
            $container->get('ViewRenderer'),
            $container->get(Translator::class),
            $container->get(FileUtilityService::class),
            $container->get(FileExecutionService::class)
        );
    }

}
