<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use BjyAuthorize\View\RedirectionStrategy;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $translator = $e->getApplication()->getServiceManager()->get('translator');
        $translator
            ->setLocale(\Locale::DEFAULT_LOCALE)
            ->setFallbackLocale('en_US');

//        $strategy = new RedirectionStrategy();
//        $strategy->setRedirectUri('/user');
//        $eventManager->attach($strategy);
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

}
