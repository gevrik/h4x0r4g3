<?php

/**
 * ParserService Factory.
 * Factory for the ParserService.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Factory;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Service\EgoCastingService;
use Netrunners\Service\ParserService;
use Zend\Mvc\I18n\Translator;
use Zend\ServiceManager\Factory\FactoryInterface;

class ParserServiceFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ParserService|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ParserService(
            $container->get(EntityManager::class),
            $container->get(Translator::class),
            $container->get('Netrunners\Service\AuctionService'),
            $container->get('Netrunners\Service\FileService'),
            $container->get('Netrunners\Service\NodeService'),
            $container->get('Netrunners\Service\ChatService'),
            $container->get('Netrunners\Service\MailMessageService'),
            $container->get('Netrunners\Service\ProfileService'),
            $container->get('Netrunners\Service\CodingService'),
            $container->get('Netrunners\Service\SystemService'),
            $container->get('Netrunners\Service\ConnectionService'),
            $container->get('Netrunners\Service\NotificationService'),
            $container->get('Netrunners\Service\AdminService'),
            $container->get('Netrunners\Service\MilkrunService'),
            $container->get('Netrunners\Service\MilkrunAivatarService'),
            $container->get('Netrunners\Service\MissionService'),
            $container->get('Netrunners\Service\HangmanService'),
            $container->get('Netrunners\Service\CodebreakerService'),
            $container->get('Netrunners\Service\GameOptionService'),
            $container->get('Netrunners\Service\ManpageService'),
            $container->get('Netrunners\Service\CombatService'),
            $container->get('Netrunners\Service\NpcInstanceService'),
            $container->get('Netrunners\Service\FactionService'),
            $container->get('Netrunners\Service\ResearchService'),
            $container->get('Netrunners\Service\GroupService'),
            $container->get('Netrunners\Service\PartyService'),
            $container->get('Netrunners\Service\StoryService'),
            $container->get('Netrunners\Service\PassageService'),
            $container->get('Netrunners\Service\BountyService'),
            $container->get('Netrunners\Service\ChoiceService'),
            $container->get(EgoCastingService::class)
        );
    }

}
