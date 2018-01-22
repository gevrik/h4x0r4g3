<?php

return [
    'bjyauthorize' => [

        'default_role' => 'guest',

        'identity_provider' => 'BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider',

        'role_providers'        => array(
            'BjyAuthorize\Provider\Role\ObjectRepositoryProvider' => array(
                'object_manager'    => 'doctrine.entitymanager.orm_default',
                'role_entity_class' => 'TmoAuth\Entity\Role',
            ),
        ),

        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
                'adminactions' => []
            ],
        ],

        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
                    ['superadmin', 'adminactions', ['canuse']],
                ],

                'deny' => [
                    // ...
                ],
            ],
        ],

        'guards' => [

            'BjyAuthorize\Guard\Controller' => [
                ['controller' => 'Application\Controller\Index', 'roles' => ['guest', 'user']],
                ['controller' => 'DoctrineModule\Controller\Cli', 'roles' => ['guest', 'user']],
            ],

        ],
    ],
];
