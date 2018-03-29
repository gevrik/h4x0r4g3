<?php

namespace TwistyPassagesTest\Controller;

use BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use TwistyPassages\Service\StoryService;
use Zend\Authentication\AuthenticationService;
use Zend\Stdlib\ArrayUtils;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ProphecyInterface;

class StoryControllerTest extends AbstractHttpControllerTestCase
{

    protected $service;

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

        $this->service = $this->prophesize(StoryService::class);

        $this->authMock();
        $this->bjyMock();
    }

    private function doctrineProphecies()
    {
        /**
         * @var ObjectRepository|ProphecyInterface $prophet
         */
        $prophet = $this->prophesize(ObjectRepository::class);
        //mocked repository with exact parameter - if findOneBy is called with another parameter test will fail
        $prophet->findOneBy(['something' => '123123'])->willReturn(null);
        // instance mocked repository
        $objectRepoMock = $prophet->reveal();

        /**
         * mock em
         * @var ProphecyInterface|EntityManager $prophet
         */
        $prophet = $this->prophesize(EntityManager::class);
        //mock with exact parameter
//        $prophet->getRepository('TwistyPassages\Entity\Story')->willReturn($someRepoMock);
        //mock with any parameter
        $prophet->getRepository(Argument::any())->willReturn($objectRepoMock);
        $prophet->persist(Argument::any())->willReturn(null);
        $prophet->flush(Argument::any())->willReturn(null);
        $emMock = $prophet->reveal();

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
