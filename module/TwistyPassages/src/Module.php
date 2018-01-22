<?php
namespace TwistyPassages;

use Zend\Config\Factory;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\InitProviderInterface;
use Zend\ModuleManager\ModuleManagerInterface;

class Module implements ConfigProviderInterface, InitProviderInterface
{

    /**
     * Initialize module
     *
     * @param ModuleManagerInterface $manager
     */
    public function init(ModuleManagerInterface $manager)
    {
        if (!defined('TWISTY_PASSAGES_MODULE_ROOT')) {
            define('TWISTY_PASSAGES_MODULE_ROOT', __DIR__ . '/..');
        }
    }

    /**
     * Get module configuration
     */
    public function getConfig()
    {
        return Factory::fromFile(
            TWISTY_PASSAGES_MODULE_ROOT . '/config/module.config.php'
        );
    }

}
