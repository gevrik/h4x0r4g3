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
        return include __DIR__ . '/../config/module.config.php';
    }

}
