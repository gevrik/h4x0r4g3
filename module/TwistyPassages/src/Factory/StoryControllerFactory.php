<?php

/**
 * StoryController Factory.
 * StoryController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Interop\Container\ContainerInterface;
use TwistyPassages\Controller\StoryController;
use TwistyPassages\Service\StoryService;
use Zend\ServiceManager\Factory\FactoryInterface;

class StoryControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return StoryController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new StoryController(
            $container->get(StoryService::class)
        );
    }

}
