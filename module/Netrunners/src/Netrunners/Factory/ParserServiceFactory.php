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
            $serviceLocator->get('translator'),
            $serviceLocator->get('Netrunners\Service\FileService'),
            $serviceLocator->get('Netrunners\Service\NodeService'),
            $serviceLocator->get('Netrunners\Service\ChatService'),
            $serviceLocator->get('Netrunners\Service\MailMessageService'),
            $serviceLocator->get('Netrunners\Service\ProfileService'),
            $serviceLocator->get('Netrunners\Service\CodingService'),
            $serviceLocator->get('Netrunners\Service\SystemService'),
            $serviceLocator->get('Netrunners\Service\ConnectionService'),
            $serviceLocator->get('Netrunners\Service\NotificationService'),
            $serviceLocator->get('Netrunners\Service\AdminService'),
            $serviceLocator->get('Netrunners\Service\MilkrunService'),
            $serviceLocator->get('Netrunners\Service\HangmanService'),
            $serviceLocator->get('Netrunners\Service\CodebreakerService'),
            $serviceLocator->get('Netrunners\Service\GameOptionService'),
            $serviceLocator->get('Netrunners\Service\ManpageService'),
            $serviceLocator->get('Netrunners\Service\CombatService'),
            $serviceLocator->get('Netrunners\Service\NpcInstanceService'),
            $serviceLocator->get('Netrunners\Service\FactionService'),
            $serviceLocator->get('Netrunners\Service\ResearchService'),
            $serviceLocator->get('Netrunners\Service\GroupService')
        );
    }

}
