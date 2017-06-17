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
use Netrunners\Service\CodingService;
use Netrunners\Service\LoopService;
use Netrunners\Service\NodeService;
use Netrunners\Service\ParserService;
use Netrunners\Service\ProfileService;
use Application\Service\WebsocketService;
use Netrunners\Service\UtilityService;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
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
     * @var LoopService
     */
    protected $loopService;

    /**
     * @var NodeService
     */
    protected $nodeService;


    /**
     * IndexController constructor.
     * @param EntityManager $entityManager
     * @param ProfileService $profileService
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param CodingService $codingService
     * @param LoopService $loopService
     * @param NodeService $nodeService
     */
    public function __construct(
        EntityManager $entityManager,
        ProfileService $profileService,
        UtilityService $utilityService,
        ParserService $parserService,
        CodingService $codingService,
        LoopService $loopService,
        NodeService $nodeService
    )
    {
        $this->entityManager = $entityManager;
        $this->profileService = $profileService;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->codingService = $codingService;
        $this->loopService = $loopService;
        $this->nodeService = $nodeService;
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
        $config = $this->getServiceLocator()->get('config');
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
                        $this->loopService,
                        $this->nodeService,
                        $loop,
                        $config['hashmod']
                    )
                )
            ),
            $webSock
        );
        $console->writeLine("=== WEBSOCKET SERVICE STARTED ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        $loop->run();
    }

    public function cliParseStackOverflowForPostsAction()
    {
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
