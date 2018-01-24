<?php

namespace TwistyPassages;

use TwistyPassages\Factory\StoryControllerFactory;
use TwistyPassages\Factory\StoryEditorControllerFactory;

return array(
    'router' => array(
        'routes' => array(
            'story' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/story[/:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\Story',
                        'action'        => 'welcome',
                    ),
                ),
            ),
            'story-editor' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/story-editor[/:action][/:id]',
                    'constraints' => array(
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'id' => '[0-9]+',
                    ),
                    'defaults' => array(
                        '__NAMESPACE__' => 'TwistyPassages\Controller',
                        'controller'    => 'TwistyPassages\Controller\StoryEditor',
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
            'TwistyPassages\Controller\Story' => StoryControllerFactory::class,
            'TwistyPassages\Controller\StoryEditor' => StoryEditorControllerFactory::class,
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'TwistyPassages\Service\StoryService' => 'TwistyPassages\Factory\StoryServiceFactory',
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
                'label' => _('Stories'),
                'route' => 'story',
                'pages' => array(
                    array(
                        'label' => _('Stories'),
                        'route' => 'story',
                        'action' => 'index',
                    ),
                ),
            ),
        ),
    ),
    'doctrine' => array(
        'driver' => array(
            'twisty_passages' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => __DIR__ . '/../src/Entity',
            ),
            'orm_default' => array(
                'drivers' => array(
                    'TwistyPassages\Entity' => 'twisty_passages',
                ),
            ),
        ),
    ),
    'bjyauthorize' => [
        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
                'story' => [],
            ],
        ],

        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
                    ['admin', 'story', ['admin']],
                ],

                'deny' => [
                    // ...
                ],
            ],
        ],
        'guards' => [

            'BjyAuthorize\Guard\Controller' => [
                ['controller' => 'TwistyPassages\Controller\Story', 'roles' => ['user']],
                ['controller' => 'TwistyPassages\Controller\StoryEditor', 'roles' => ['user']],
            ],

        ],
    ],
);
