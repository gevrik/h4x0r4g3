<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\System;
use Netrunners\Service\CodingService;
use Netrunners\Service\ParserService;
use Netrunners\Service\ProfileService;
use Application\Service\WebsocketService;
use Netrunners\Service\SystemService;
use Netrunners\Service\UtilityService;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
use TmoAuth\Entity\User;
use Zend\Console\ColorInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var ProfileService
     */
    protected $profileService;

    /**
     * @var UtilityService
     */
    protected $utilityService;

    /**
     * @var ParserService
     */
    protected $parserService;

    /**
     * @var CodingService
     */
    protected $codingService;

    /**
     * IndexController constructor.
     * @param EntityManager $entityManager
     * @param ProfileService $profileService
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param CodingService $codingService
     */
    public function __construct(
        EntityManager $entityManager,
        ProfileService $profileService,
        UtilityService $utilityService,
        ParserService $parserService,
        CodingService $codingService
    )
    {
        $this->entityManager = $entityManager;
        $this->profileService = $profileService;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->codingService = $codingService;
    }

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        return new ViewModel();
    }

    // CLI

    /**
     * @throws \React\Socket\ConnectionException
     */
    public function cliStartWebsocketAction()
    {
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine("=== STARTING WEBSOCKET SERVICE ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        $loop = Factory::create();
        $webSock = new Server($loop);
        $webSock->listen(8080, '0.0.0.0');
        $server = new IoServer(
            new HttpServer(
                new WsServer(
                    new WebsocketService(
                        $this->entityManager,
                        $this->profileService,
                        $this->utilityService,
                        $this->parserService,
                        $this->codingService,
                        $loop
                    )
                )
            ),
            $webSock
        );
        $console->writeLine("=== WEBSOCKET SERVICE STARTED ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        $loop->run();
    }

    // MAINTENANCE

    /**
     * @return bool
     */
    public function cliCreateSystemsForUsersAction()
    {
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine("=== CREATING SYSTEMS ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        $users = $this->entityManager->getRepository('TmoAuth\Entity\User')->findAll();
        foreach ($users as $user) {
            /** @var User $user */
            $profile = $user->getProfile();
            if (!$profile->getSystem()) {
                $system = new System();
                $system->setName($user->getUsername());
                $system->setProfile($profile);
                $system->setCpu(SystemService::DEFAULT_CPU);
                $system->setMemory(SystemService::DEFAULT_MEMORY);
                $system->setStorage(SystemService::DEFAULT_STORAGE);
                $this->entityManager->persist($system);
                $profile->setSystem($system);
            }
            if (count($profile->getSystem()->getFiles()) < 1) {
                $system = $profile->getSystem();
                // root folder
                $rootDirectory = new File();
                $rootDirectory->setName('root');
                $rootDirectory->setSystem($system);
                $rootDirectory->setProfile(NULL);
                $rootDirectory->setCoder(NULL);
                $rootDirectory->setCreated(new \DateTime());
                $rootDirectory->setIntegrity(100);
                $rootDirectory->setLevel(1);
                $rootDirectory->setParent(NULL);
                $rootDirectory->setSize(0);
                $rootDirectory->setType(File::TYPE_DIRECTORY);
                $this->entityManager->persist($rootDirectory);
                $system->addFile($rootDirectory);
                $profile->setCurrentDirectory($rootDirectory);
                // home folder
                $file = new File();
                $file->setName('home');
                $file->setSystem($system);
                $file->setProfile(NULL);
                $file->setCoder(NULL);
                $file->setCreated(new \DateTime());
                $file->setIntegrity(100);
                $file->setLevel(1);
                $file->setParent($rootDirectory);
                $file->setSize(0);
                $file->setType(File::TYPE_DIRECTORY);
                $this->entityManager->persist($file);
                $system->addFile($file);
                $rootDirectory->addChild($file);
                // log folder
                $file = new File();
                $file->setName('log');
                $file->setSystem($system);
                $file->setProfile(NULL);
                $file->setCoder(NULL);
                $file->setCreated(new \DateTime());
                $file->setIntegrity(100);
                $file->setLevel(1);
                $file->setParent($rootDirectory);
                $file->setSize(0);
                $file->setType(File::TYPE_DIRECTORY);
                $this->entityManager->persist($file);
                $system->addFile($file);
                $rootDirectory->addChild($file);
                // bin folder
                $file = new File();
                $file->setName('bin');
                $file->setSystem($system);
                $file->setProfile(NULL);
                $file->setCoder(NULL);
                $file->setCreated(new \DateTime());
                $file->setIntegrity(100);
                $file->setLevel(1);
                $file->setParent($rootDirectory);
                $file->setSize(0);
                $file->setType(File::TYPE_DIRECTORY);
                $this->entityManager->persist($file);
                $system->addFile($file);
                $rootDirectory->addChild($file);
                // bitchx client
                $bitchXFile = new File();
                $bitchXFile->setName('bitchx');
                $bitchXFile->setSystem($system);
                $bitchXFile->setProfile($profile);
                $bitchXFile->setCoder($profile);
                $bitchXFile->setCreated(new \DateTime());
                $bitchXFile->setIntegrity(100);
                $bitchXFile->setLevel(1);
                $bitchXFile->setParent($file);
                $bitchXFile->setSize(1);
                $bitchXFile->setType(File::TYPE_CHAT_CLIENT);
                $bitchXFile->setExecutable(1);
                $bitchXFile->setRunning(1);
                $this->entityManager->persist($bitchXFile);
                $system->addFile($bitchXFile);
                $file->addChild($bitchXFile);
            }
        }
        $this->entityManager->flush();
        $console->writeLine("=== CREATING SYSTEMS COMPLETE ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        return true;
    }

    public function cliParseStackOverflowForPostsAction()
    {
        //$targetUrl = 'http://www.stackoverflow.com';
        $html = new \simple_html_dom();
        $html->load_file('http://www.stackoverflow.com');
        $links = $html->find('a');
        foreach ($links as $link) {
            $attributes = $link->attr;
            if (array_key_exists('class', $attributes)) {
                if ($attributes['class'] != 'question-hyperlink') {
                    continue;
                }
                echo $link->href . "\n\r";
            }
        }
    }

    public function cliPopulateWordTable()
    {
        //$targetUrl = 'http://www.stackoverflow.com';
        $html = new \simple_html_dom();
        $html->load_file('http://www.stackoverflow.com');
        $links = $html->find('a');
        foreach ($links as $link) {
            $attributes = $link->attr;
            if (array_key_exists('class', $attributes)) {
                if ($attributes['class'] != 'question-hyperlink') {
                    continue;
                }
                echo $link->href . "\n\r";
            }
        }
    }

}
