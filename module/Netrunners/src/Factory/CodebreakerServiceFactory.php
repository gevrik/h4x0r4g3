<?php

/**
 * CodebreakerService Factory.
 * Factory for the CodebreakerService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\CodebreakerService;
use Zend\Mvc\I18n\Translator;
use Zend\ServiceManager\Factory\FactoryInterface;

class CodebreakerServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return CodebreakerService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CodebreakerService(
            $container->get(EntityManager::class),
            $container->get('ViewRenderer'),
            $container->get(Translator::class)
        );
    }

}
