<?php
namespace Netrunners;

use Zend\Mvc\MvcEvent;

class Module
{

    public function onBootstrap(MvcEvent $mvcEvent)
    {
        //$em = $mvcEvent->getApplication()->getEventManager();
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
