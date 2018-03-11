<?php

namespace Netrunners;

return array(
    'router' => array(
        'routes' => array(
            'profile' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/profile[/:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'Netrunners\Controller',
                        'controller'    => 'Netrunners\Controller\Profile',
                        'action'        => 'profile',
                    ),
                ),
            ),
            'system' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/system[/:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'Netrunners\Controller',
                        'controller'    => 'Netrunners\Controller\System',
                        'action'        => 'profileIndex',
                    ),
                ),
            ),
            'feedback' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/feedback[/:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'Netrunners\Controller',
                        'controller'    => 'Netrunners\Controller\Feedback',
                        'action'        => 'index',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            'Netrunners\Controller\Profile' => 'Netrunners\Factory\ProfileControllerFactory',
            'Netrunners\Controller\System' => 'Netrunners\Factory\SystemControllerFactory',
            'Netrunners\Controller\Feedback' => 'Netrunners\Factory\FeedbackControllerFactory',
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'translator' => 'Zend\Mvc\Service\TranslatorServiceFactory',
            'Netrunners\Service\BaseService' => 'Netrunners\Factory\BaseServiceFactory',
            'Netrunners\Service\AuctionService' => 'Netrunners\Factory\AuctionServiceFactory',
            'Netrunners\Service\AdminService' => 'Netrunners\Factory\AdminServiceFactory',
            'Netrunners\Service\ChatService' => 'Netrunners\Factory\ChatServiceFactory',
            'Netrunners\Service\CodingService' => 'Netrunners\Factory\CodingServiceFactory',
            'Netrunners\Service\CodebreakerService' => 'Netrunners\Factory\CodebreakerServiceFactory',
            'Netrunners\Service\CombatService' => 'Netrunners\Factory\CombatServiceFactory',
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
            'Netrunners\Service\MilkrunService' => 'Netrunners\Factory\MilkrunServiceFactory',
            'Netrunners\Service\MilkrunAivatarService' => 'Netrunners\Factory\MilkrunAivatarServiceFactory',
            'Netrunners\Service\ParserService' => 'Netrunners\Factory\ParserServiceFactory',
            'Netrunners\Service\ProfileService' => 'Netrunners\Factory\ProfileServiceFactory',
            'Netrunners\Service\ResearchService' => 'Netrunners\Factory\ResearchServiceFactory',
            'Netrunners\Service\ServerDataService' => 'Netrunners\Factory\ServerDataServiceFactory',
            'Netrunners\Service\SystemService' => 'Netrunners\Factory\SystemServiceFactory',
            'Netrunners\Service\SystemGeneratorService' => 'Netrunners\Factory\SystemGeneratorServiceFactory',
            'Netrunners\Service\UtilityService' => 'Netrunners\Factory\UtilityServiceFactory',
        ),
    ),
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
            ),
        ),
    ),
    'view_helpers' => array(
        'invokables'=> array(
        ),
        'factories' => array(
            'image_url_helper' => 'Netrunners\Factory\ImageUrlHelperFactory',
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    'navigation' => array(
        'default' => array(
            array(
                'label' => _('Profiles'),
                'route' => 'profile',
                'pages' => array(
                    array(
                        'label' => _('Detail'),
                        'route' => 'profile',
                        'action' => 'detail',
                    ),
                ),
            ),
        ),
    ),
    'doctrine' => array(
        'driver' => array(
            'netrunners' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => __DIR__ . '/../src/Entity',
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Netrunners\Entity' => 'netrunners',
                ),
            ),
        ),
    ),
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
);
