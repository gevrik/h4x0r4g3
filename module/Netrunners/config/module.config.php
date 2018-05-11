<?php

namespace Netrunners;

use Netrunners\Factory\BookmarkServiceFactory;
use Netrunners\Factory\ChoiceServiceFactory;
use Netrunners\Factory\EgoCastingServiceFactory;
use Netrunners\Service\BookmarkService;
use Netrunners\Service\ChoiceService;
use Netrunners\Service\EgoCastingService;
use Netrunners\View\Helper\ImageUrlHelper;
use Netrunners\View\Helper\MailMasterHelper;
use Netrunners\View\Helper\ProfileGroupHelper;

return [
    'router' => [
        'routes' => [
            'profile' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/profile[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Netrunners\Controller',
                        'controller'    => 'Netrunners\Controller\Profile',
                        'action'        => 'profile',
                    ],
                ],
            ],
            'system' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/system[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Netrunners\Controller',
                        'controller'    => 'Netrunners\Controller\System',
                        'action'        => 'profileIndex',
                    ],
                ],
            ],
            'feedback' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/feedback[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Netrunners\Controller',
                        'controller'    => 'Netrunners\Controller\Feedback',
                        'action'        => 'index',
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'invokables' => [
        ],
        'factories' => [
            'Netrunners\Controller\Profile' => 'Netrunners\Factory\ProfileControllerFactory',
            'Netrunners\Controller\System' => 'Netrunners\Factory\SystemControllerFactory',
            'Netrunners\Controller\Feedback' => 'Netrunners\Factory\FeedbackControllerFactory',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'translator' => 'Zend\Mvc\Service\TranslatorServiceFactory',
            'Netrunners\Service\BaseUtilityService' => 'Netrunners\Factory\BaseUtilityServiceFactory',
            'Netrunners\Service\BaseService' => 'Netrunners\Factory\BaseServiceFactory',
            'Netrunners\Service\AuctionService' => 'Netrunners\Factory\AuctionServiceFactory',
            'Netrunners\Service\AdminService' => 'Netrunners\Factory\AdminServiceFactory',
            BookmarkService::class => BookmarkServiceFactory::class,
            'Netrunners\Service\BountyService' => 'Netrunners\Factory\BountyServiceFactory',
            'Netrunners\Service\ChatService' => 'Netrunners\Factory\ChatServiceFactory',
            ChoiceService::class => ChoiceServiceFactory::class,
            'Netrunners\Service\CodingService' => 'Netrunners\Factory\CodingServiceFactory',
            'Netrunners\Service\CodebreakerService' => 'Netrunners\Factory\CodebreakerServiceFactory',
            'Netrunners\Service\CombatService' => 'Netrunners\Factory\CombatServiceFactory',
            EgoCastingService::class => EgoCastingServiceFactory::class,
            'Netrunners\Service\FactionService' => 'Netrunners\Factory\FactionServiceFactory',
            'Netrunners\Service\FileService' => 'Netrunners\Factory\FileServiceFactory',
            'Netrunners\Service\FileExecutionService' => 'Netrunners\Factory\FileExecutionServiceFactory',
            'Netrunners\Service\FileUtilityService' => 'Netrunners\Factory\FileUtilityServiceFactory',
            'Netrunners\Service\ConnectionService' => 'Netrunners\Factory\ConnectionServiceFactory',
            'Netrunners\Service\GameOptionService' => 'Netrunners\Factory\GameOptionServiceFactory',
            'Netrunners\Service\GroupService' => 'Netrunners\Factory\GroupServiceFactory',
            'Netrunners\Service\HangmanService' => 'Netrunners\Factory\HangmanServiceFactory',
            'Netrunners\Service\LoginService' => 'Netrunners\Factory\LoginServiceFactory',
            'Netrunners\Service\LoopService' => 'Netrunners\Factory\LoopServiceFactory',
            'Netrunners\Service\ManpageService' => 'Netrunners\Factory\ManpageServiceFactory',
            'Netrunners\Service\MissionService' => 'Netrunners\Factory\MissionServiceFactory',
            'Netrunners\Service\NodeService' => 'Netrunners\Factory\NodeServiceFactory',
            'Netrunners\Service\NotificationService' => 'Netrunners\Factory\NotificationServiceFactory',
            'Netrunners\Service\NpcInstanceService' => 'Netrunners\Factory\NpcInstanceServiceFactory',
            'Netrunners\Service\MailMessageService' => 'Netrunners\Factory\MailMessageServiceFactory',
            'Netrunners\Service\MainCampaignService' => 'Netrunners\Factory\MainCampaignServiceFactory',
            'Netrunners\Service\MilkrunService' => 'Netrunners\Factory\MilkrunServiceFactory',
            'Netrunners\Service\MilkrunAivatarService' => 'Netrunners\Factory\MilkrunAivatarServiceFactory',
            'Netrunners\Service\PartyService' => 'Netrunners\Factory\PartyServiceFactory',
            'Netrunners\Service\ParserService' => 'Netrunners\Factory\ParserServiceFactory',
            'Netrunners\Service\PassageService' => 'Netrunners\Factory\PassageServiceFactory',
            'Netrunners\Service\ProfileService' => 'Netrunners\Factory\ProfileServiceFactory',
            'Netrunners\Service\ResearchService' => 'Netrunners\Factory\ResearchServiceFactory',
            'Netrunners\Service\StoryService' => 'Netrunners\Factory\StoryServiceFactory',
            'Netrunners\Service\SystemService' => 'Netrunners\Factory\SystemServiceFactory',
            'Netrunners\Service\SystemGeneratorService' => 'Netrunners\Factory\SystemGeneratorServiceFactory',
            'Netrunners\Service\UtilityService' => 'Netrunners\Factory\UtilityServiceFactory',
        ],
    ],
    'translator' => [
        'locale' => 'en_US',
        'translation_file_patterns' => [
            [
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
            ],
        ],
    ],
    'view_helpers' => [
        'invokables'=> [
        ],
        'factories' => [
            ImageUrlHelper::class => 'Netrunners\Factory\ImageUrlHelperFactory',
            MailMasterHelper::class => 'Netrunners\Factory\MailMasterHelperFactory',
            ProfileGroupHelper::class => 'Netrunners\Factory\ProfileGroupHelperFactory',
        ],
        'aliases' => [
            'imageUrlHelper' => ImageUrlHelper::class,
            'mailMasterHelper' => MailMasterHelper::class,
            'profileGroupHelper' => ProfileGroupHelper::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'navigation' => [
        'default' => [
            [
                'label' => _('Profiles'),
                'route' => 'profile',
                'pages' => [
                    [
                        'label' => _('Detail'),
                        'route' => 'profile',
                        'action' => 'detail',
                    ],
                ],
            ],
        ],
    ],
    'doctrine' => [
        'driver' => [
            'netrunners' => [
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => __DIR__ . '/../src/Entity',
            ],
            'orm_default' => [
                'drivers' => [
                    'Netrunners\Entity' => 'netrunners',
                ],
            ],
        ],
    ],
    'bjyauthorize' => [

        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
//                'adminactions' => []
            ],
        ],

        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
//                    ['superadmin', 'adminactions', ['canuse']],
                ],

                'deny' => [
                    // ...
                ],
            ],
        ],

        'guards' => [

            'BjyAuthorize\Guard\Controller' => [
                ['controller' => 'Netrunners\Controller\Profile', 'action' => ['profile'],'roles' => ['user']],
                ['controller' => 'Netrunners\Controller\Profile', 'action' => ['index', 'xhrData'],'roles' => ['admin']],
                ['controller' => 'Netrunners\Controller\System', 'action' => ['profileIndex'],'roles' => ['user']],
                ['controller' => 'Netrunners\Controller\System', 'action' => ['index', 'xhrData'],'roles' => ['admin']],
                ['controller' => 'Netrunners\Controller\Feedback', 'roles' => ['admin']],
            ],

        ],
    ],
];
