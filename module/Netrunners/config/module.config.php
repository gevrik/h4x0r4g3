<?php
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
                        'action'        => 'index',
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
            'Netrunners\Controller\Feedback' => 'Netrunners\Factory\FeedbackControllerFactory',
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'translator' => 'Zend\Mvc\Service\TranslatorServiceFactory',
            'Netrunners\Service\BaseService' => 'Netrunners\Factory\BaseServiceFactory',
            'Netrunners\Service\AdminService' => 'Netrunners\Factory\AdminServiceFactory',
            'Netrunners\Service\ChatService' => 'Netrunners\Factory\ChatServiceFactory',
            'Netrunners\Service\CodingService' => 'Netrunners\Factory\CodingServiceFactory',
            'Netrunners\Service\CodebreakerService' => 'Netrunners\Factory\CodebreakerServiceFactory',
            'Netrunners\Service\CombatService' => 'Netrunners\Factory\CombatServiceFactory',
            'Netrunners\Service\FactionService' => 'Netrunners\Factory\FactionServiceFactory',
            'Netrunners\Service\FileService' => 'Netrunners\Factory\FileServiceFactory',
            'Netrunners\Service\FileUtilityService' => 'Netrunners\Factory\FileUtilityServiceFactory',
            'Netrunners\Service\ConnectionService' => 'Netrunners\Factory\ConnectionServiceFactory',
            'Netrunners\Service\GameOptionService' => 'Netrunners\Factory\GameOptionServiceFactory',
            'Netrunners\Service\GroupService' => 'Netrunners\Factory\GroupServiceFactory',
            'Netrunners\Service\HangmanService' => 'Netrunners\Factory\HangmanServiceFactory',
            'Netrunners\Service\LoginService' => 'Netrunners\Factory\LoginServiceFactory',
            'Netrunners\Service\LoopService' => 'Netrunners\Factory\LoopServiceFactory',
            'Netrunners\Service\ManpageService' => 'Netrunners\Factory\ManpageServiceFactory',
            'Netrunners\Service\NodeService' => 'Netrunners\Factory\NodeServiceFactory',
            'Netrunners\Service\NotificationService' => 'Netrunners\Factory\NotificationServiceFactory',
            'Netrunners\Service\NpcInstanceService' => 'Netrunners\Factory\NpcInstanceServiceFactory',
            'Netrunners\Service\MailMessageService' => 'Netrunners\Factory\MailMessageServiceFactory',
            'Netrunners\Service\MilkrunService' => 'Netrunners\Factory\MilkrunServiceFactory',
            'Netrunners\Service\ParserService' => 'Netrunners\Factory\ParserServiceFactory',
            'Netrunners\Service\ProfileService' => 'Netrunners\Factory\ProfileServiceFactory',
            'Netrunners\Service\ResearchService' => 'Netrunners\Factory\ResearchServiceFactory',
            'Netrunners\Service\SystemService' => 'Netrunners\Factory\SystemServiceFactory',
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
                'paths' => __DIR__ . '/../src/Netrunners/Entity',
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Netrunners\Entity' => 'netrunners',
                ),
            ),
        ),
    ),
);
