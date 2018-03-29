<?php

/**
 * ChoiceController Factory.
 * ChoiceController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Interop\Container\ContainerInterface;
use TwistyPassages\Controller\ChoiceController;
use TwistyPassages\Service\ChoiceService;
use Zend\ServiceManager\Factory\FactoryInterface;

class ChoiceControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return ChoiceController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ChoiceController(
            $container->get(ChoiceService::class)
        );
    }

}
