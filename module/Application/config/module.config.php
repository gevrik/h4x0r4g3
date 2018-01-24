<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use TmoAuth\Factory\UserControllerFactory;
use Zend\ServiceManager\Factory\InvokableFactory;

return array(
    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Zend\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            'application' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/application',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'factories' => array(
            'translator' => 'Zend\Mvc\Service\TranslatorServiceFactory',
            'navigation' => 'Zend\Navigation\Service\DefaultNavigationFactory',
            'Application\Service\WebsocketService' => InvokableFactory::class
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
    'controllers' => array(
        'factories' => array(
            'Application\Controller\Index' => 'Application\Factory\IndexControllerFactory',
            'zfcuser' => UserControllerFactory::class
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => false,
        'display_exceptions'       => false,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
            'layout/web'             => __DIR__ . '/../view/layout/web.phtml',
            'layout/tp'             => __DIR__ . '/../view/layout/tp.phtml',
            'layout/tp-editor'             => __DIR__ . '/../view/layout/tp-editor.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
    'navigation' => array(
        'default' => array(
            array(
                'label' => _('Home'),
                'route' => 'home',
            ),
        ),
    ),
    // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
                'start-websocket' => array(
                    'options' => array(
                        'route'    => 'start-websocket [--adminmode|-am]',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-start-websocket'
                        )
                    )
                ),
                'create-systems' => array(
                    'options' => array(
                        'route'    => 'create-systems',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-create-systems-for-users'
                        )
                    )
                ),
                'parse-so' => array(
                    'options' => array(
                        'route'    => 'parse-so',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-parse-stack-overflow-for-posts'
                        )
                    )
                ),
                'reset-skills' => array(
                    'options' => array(
                        'route'    => 'reset-skills',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-reset-skills'
                        )
                    )
                ),
                'populate-words' => array(
                    'options' => array(
                        'route'    => 'populate-words',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-populate-word-table'
                        )
                    )
                ),
                'populate-company-names' => array(
                    'options' => array(
                        'route'    => 'populate-company-names',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-populate-company-names-table'
                        )
                    )
                ),
                'create-faction-systems' => array(
                    'options' => array(
                        'route'    => 'create-faction-systems',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-create-faction-systems'
                        )
                    )
                ),
                'create-admin-account' => array(
                    'options' => array(
                        'route'    => 'create-admin-account',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-create-admin-account'
                        )
                    )
                ),
                'create-chatsubo' => array(
                    'options' => array(
                        'route'    => 'create-chatsubo',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-create-chatsubo'
                        )
                    )
                ),
                'init-server' => array(
                    'options' => array(
                        'route'    => 'init-server',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-init-server-settings'
                        )
                    )
                ),
                'create-milkrun-aivatars' => array(
                    'options' => array(
                        'route'    => 'create-milkrun-aivatars',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-add-milkrun-aivatars'
                        )
                    )
                ),
                'harvest-geocoords' => array(
                    'options' => array(
                        'route'    => 'harvest-geocoords',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-harvest-geocoords'
                        )
                    )
                ),
                'upgrade-connections' => array(
                    'options' => array(
                        'route'    => 'upgrade-connections',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Index',
                            'action'     => 'cli-upgrade-connections'
                        )
                    )
                ),
            ),
        ),
    ),
);
