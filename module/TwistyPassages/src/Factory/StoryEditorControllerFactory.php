<?php

/**
 * StoryEditorController Factory.
 * StoryEditorController Factory.
 * @version 1.0
 * @author Gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Factory;

use Interop\Container\ContainerInterface;
use TwistyPassages\Controller\StoryEditorController;
use TwistyPassages\Service\StoryService;
use Zend\ServiceManager\Factory\FactoryInterface;

class StoryEditorControllerFactory implements FactoryInterface
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return StoryEditorController|object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new StoryEditorController(
            $container->get(StoryService::class)
        );
    }

}
