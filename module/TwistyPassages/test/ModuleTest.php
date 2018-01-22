<?php

namespace TwistyPassagesTest;

use PHPUnit\Framework\TestCase;
use TwistyPassages\Module;
use Zend\ModuleManager\ModuleManagerInterface;

/**
 * Class ModuleTest
 *
 * @package AdvertBackendTest
 */
class ModuleTest extends TestCase
{
    /**
     * @var string
     */
    private $moduleRoot = null;

    /**
     * Setup test cases
     */
    protected function setUp()
    {
        $this->moduleRoot = realpath(__DIR__ . '/../');
    }

    /**
     * Test initialization
     *
     * @group module
     * @group advert-backend
     */
    public function testInit()
    {
        $moduleManagerMock = $this->prophesize(
            ModuleManagerInterface::class
        );

        $this->assertTrue(class_exists(Module::class));

        $module = new Module();
        $module->init($moduleManagerMock->reveal());

        $this->assertTrue(defined('TWISTY_PASSAGES_MODULE_ROOT'));
        $this->assertEquals(
            $this->moduleRoot, realpath(TWISTY_PASSAGES_MODULE_ROOT)
        );
    }

    /**
     * Test get config
     *
     * @group module
     * @group advert-backend
     */
    public function testGetConfig()
    {
        $expectedConfig = include $this->moduleRoot
            . '/config/module.config.php';

        $module     = new Module();
        $configData = $module->getConfig();

        $this->assertEquals($expectedConfig, $configData);
    }

}
