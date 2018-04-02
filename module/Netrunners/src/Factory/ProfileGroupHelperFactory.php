<?php

namespace Netrunners\Factory;

use Interop\Container\ContainerInterface;
use Netrunners\View\Helper\ProfileGroupHelper;
use Zend\ServiceManager\Factory\FactoryInterface;

class ProfileGroupHelperFactory implements FactoryInterface
{

    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    )
    {
        return new ProfileGroupHelper($container);
    }

}
