<?php

namespace Application\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Service\CodingService;
use Netrunners\Service\LoopService;
use Netrunners\Service\NodeService;
use Netrunners\Service\ParserService;
use Netrunners\Service\ProfileService;
use Netrunners\Service\UtilityService;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Crypt\Password\Bcrypt;
use Zend\I18n\Validator\Alnum;
use Zend\Log\Logger;

class WebsocketService implements MessageComponentInterface {

    const LOOP_TIME_JOBS = 5;
    const LOOP_TIME_RESOURCES = 300;

    public static $instance;

    /**
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * @var array
     */
    protected $clientsData = array();

    /**
     * @var LoopInterface
     */
    protected $loop;

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
     * @var Logger
     */
    protected $logger;

    /**
     * @var
     */
    protected $hash;

    /**
     * @param EntityManager $entityManager
     * @param ProfileService $profileService
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param CodingService $codingService
     * @param LoopService $loopService
     * @param NodeService $nodeService
     * @param LoopInterface $loop
     * @param $hash
     */
    public function __construct(
        EntityManager $entityManager,
        ProfileService $profileService,
        UtilityService $utilityService,
        ParserService $parserService,
        CodingService $codingService,
        LoopService $loopService,
        NodeService $nodeService,
        LoopInterface $loop,
        $hash
    ) {
        $this->clients = new \SplObjectStorage;
        $this->entityManager = $entityManager;
        $this->profileService = $profileService;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->codingService = $codingService;
        $this->loopService = $loopService;
        $this->nodeService = $nodeService;
        $this->loop = $loop;
        $this->hash = $hash;

        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, array('stream' => getcwd() . '/data/log/command_log.txt'));

        $this->loop->addPeriodicTimer(self::LOOP_TIME_JOBS, function(){
            $this->loopService->loopJobs();
        });

        $this->loop->addPeriodicTimer(self::LOOP_TIME_RESOURCES, function(){
            $this->loopService->loopResources();
        });

