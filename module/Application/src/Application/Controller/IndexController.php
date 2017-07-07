<?php

namespace Application\Controller;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\CompanyName;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\Word;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Service\LoginService;
use Netrunners\Service\LoopService;
use Netrunners\Service\NodeService;
use Netrunners\Service\ParserService;
use Application\Service\WebsocketService;
use Netrunners\Service\UtilityService;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
use Zend\Console\ColorInterface;
use Zend\Console\Request;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var UtilityService
     */
    protected $utilityService;

    /**
     * @var ParserService
     */
    protected $parserService;

    /**
     * @var LoopService
     */
    protected $loopService;

    /**
     * @var NodeService
     */
    protected $nodeService;

    /**
     * @var LoginService
     */
    protected $loginService;


    /**
     * IndexController constructor.
     * @param EntityManager $entityManager
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param LoopService $loopService
     * @param NodeService $nodeService
     * @param LoginService $loginService
     */
    public function __construct(
        EntityManager $entityManager,
        UtilityService $utilityService,
        ParserService $parserService,
        LoopService $loopService,
        NodeService $nodeService,
        LoginService $loginService
    )
    {
        $this->entityManager = $entityManager;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->loopService = $loopService;
        $this->nodeService = $nodeService;
        $this->loginService = $loginService;
    }

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        $config = $this->getServiceLocator()->get('config');
        $view = new ViewModel();
        $view->setVariables([
            'wsprotocol' => $config['wsconfig']['wsprotocol'],
            'wshost' => $config['wsconfig']['wshost'],
            'wsport' => $config['wsconfig']['wsport']
        ]);
        return $view;
    }

    // CLI

    /**
     * @throws \React\Socket\ConnectionException
     */
    public function cliStartWebsocketAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
        $adminMode = $request->getParam('adminmode') || $request->getParam('am');
        $console = $this->getServiceLocator()->get('console');
        $config = $this->getServiceLocator()->get('config');
        $console->writeLine("=== STARTING WEBSOCKET SERVICE ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        $loop = Factory::create();
        $webSock = new Server($loop);
        $webSock->listen(8080, '0.0.0.0');
        /** @noinspection PhpUnusedLocalVariableInspection */
        $server = new IoServer(
            new HttpServer(
                new WsServer(
                    new WebsocketService(
                        $this->entityManager,
                        $this->utilityService,
                        $this->parserService,
                        $this->loopService,
                        $this->nodeService,
                        $this->loginService,
                        $loop,
                        $config['hashmod'],
                        $adminMode
                    )
                )
            ),
            $webSock
        );
        $console->writeLine("=== WEBSOCKET SERVICE STARTED ===", ColorInterface::LIGHT_WHITE, ColorInterface::GREEN);
        $loop->run();
    }

    public function cliResetSkillsAction()
    {
        // add skills
        $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
        $profiles = $this->entityManager->getRepository('Netrunners\Entity\Profile')->findAll();
        foreach ($profiles as $profile) {
            foreach ($skills as $skill) {
                /** @var Skill $skill */
                $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
                /** @var SkillRatingRepository $skillRatingRepo */
                $skillRating = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
                if ($skillRating) continue;
                $skillRating = new SkillRating();
                $skillRating->setProfile($profile);
                $skillRating->setRating($skill->getLevel());
                $skillRating->setSkill($skill);
                $this->entityManager->persist($skillRating);
            }
        }
        $this->entityManager->flush();
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

    public function cliPopulateWordTableAction()
    {
        set_time_limit(0);
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine('Reading in CSV', ColorInterface::GREEN);
        if (($handle = fopen(getcwd() . '/public/nouns.csv', "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $theWord = $data[0];
                $wordLength = strlen($theWord);
                if ($wordLength < 4) continue;
                $console->writeLine('ADDING: ' . $theWord, ColorInterface::WHITE);
                $word = new Word();
                $word->setContent($theWord);
                $word->setLength($wordLength);
                $this->entityManager->persist($word);
            }
            fclose($handle);
        }
        $this->entityManager->flush();
        $console->writeLine('DONE reading in CSV', ColorInterface::GREEN);
        return true;

    }

    public function cliPopulateCompanyNamesTableAction()
    {
        set_time_limit(0);
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine('Reading in CSV', ColorInterface::GREEN);
        if (($handle = fopen(getcwd() . '/public/compnames.csv', "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $theWord = $data[0];
                $console->writeLine('ADDING: ' . $theWord, ColorInterface::WHITE);
                $word = new CompanyName();
                $word->setContent($theWord);
                $this->entityManager->persist($word);
            }
            fclose($handle);
        }
        $this->entityManager->flush();
        $console->writeLine('DONE reading in CSV', ColorInterface::GREEN);
        return true;

    }

}
