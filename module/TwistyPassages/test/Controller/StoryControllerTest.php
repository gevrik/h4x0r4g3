<?php

namespace TwistyPassagesTest\Controller;

use BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider;
use Zend\Authentication\AuthenticationService;
use Zend\Stdlib\ArrayUtils;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

class StoryControllerTest extends AbstractHttpControllerTestCase
{

    /**
     *
     */
    public function setUp()
    {
        $configOverrides = [];
        $this->setApplicationConfig(ArrayUtils::merge(
            include __DIR__ . '/../../../../config/application.config.php',
            $configOverrides
        ));
        parent::setUp();
        $this->authMock();
        $this->bjyMock();
    }

    /**
     *
     */
    private function bjyMock()
    {
        $mockAuth = $this->getMockBuilder(AuthenticationIdentityProvider::class)->disableOriginalConstructor()->getMock();
        $mockAuth->expects($this->any())->method('getIdentityRoles')->willReturn(['admin']);

        $this->getApplicationServiceLocator()->setAllowOverride(true);
        $this->getApplicationServiceLocator()->setService(AuthenticationIdentityProvider::class, $mockAuth);
        $this->getApplicationServiceLocator()->setAllowOverride(false);
    }

    /**
     *
     */
    private function authMock()
    {
        $mockAuth = $this->getMockBuilder(AuthenticationService::class)->disableOriginalConstructor()->getMock();
        $mockAuth->expects($this->any())->method('hasIdentity')->willReturn(true);
        $mockAuth->expects($this->any())->method('getIdentity')->willReturn(['id' => 1, 'username' => 'admin']);

        $this->getApplicationServiceLocator()->setAllowOverride(true);
        $this->getApplicationServiceLocator()->setService(AuthenticationService::class, $mockAuth);
        $this->getApplicationServiceLocator()->setAllowOverride(false);
    }

    /**
     * @throws \Exception
     */
    public function testIndexActionCanBeAccessed()
    {
        $this->dispatch('/story');
        $this->assertResponseStatusCode(200);
        $this->assertModuleName('TwistyPassages');
        $this->assertControllerName('TwistyPassages\Controller\Story');
        $this->assertControllerClass('StoryController');
        $this->assertMatchedRouteName('story');
    }

}
