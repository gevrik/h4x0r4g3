<?php

namespace TwistyPassages;

use TwistyPassages\Factory\ChoiceControllerFactory;
use TwistyPassages\Factory\ChoiceServiceFactory;
use TwistyPassages\Factory\PassageControllerFactory;
use TwistyPassages\Factory\PassageServiceFactory;
use TwistyPassages\Factory\StoryControllerFactory;
use TwistyPassages\Factory\StoryEditorControllerFactory;
use TwistyPassages\Factory\StoryServiceFactory;
use TwistyPassages\Factory\WelcomeControllerFactory;
use TwistyPassages\Factory\WelcomeServiceFactory;
use TwistyPassages\Service\ChoiceService;
use TwistyPassages\Service\PassageService;
use TwistyPassages\Service\StoryService;
use TwistyPassages\Service\WelcomeService;

return [
    'router' => [
        'routes' => [
            'choice' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/choice[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\Choice',
                        'action'        => 'index',
                    ],
                ],
            ],
            'passage' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/passage[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\Passage',
                        'action'        => 'index',
                    ],
                ],
            ],
            'story' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/story[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\Story',
                        'action'        => 'index',
                    ],
                ],
            ],
            'story-editor' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/story-editor[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\StoryEditor',
                        'action'        => 'index',
                    ],
                ],
            ],
            'tp' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/tp[/:action][/:id]',
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\Welcome',
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
            'TwistyPassages\Controller\Choice' => ChoiceControllerFactory::class,
            'TwistyPassages\Controller\Passage' => PassageControllerFactory::class,
            'TwistyPassages\Controller\Story' => StoryControllerFactory::class,
            'TwistyPassages\Controller\StoryEditor' => StoryEditorControllerFactory::class,
            'TwistyPassages\Controller\Welcome' => WelcomeControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            ChoiceService::class => ChoiceServiceFactory::class,
            PassageService::class => PassageServiceFactory::class,
            StoryService::class => StoryServiceFactory::class,
            WelcomeService::class => WelcomeServiceFactory::class,
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
                'label' => _('Choices'),
                'route' => 'choice',
                'pages' => [
                    [
                        'label' => _('Choices'),
                        'route' => 'choice',
                        'action' => 'index',
                    ],
                ],
            ],
            [
                'label' => _('Passages'),
                'route' => 'passage',
                'pages' => [
                    [
                        'label' => _('Passages'),
                        'route' => 'passage',
                        'action' => 'index',
                    ],
                ],
            ],
            [
                'label' => _('Stories'),
                'route' => 'story',
                'pages' => [
                    [
                        'label' => _('Stories'),
                        'route' => 'story',
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'doctrine' => [
        'driver' => [
            'twisty_passages' => [
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => __DIR__ . '/../src/Entity',
            ],
            'orm_default' => [
                'drivers' => [
                    'TwistyPassages\Entity' => 'twisty_passages',
                ],
            ],
        ],
    ],
    'bjyauthorize' => [
        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
                'adminstuff' => [],
                'choice' => [],
                'passage' => [],
                'story' => [],
            ],
        ],

        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
                    ['admin', 'adminstuff', ['admin']],
                    ['admin', 'choice', ['admin']],
                    ['admin', 'passage', ['admin']],
                    ['admin', 'story', ['admin']],
                ],

                'deny' => [
                    // ...
                ],
            ],
        ],
        'guards' => [

            'BjyAuthorize\Guard\Controller' => [
                ['controller' => 'TwistyPassages\Controller\Choice', 'roles' => ['user']],
                ['controller' => 'TwistyPassages\Controller\Passage', 'roles' => ['user']],
                ['controller' => 'TwistyPassages\Controller\Story', 'roles' => ['user']],
                ['controller' => 'TwistyPassages\Controller\StoryEditor', 'roles' => ['user']],
                ['controller' => 'TwistyPassages\Controller\Welcome', 'roles' => ['user']],
            ],

        ],
    ],
];
