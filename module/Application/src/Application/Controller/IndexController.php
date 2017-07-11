<?php

namespace Application\Controller;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\CompanyName;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Faction;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Entity\Word;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Service\LoginService;
use Netrunners\Service\LoopService;
use Netrunners\Service\ManpageService;
use Netrunners\Service\NodeService;
use Netrunners\Service\ParserService;
use Application\Service\WebsocketService;
use Netrunners\Service\ProfileService;
use Netrunners\Service\UtilityService;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Console\ColorInterface;
use Zend\Console\Request;
use Zend\Crypt\Password\Bcrypt;
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
     * @var ManpageService
     */
    protected $manpageService;


    /**
     * IndexController constructor.
     * @param EntityManager $entityManager
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param LoopService $loopService
     * @param NodeService $nodeService
     * @param LoginService $loginService
     * @param ManpageService $manpageService
     */
    public function __construct(
        EntityManager $entityManager,
        UtilityService $utilityService,
        ParserService $parserService,
        LoopService $loopService,
        NodeService $nodeService,
        LoginService $loginService,
        ManpageService $manpageService
    )
    {
        $this->entityManager = $entityManager;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->loopService = $loopService;
        $this->nodeService = $nodeService;
        $this->loginService = $loginService;
        $this->manpageService = $manpageService;
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
                        $this->manpageService,
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

    /**
     * Used to add skill ratings for new skills to existing users.
     */
    public function cliResetSkillsAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
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
                $skillRating->setNpc(NULL);
                $this->entityManager->persist($skillRating);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * TODO for mini-game
     */
    public function cliParseStackOverflowForPostsAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
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

    /**
     * To populate the word table after a new server installation.
     * @return bool
     */
    public function cliPopulateWordTableAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
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

    /**
     * To populate the company name table after a new server installation.
     * @return bool
     */
    public function cliPopulateCompanyNamesTableAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
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

    /**
     * @return bool
     */
    public function cliCreateAdminAccountAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
        set_time_limit(0);
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine('CREATING ADMIN ACCOUNT', ColorInterface::GREEN);
        $addy = $this->utilityService->getRandomAddress(32);
        $maxTries = 100;
        $tries = 0;
        while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
            $addy = $this->utilityService->getRandomAddress(32);
            $tries++;
            if ($tries >= $maxTries) {
                $console->writeLine('COULD NOT CREATE SYSTEM ADDY - ABORTING', ColorInterface::LIGHT_RED);
                return true;
            }
        }
        $user = new User();
        $user->setEmail(NULL);
        $user->setBanned(false);
        $user->setDisplayName('administrator');
        $user->setState(1);
        $user->setUsername('administrator');
        $bcrypt = new Bcrypt();
        $bcrypt->setCost(10);
        $password = bin2hex(openssl_random_pseudo_bytes(4));
        $console->writeLine('ADMIN PASSWORD: ' . $password, ColorInterface::LIGHT_RED);
        $pass = $bcrypt->create($password);
        $user->setPassword($pass);
        $user->setProfile(NULL);
        $this->entityManager->persist($user);
        $profile = new Profile();
        $profile->setUser($user);
        $profile->setCredits(1000000);
        $profile->setSnippets(100000);
        $profile->setEeg(100);
        $profile->setWillpower(100);
        $profile->setBlade(NULL);
        $profile->setBlaster(NULL);
        $profile->setShield(NULL);
        $profile->setHandArmor(NULL);
        $profile->setHeadArmor(NULL);
        $profile->setLegArmor(NULL);
        $profile->setLowerArmArmor(NULL);
        $profile->setShoesArmor(NULL);
        $profile->setShoulderArmor(NULL);
        $profile->setTorsoArmor(NULL);
        $profile->setUpperArmArmor(NULL);
        $profile->setStealthing(false);
        // add skills
        $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
        foreach ($skills as $skill) {
            /** @var Skill $skill */
            $skillRating = new SkillRating();
            $skillRating->setProfile($profile);
            $skillRating->setNpc(NULL);
            $skillRating->setRating(100);
            $skillRating->setSkill($skill);
            $this->entityManager->persist($skillRating);
        }
        // add default skillpoints
        $profile->setSkillPoints(0);
        $this->entityManager->persist($profile);
        $user->setProfile($profile);
        $defaultRole = $this->entityManager->find('TmoAuth\Entity\Role', 6);
        /** @var Role $defaultRole */
        $user->addRole($defaultRole);
        $system = new System();
        $system->setProfile($profile);
        $system->setName($user->getUsername());
        $system->setAddy($addy);
        $system->setGroup(NULL);
        $system->setFaction(NULL);
        $system->setMaxSize(System::DEFAULT_MAX_SYSTEM_SIZE);
        $system->setAlertLevel(0);
        $this->entityManager->persist($system);
        // default io node
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU);
        /** @var NodeType $nodeType */
        $ioNode = new Node();
        $ioNode->setCreated(new \DateTime());
        $ioNode->setLevel(1);
        $ioNode->setName($nodeType->getName());
        $ioNode->setSystem($system);
        $ioNode->setNodeType($nodeType);
        $this->entityManager->persist($ioNode);
        $profile->setCurrentNode($ioNode);
        $profile->setHomeNode($ioNode);
        $profile->setLocale(Profile::DEFAULT_PROFILE_LOCALE);
        // flush to db
        $this->entityManager->flush();
        $console->writeLine('DONE CREATING ADMIN ACCOUNT', ColorInterface::GREEN);
        return true;
    }

    /**
     * @return bool
     */
    public function cliCreateFactionSystemsAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
        set_time_limit(0);
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine('CREATING FACTION SYSTEMS', ColorInterface::GREEN);
        $factions = $this->entityManager->getRepository('Netrunners\Entity\Faction')->findAll();
        foreach ($factions as $faction) {
            /** @var Faction $faction */
            if ($faction->getId() > 6) continue;
            $system = new System();
            $system->setGroup(NULL);
            $system->setProfile(NULL);
            $system->setFaction($faction);
            $systemName = str_replace(' ', '-', $faction->getName()) . '-hq';
            $system->setName(strtolower($systemName));
            $system->setAlertLevel(0);
            // create a new addy for the system
            $addy = $this->utilityService->getRandomAddress(32);
            $maxTries = 100;
            $tries = 0;
            while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
                $addy = $this->utilityService->getRandomAddress(32);
                $tries++;
                if ($tries >= $maxTries) {
                    $console->writeLine('UNABLE TO GENERATE ADDY FOR SYSTEM - ABORTING', ColorInterface::LIGHT_RED);
                    return true;
                }
            }
            $system->setAddy($addy);
            $system->setMaxSize(System::FACTION_MAX_SYSTEM_SIZE);
            $this->entityManager->persist($system);
            $console->writeLine('SYSTEM GENERATED - NOW WORKING ON NODES AND CONNECTIONS', ColorInterface::LIGHT_WHITE);
            // pub io node
            $node_pio = new Node();
            $node_pio->setName('public_io');
            $node_pio->setLevel(10);
            $node_pio->setSystem($system);
            $node_pio->setDescription('faction headquarter public io node');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_PUBLICIO);
            /** @var NodeType $nodeType */
            $node_pio->setNodeType($nodeType);
            $node_pio->setCreated(new \DateTime());
            $this->entityManager->persist($node_pio);
            // pub market node
            $node_pm = new Node();
            $node_pm->setName('public_faction_market');
            $node_pm->setLevel(10);
            $node_pm->setSystem($system);
            $node_pm->setDescription('faction headquarter public market node');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_MARKET);
            /** @var NodeType $nodeType */
            $node_pm->setNodeType($nodeType);
            $node_pm->setCreated(new \DateTime());
            $this->entityManager->persist($node_pm);
            // connections between public market and public io
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(NULL);
            $connection->setSourceNode($node_pio);
            $connection->setTargetNode($node_pm);
            $connection->setType(Connection::TYPE_NORMAL);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(NULL);
            $connection->setTargetNode($node_pio);
            $connection->setSourceNode($node_pm);
            $connection->setType(Connection::TYPE_NORMAL);
            $this->entityManager->persist($connection);
            // firewall 1 node
            $node_fw1 = new Node();
            $node_fw1->setName('firewall');
            $node_fw1->setLevel(10);
            $node_fw1->setSystem($system);
            $node_fw1->setDescription('faction headquarter firewall');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_FIREWALL);
            /** @var NodeType $nodeType */
            $node_fw1->setNodeType($nodeType);
            $node_fw1->setCreated(new \DateTime());
            $this->entityManager->persist($node_fw1);
            // connections between pub market 1 and firewall 1
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setSourceNode($node_fw1);
            $connection->setTargetNode($node_pm);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setTargetNode($node_fw1);
            $connection->setSourceNode($node_pm);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            // faction market node
            $node_fm = new Node();
            $node_fm->setName('faction_market');
            $node_fm->setLevel(10);
            $node_fm->setSystem($system);
            $node_fm->setDescription('faction headquarter market');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_MARKET);
            /** @var NodeType $nodeType */
            $node_fm->setNodeType($nodeType);
            $node_fm->setCreated(new \DateTime());
            $this->entityManager->persist($node_fm);
            // connections between faction market and firewall 1
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setSourceNode($node_fm);
            $connection->setTargetNode($node_fw1);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setTargetNode($node_fm);
            $connection->setSourceNode($node_fw1);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            // second firewall node
            $node_fw2 = new Node();
            $node_fw2->setName('firewall');
            $node_fw2->setLevel(10);
            $node_fw2->setSystem($system);
            $node_fw2->setDescription('faction headquarter firewall');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_FIREWALL);
            /** @var NodeType $nodeType */
            $node_fw2->setNodeType($nodeType);
            $node_fw2->setCreated(new \DateTime());
            $this->entityManager->persist($node_fw2);
            // connections between fw2 and faction market
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setSourceNode($node_fw2);
            $connection->setTargetNode($node_fm);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setTargetNode($node_fw2);
            $connection->setSourceNode($node_fm);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            // bank node
            $node_bnk = new Node();
            $node_bnk->setName('faction_bank');
            $node_bnk->setLevel(10);
            $node_bnk->setSystem($system);
            $node_bnk->setDescription('faction headquarter bank');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_BANK);
            /** @var NodeType $nodeType */
            $node_bnk->setNodeType($nodeType);
            $node_bnk->setCreated(new \DateTime());
            $this->entityManager->persist($node_bnk);
            // connections between bank and fw2
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setSourceNode($node_bnk);
            $connection->setTargetNode($node_fw2);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setTargetNode($node_bnk);
            $connection->setSourceNode($node_fw2);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            // bulletin board node
            $node_bb = new Node();
            $node_bb->setName('bulletin_board');
            $node_bb->setLevel(10);
            $node_bb->setSystem($system);
            $node_bb->setDescription('faction headquarter bulletin board');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_BB);
            /** @var NodeType $nodeType */
            $node_bb->setNodeType($nodeType);
            $node_bb->setCreated(new \DateTime());
            $this->entityManager->persist($node_bb);
            // connections between bb and bank
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(NULL);
            $connection->setSourceNode($node_bb);
            $connection->setTargetNode($node_bnk);
            $connection->setType(Connection::TYPE_NORMAL);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(NULL);
            $connection->setTargetNode($node_bb);
            $connection->setSourceNode($node_bnk);
            $connection->setType(Connection::TYPE_NORMAL);
            $this->entityManager->persist($connection);
            // agent node
            $node_agn = new Node();
            $node_agn->setName('faction_agent');
            $node_agn->setLevel(10);
            $node_agn->setSystem($system);
            $node_agn->setDescription('faction headquarter agent');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_AGENT);
            /** @var NodeType $nodeType */
            $node_agn->setNodeType($nodeType);
            $node_agn->setCreated(new \DateTime());
            $this->entityManager->persist($node_agn);
            // connection between agent and bank
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(NULL);
            $connection->setSourceNode($node_agn);
            $connection->setTargetNode($node_bnk);
            $connection->setType(Connection::TYPE_NORMAL);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(NULL);
            $connection->setTargetNode($node_agn);
            $connection->setSourceNode($node_bnk);
            $connection->setType(Connection::TYPE_NORMAL);
            $this->entityManager->persist($connection);
            // cpu node
            $node_cpu = new Node();
            $node_cpu->setName('system_cpu');
            $node_cpu->setLevel(10);
            $node_cpu->setSystem($system);
            $node_cpu->setDescription('faction headquarter cpu');
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU);
            /** @var NodeType $nodeType */
            $node_cpu->setNodeType($nodeType);
            $node_cpu->setCreated(new \DateTime());
            $this->entityManager->persist($node_cpu);
            // connection between cpu and bank
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setSourceNode($node_cpu);
            $connection->setTargetNode($node_bnk);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
            $connection = new Connection();
            $connection->setCreated(new \DateTime());
            $connection->setLevel(10);
            $connection->setIsOpen(0);
            $connection->setTargetNode($node_cpu);
            $connection->setSourceNode($node_bnk);
            $connection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->persist($connection);
        }
        // flush everything to db
        $this->entityManager->flush();
        $console->writeLine('DONE CREATING FACTION SYSTEMS', ColorInterface::GREEN);
        return true;
    }

    /**
     * @return bool
     */
    public function cliCreateChatsuboAction()
    {
        // get request and check if we received it from the console
        $request = $this->getRequest();
        if (!$request instanceof Request){
            throw new \RuntimeException('access denied');
        }
        set_time_limit(0);
        $console = $this->getServiceLocator()->get('console');
        $console->writeLine('CREATING CHATSUBO SYSTEM', ColorInterface::GREEN);
        $system = new System();
        $system->setProfile(NULL);
        $system->setName('the_chatsubo');
        $system->setAddy('l33t:l33t:l33t:l33t:l33t:l33t:l33t:l33t');
        $system->setGroup(NULL);
        $system->setFaction(NULL);
        $system->setMaxSize(System::DEFAULT_MAX_SYSTEM_SIZE);
        $system->setAlertLevel(0);
        $this->entityManager->persist($system);
        // default io node
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_PUBLICIO);
        /** @var NodeType $nodeType */
        $ioNode = new Node();
        $ioNode->setCreated(new \DateTime());
        $ioNode->setLevel(10);
        $ioNode->setName('the_chatsubo');
        $ioNode->setSystem($system);
        $ioNode->setNodeType($nodeType);
        $ioNode->setNomob(true);
        $ioNode->setNopvp(true);
        $ioNode->setDescription('The Chatsubo is a bar for professional expatriates; located somewhere in Japan, but you could drink virtual cocktails there for a week and never hear two words in Japanese. Ratz is tending bar, his prosthetic arm jerking monotonously as he fills a tray of glasses with draft Kirin. He sees you and smiles, his teeth a webwork of East European steel and brown decay. You are still not quite sure if Ratz is the owner of the system or just a very sophisticated Aivatar.');
        $this->entityManager->persist($ioNode);
        // flush to db
        $this->entityManager->flush();
        $console->writeLine('DONE CREATING CHATSUBO SYSTEM', ColorInterface::GREEN);
        return true;
    }

}