        self::$instance = $this;

    }

    /**
     * @return WebsocketService
     */
    public static function getInstance() {
        return self::$instance;
    }

    /**
     * @return \SplObjectStorage
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * @return array
     */
    public function getClientsData()
    {
        return $this->clientsData;
    }

    /**
     * @param $resourceId
     * @return mixed|null
     */
    public function getClientData($resourceId)
    {
        return (isset($this->clientsData[$resourceId])) ? (object)$this->clientsData[$resourceId] : NULL;
    }

    /**
     * @param int $resourceId
     * @param string $optionName
     * @param mixed $optionValue
     */
    public function setCodingOption($resourceId, $optionName, $optionValue)
    {
        if (isset($this->clientsData[$resourceId])) {
            $this->clientsData[$resourceId]['codingOptions'][$optionName] = $optionValue;
        }
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        $this->clientsData[$conn->resourceId] = array(
            'socketId' => $conn->resourceId,
            'username' => false,
            'userId' => false,
            'hash' => false,
            'tempPassword' => false,
            'profileId' => false,
            'codingOptions' => [
                'fileType' => 0,
                'fileLevel' => 0,
                'mode' => 'resource'
            ]
        );
    }

    /**
     * @param ConnectionInterface $from
     * @param string $msg
     * @return bool
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        // decode received data and if the data is not valid, disconnect the client
        $msgData = json_decode($msg);
        // get the message data parts
        $command = $msgData->command;
        if (!$command) $from->close();
        $hash = $msgData->hash;
        $content = $msgData->content;
        $content = trim($content);
        $silent = (isset($msgData->silent)) ? $msgData->silent : false;
        $entityId = (isset($msgData->entityId)) ? (int)$msgData->entityId : false;
        if (!$content || $content == '') {
            if ($command != 'parseMailInput') {
                return true;
            }
        }
        if ($content != 'default' && $command != 'autocomplete' && !$silent) {
            $content = htmLawed($content, ['safe'=>1,'elements'=>'strong, em, strike, u']);
            $response = array(
                'command' => 'echocommand',
                'content' => $content
            );
            $from->send(json_encode($response));
        }
        // get resource id of socket
        $resourceId = $from->resourceId;
        if ($content != 'ticker') {
            $this->logger->log(Logger::INFO, $resourceId . ': ' . $msg);
        }
        // get the current date
        $currentDate = new \DateTime();
        // data ok, check which command was sent
        switch ($command) {
            default:
                break;
            case 'login':
                $username = strtolower($content);
                $user = $this->entityManager->getRepository('TmoAuth\Entity\User')->findOneBy(array(
                    'username' => $username
                ));
                if (!$user) {
                    $this->clientsData[$resourceId]['username'] = $username;
                    $response = array(
                        'command' => 'confirmusercreate',
                    );
                }
                else {
                    $this->clientsData[$resourceId]['username'] = $user->getUsername();
                    $this->clientsData[$resourceId]['userId'] = $user->getId();
                    $this->clientsData[$resourceId]['profileId'] = $user->getProfile()->getId();
                    $response = array(
                        'command' => 'promptforpassword',
                    );
                }
                $from->send(json_encode($response));
                break;
            case 'confirmusercreate':
                if ($content == 'yes' || $content == 'y') {
                    $validator = new Alnum();
                    if ($validator->isValid($this->clientsData[$resourceId]['username'])) {
                        $response = array(
                            'command' => 'createpassword',
                        );
                        $from->send(json_encode($response));
                    }
                    else {
                        $response = array(
                            'command' => 'showmessage',
                            'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Username can only contain alphanumeric characters, please try again</pre>'
                        );
                        $from->send(json_encode($response));
                        $from->close();
                    }
                }
                else {
                    $from->close();
                }
                break;
            case 'createpassword':
                $validator = new Alnum();
                if ($validator->isValid($content)) {
                    $this->clientsData[$resourceId]['tempPassword'] = $content;
                    $response = array(
                        'command' => 'createpasswordconfirm',
                    );
                    $from->send(json_encode($response));
                }
                else {
                    $response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Password can only contain alphanumeric characters, please try again</pre>'
                    );
                    $from->send(json_encode($response));
                    $from->close();
                }
                break;
            case 'createpasswordconfirm':
                $tempPassword = $this->clientsData[$resourceId]['tempPassword'];
                if ($tempPassword != $content) {
                    $response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-warning">The passwords do not match, please confirm again</pre>'
                    );
                    $from->send(json_encode($response));
                    $from->close();
                }
                else {
                    // create a new addy for the user's initial system
                    $addy = $this->utilityService->getRandomAddress(32);
                    $maxTries = 100;
                    $tries = 0;
                    while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
                        $addy = $this->utilityService->getRandomAddress(32);
                        $tries++;
                        if ($tries >= $maxTries) {
                            $response = array(
                                'command' => 'showmessage',
                                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Unable to initialize your account! Please contact an administrator!</pre>'
                            );
                            $from->send(json_encode($response));
                            $from->close();
                            return true;
                        }
                    }
                    // create new user
                    $this->clientsData[$resourceId]['tempPassword'] = false;
                    $user = new User();
                    $user->setUsername(strtolower($this->clientsData[$resourceId]['username']));
                    $user->setDisplayName($this->clientsData[$resourceId]['username']);
                    $user->setEmail(NULL);
                    $user->setState(1);
                    $bcrypt = new Bcrypt();
                    $bcrypt->setCost(10);
                    $pass = $bcrypt->create($content);
                    $user->setPassword($pass);
                    $user->setProfile(NULL);
                    $this->entityManager->persist($user);
                    $profile = new Profile();
                    $profile->setUser($user);
                    $profile->setCredits(ProfileService::DEFAULT_STARTING_CREDITS);
                    $profile->setSnippets(ProfileService::DEFAULT_STARTING_SNIPPETS);
                    // add skills
                    $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
                    foreach ($skills as $skill) {
                        /** @var Skill $skill */
                        $skillRating = new SkillRating();
                        $skillRating->setProfile($profile);
                        $skillRating->setRating($skill->getLevel());
                        $skillRating->setSkill($skill);
                        $this->entityManager->persist($skillRating);
                    }
                    // add default skillpoints
                    $profile->setSkillPoints(ProfileService::DEFAULT_SKILL_POINTS);
                    $this->entityManager->persist($profile);
                    $user->setProfile($profile);
                    $defaultRole = $this->entityManager->find('TmoAuth\Entity\Role', 2);
                    /** @var Role $defaultRole */
                    $user->addRole($defaultRole);
                    $system = new System();
                    $system->setProfile($profile);
                    $system->setName($user->getUsername());
                    $system->setAddy($addy);
                    $this->entityManager->persist($system);
                    // default io node
                    $ioNode = new Node();
                    $ioNode->setCreated(new \DateTime());
                    $ioNode->setLevel(1);
                    $ioNode->setName(Node::STRING_CPU);
                    $ioNode->setSystem($system);
                    $ioNode->setType(Node::ID_CPU);
                    $this->entityManager->persist($ioNode);
                    $profile->setCurrentNode($ioNode);
                    $profile->setHomeNode($ioNode);
                    // flush to db
                    $this->entityManager->flush();
                    $hash = hash('sha256', $this->hash . $user->getId());
                    $this->clientsData[$resourceId]['hash'] = $hash;
                    $this->clientsData[$resourceId]['userId'] = $user->getId();
                    $this->clientsData[$resourceId]['username'] = $user->getUsername();
                    $this->clientsData[$resourceId]['jobs'] = [];
                    $response = array(
                        'command' => 'createuserdone',
                        'hash' => $hash
                    );
                    $from->send(json_encode($response));
                }
                break;
            case 'promptforpassword':
                $user = $this->entityManager->find('TmoAuth\Entity\User', $this->clientsData[$resourceId]['userId']);
                $currentPassword = $user->getPassword();
                $bcrypt = new Bcrypt();
                if (!$bcrypt->verify($content, $currentPassword)) {
                    $response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Invalid password</pre>'
                    );
                    $from->send(json_encode($response));
                    $from->close();
                }
                else {
                    foreach ($this->clients as $client) {
                        if ($client->resourceId != $resourceId && $this->clientsData[$client->resourceId]['username'] == $this->clientsData[$resourceId]['username']) {
                            $loginResponse = array(
                                'command' => 'showmessage',
                                'message' => '<pre style="white-space: pre-wrap;" class="text-danger">Your connection has been terminated because you logged in from another location</pre>'
                            );
                            $client->send(json_encode($loginResponse));
                            $client->close();
                        }
                    }
                    $hash = hash('sha256', $this->hash . $user->getId());
                    $this->clientsData[$resourceId]['hash'] = $hash;
                    $response = array(
                        'command' => 'logincomplete',
                        'hash' => $hash
                    );
                    $from->send(json_encode($response));
                    // message everyone in node
                    $messageText = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has logged in to this node</pre>', $user->getUsername());
                    $message = array(
                        'command' => 'showmessageprepend',
                        'message' => $messageText
                    );
                    $this->codingService->messageEveryoneInNode($user->getProfile()->getCurrentNode(), $message, $user->getProfile());
                }
                break;
            case 'saveNodeDescription':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->nodeService->saveNodeDescription($from, (object)$this->clientsData[$resourceId], $content);
            case 'showprompt':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->showPrompt($from, (object)$this->clientsData[$resourceId]);
            case 'autocomplete':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->autocomplete($from, (object)$this->clientsData[$resourceId], $content);
            case 'parseInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->parserService->parseInput($from, $content, $entityId, $this->loopService->getJobs());
            case 'parseMailInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->parserService->parseMailInput($from, $content, $msgData->mailOptions);
            case 'parseCodeInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                $codeResponse = $this->parserService->parseCodeInput($from, $content, $this->loopService->getJobs());
                if (is_array($codeResponse) && $codeResponse['command'] == 'updateClientData') {
                    $this->clientsData[$resourceId] = (array)$codeResponse['clientData'];
                    $response = array(
                        'command' => 'showmessage',
                        'message' => $codeResponse['message']
                    );
                    $from->send(json_encode($response));
                }
                else {
                    $from->send(json_encode($codeResponse));
                }
                break;
        }
        return true;
    }

    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        unset($this->clientsData[$conn->resourceId]);
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        unset($this->clientsData[$conn->resourceId]);
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

}
