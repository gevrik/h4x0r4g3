<?php

/**
 * MailMessageService Factory.
 * Factory for the MailMessageService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\MailMessageService;
use Zend\Mvc\I18n\Translator;
use Zend\ServiceManager\Factory\FactoryInterface;

class MailMessageServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return MailMessageService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new MailMessageService(
            $container->get(EntityManager::class),
            $container->get('ViewRenderer'),
            $container->get(Translator::class)
        );
    }

}
