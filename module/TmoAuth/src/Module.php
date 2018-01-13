<?php
namespace TmoAuth;

use Zend\Mvc\MvcEvent;
use TmoAuth\Listener\AuthorizeUserListener;

class Module
{

    public function onBootstrap(MvcEvent $mvcEvent)
    {
        $em = $mvcEvent->getApplication()->getEventManager();
        $em->attach(new AuthorizeUserListener());
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

}
