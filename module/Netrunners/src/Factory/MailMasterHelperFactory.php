<?php

namespace Netrunners\Factory;

use Interop\Container\ContainerInterface;
use Netrunners\View\Helper\MailMasterHelper;
use Zend\ServiceManager\Factory\FactoryInterface;

class MailMasterHelperFactory implements FactoryInterface
{

    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    )
    {
        return new MailMasterHelper($container);
    }

}
