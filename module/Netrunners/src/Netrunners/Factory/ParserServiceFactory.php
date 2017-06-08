<?php

/**
 * ParserService Factory.
 * Factory for the ParserService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Netrunners\Service\ParserService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ParserServiceFactory implements FactoryInterface
{

    /**
     * Create service.
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return new ParserService(
            $serviceLocator->get('Doctrine\ORM\EntityManager'),
            $serviceLocator->get('Netrunners\Service\FileService'),
            $serviceLocator->get('Netrunners\Service\ChatService'),
            $serviceLocator->get('Netrunners\Service\MailMessageService'),
            $serviceLocator->get('Netrunners\Service\ProfileService'),
            $serviceLocator->get('Netrunners\Service\CodingService'),
            $serviceLocator->get('Netrunners\Service\SystemService')
        );
    }

}
