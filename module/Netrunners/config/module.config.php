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
        ),
    ),
    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            'Netrunners\Controller\Profile' => 'Netrunners\Factory\ProfileControllerFactory'
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'translator' => 'Zend\Mvc\Service\TranslatorServiceFactory',
            'Netrunners\Service\BaseService' => 'Netrunners\Factory\BaseServiceFactory',
            'Netrunners\Service\AdminService' => 'Netrunners\Factory\AdminServiceFactory',
            'Netrunners\Service\ChatService' => 'Netrunners\Factory\ChatServiceFactory',
            'Netrunners\Service\CodingService' => 'Netrunners\Factory\CodingServiceFactory',
            'Netrunners\Service\FileService' => 'Netrunners\Factory\FileServiceFactory',
            'Netrunners\Service\ConnectionService' => 'Netrunners\Factory\ConnectionServiceFactory',
            'Netrunners\Service\LoopService' => 'Netrunners\Factory\LoopServiceFactory',
            'Netrunners\Service\NodeService' => 'Netrunners\Factory\NodeServiceFactory',
            'Netrunners\Service\NotificationService' => 'Netrunners\Factory\NotificationServiceFactory',
            'Netrunners\Service\MailMessageService' => 'Netrunners\Factory\MailMessageServiceFactory',
            'Netrunners\Service\ParserService' => 'Netrunners\Factory\ParserServiceFactory',
            'Netrunners\Service\ProfileService' => 'Netrunners\Factory\ProfileServiceFactory',
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
