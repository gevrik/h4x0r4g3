<?php

namespace TmoAuth;

use TmoAuth\Factory\UserControllerFactory;

return array(
    'router' => array(
        'routes' => array(
            'zfcuser' => array(
                'type' => 'Literal',
                'priority' => 1000,
                'options' => array(
                    'route' => '/user',
                    'defaults' => array(
                        'controller' => 'TmoAuth\Controller\User',
                        'action'     => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'login' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/login',
                            'defaults' => array(
                                'controller' => 'TmoAuth\Controller\User',
                                'action'     => 'login',
                            ),
                        ),
                    ),
                    'authenticate' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/authenticate',
                            'defaults' => array(
                                'controller' => 'TmoAuth\Controller\User',
                                'action'     => 'authenticate',
                            ),
                        ),
                    ),
                    'logout' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/logout',
                            'defaults' => array(
                                'controller' => 'TmoAuth\Controller\User',
                                'action'     => 'logout',
                            ),
                        ),
                    ),
                    'register' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/register',
                            'defaults' => array(
                                'controller' => 'TmoAuth\Controller\User',
                                'action'     => 'register',
                            ),
                        ),
                    ),
                    'changepassword' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/change-password',
                            'defaults' => array(
                                'controller' => 'TmoAuth\Controller\User',
                                'action'     => 'changepassword',
                            ),
                        ),
                    ),
                    'changeemail' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/change-email',
                            'defaults' => array(
                                'controller' => 'TmoAuth\Controller\User',
                                'action' => 'changeemail',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
        ),
        'factories' => array(
            'TmoAuth\Controller\User' => 'TmoAuth\Factory\UserControllerFactory',
            'zfcuser' => UserControllerFactory::class,
        ),
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
        'template_map' => array(
            'zfc-user/user/login'   => __DIR__ . '/../view/tmo-auth/user/login.phtml',
        ),
    ),
    'navigation' => array(
        'default' => array(
            array(
                'label' => _('User'),
                'route' => 'zfcuser',
                'pages' => array(
                    array(
                        'label' => _('Log-in'),
                        'route' => 'zfcuser/login',
                        'action' => 'login',
                    ),
                    array(
                        'label' => _('Register'),
                        'route' => 'zfcuser/register',
                        'action' => 'register',
                    ),
                ),
            ),
        ),
    ),
    'doctrine' => array(
        'driver' => array(
            // overriding zfc-user-doctrine-orm's config
            'zfcuser_entity' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'paths' => __DIR__ . '/../src/Entity',
            ),
            'orm_default' => array(
                'drivers' => array(
                    'TmoAuth\Entity' => 'zfcuser_entity',
                ),
            ),
        ),
    ),
    'bjyauthorize' => array(
        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
                'profile' => [],
            ],
        ],
        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
                    ['user', 'profile', ['detail']],
                    ['admin', 'profile', ['update']],
                ],

                'deny' => [
                    // ...
                ],
            ],
        ],
        'guards' => [
            'BjyAuthorize\Guard\Controller' => [
                ['controller' => 'TmoAuth\Controller\User', 'action' => ['login', 'index', 'register'], 'roles' => ['guest', 'user']],
                ['controller' => 'TmoAuth\Controller\User', 'action' => ['logout', 'changepassword'], 'roles' => 'user'],
            ],

        ],
    ),
    'zfcuser' => array(
        'new_user_default_role' => 'user',
    ),
);
