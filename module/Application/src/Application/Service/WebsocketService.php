<?php

namespace Application\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Service\CodingService;
use Netrunners\Service\LoopService;
use Netrunners\Service\ParserService;
use Netrunners\Service\ProfileService;
use Netrunners\Service\SystemService;
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
     * @var Logger
     */
    protected $logger;

    /**
     * @param EntityManager $entityManager
     * @param ProfileService $profileService
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param CodingService $codingService
     * @param LoopService $loopService
     * @param LoopInterface $loop
     */
    public function __construct(
        EntityManager $entityManager,
        ProfileService $profileService,
        UtilityService $utilityService,
        ParserService $parserService,
        CodingService $codingService,
        LoopService $loopService,
        LoopInterface $loop
    ) {
        $this->clients = new \SplObjectStorage;
        $this->entityManager = $entityManager;
        $this->profileService = $profileService;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->codingService = $codingService;
        $this->loopService = $loopService;
        $this->loop = $loop;

        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, array('stream' => getcwd() . '/data/log/command_log.txt'));

        $this->loop->addPeriodicTimer(5, function(){
            $this->loopService->loopJobs();
        });

    }

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
            'profileId' => false
        );
    }

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
            $content = htmlentities($content);
            $response = array(
                'command' => 'echoCommand',
                'content' => $content
            );
            $from->send(json_encode($response));
        }
        // get resource id of socket
        $resourceId = $from->resourceId;
        $this->logger->log(Logger::INFO, $resourceId . ': ' . $msg);
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
                        'command' => 'confirmUserCreate',
                    );
                }
                else {
                    $this->clientsData[$resourceId]['username'] = $user->getUsername();
                    $this->clientsData[$resourceId]['userId'] = $user->getId();
                    $this->clientsData[$resourceId]['profileId'] = $user->getProfile()->getId();
                    $response = array(
                        'command' => 'promptForPassword',
                    );
                }
                $from->send(json_encode($response));
                break;
            case 'confirmUserCreate':
                if ($content == 'yes' || $content == 'y') {
                    $validator = new Alnum();
                    if ($validator->isValid($this->clientsData[$resourceId]['username'])) {
                        $response = array(
                            'command' => 'createPassword',
                        );
                        $from->send(json_encode($response));
                    }
                    else {
                        $response = array(
                            'command' => 'showMessage',
                            'type' => 'warning',
                            'message' => 'Username can only contain alphanumeric characters, please try again'
                        );
                        $from->send(json_encode($response));
                        $from->close();
                    }
                }
                else {
                    $from->close();
                }
                break;
            case 'createPassword':
                $validator = new Alnum();
                if ($validator->isValid($content)) {
                    $this->clientsData[$resourceId]['tempPassword'] = $content;
                    $response = array(
                        'command' => 'createPasswordConfirm',
                    );
                    $from->send(json_encode($response));
                }
                else {
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'warning',
                        'message' => 'Password can only contain alphanumeric characters, please try again'
                    );
                    $from->send(json_encode($response));
                    $from->close();
                }
                break;
            case 'createPasswordConfirm':
                $tempPassword = $this->clientsData[$resourceId]['tempPassword'];
                if ($tempPassword != $content) {
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'warning',
                        'message' => 'The passwords do not match, please confirm again'
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
                                'command' => 'showMessage',
                                'type' => 'warning',
                                'message' => 'Unable to initialize your account! Please contact an administrator!'
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
                    $profile->setSkillAdvancedCoding(0);
                    $profile->setSkillAdvancedNetworking(0);
                    $profile->setSkillBlackhat(20);
                    $profile->setSkillCoding(30);
                    $profile->setSkillComputing(30);
                    $profile->setSkillCryptography(0);
                    $profile->setSkillDatabases(0);
                    $profile->setSkillElectronics(0);
                    $profile->setSkillForensics(0);
                    $profile->setSkillNetworking(0);
                    $profile->setSkillReverseEngineering(0);
                    $profile->setSkillSocialEngineering(0);
                    $profile->setSkillWhitehat(0);
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
                    // flush to db
                    $this->entityManager->flush();
                    $hash = hash('sha256', 'netrunners' . $user->getId());
                    $this->clientsData[$resourceId]['hash'] = $hash;
                    $this->clientsData[$resourceId]['userId'] = $user->getId();
                    $this->clientsData[$resourceId]['username'] = $user->getUsername();
                    $this->clientsData[$resourceId]['jobs'] = [];
                    $response = array(
                        'command' => 'createUserDone',
                        'hash' => $hash
                    );
                    $from->send(json_encode($response));
                }
                break;
            case 'promptForPassword':
                $user = $this->entityManager->find('TmoAuth\Entity\User', $this->clientsData[$resourceId]['userId']);
                $currentPassword = $user->getPassword();
                $bcrypt = new Bcrypt();
                if (!$bcrypt->verify($content, $currentPassword)) {
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'danger',
                        'message' => 'Invalid password'
                    );
                    $from->send(json_encode($response));
                    $from->close();
                }
                else {
                    foreach ($this->clients as $client) {
                        if ($client->resourceId != $resourceId && $this->clientsData[$client->resourceId]['username'] == $this->clientsData[$resourceId]['username']) {
                            $loginResponse = array(
                                'command' => 'showMessage',
                                'type' => 'danger',
                                'message' => 'Your connection has been terminated because you logged in from another location'
                            );
                            $client->send(json_encode($loginResponse));
                            $client->close();
                        }
                    }
                    $hash = hash('sha256', 'netrunners' . $user->getId());
                    $this->clientsData[$resourceId]['hash'] = $hash;
                    $response = array(
                        'command' => 'loginComplete',
                        'hash' => $hash
                    );
                    $from->send(json_encode($response));
                }
                break;
            case 'showPrompt':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->showPrompt($from, (object)$this->clientsData[$resourceId]);
            case 'autocomplete':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->autocomplete($from, (object)$this->clientsData[$resourceId], $content);
            case 'parseInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->parserService->parseInput($from, (object)$this->clientsData[$from->resourceId], $content, $this->clients, $this->clientsData, $entityId);
            case 'parseMailInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->parserService->parseMailInput($from, (object)$this->clientsData[$from->resourceId], $content, $this->clients, $this->clientsData, $msgData->mailOptions);
            case 'parseCodeInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                $codeResponse = $this->parserService->parseCodeInput($from, (object)$this->clientsData[$from->resourceId], $content, $this->clients, $this->clientsData, $msgData->codeOptions);
                if (is_array($codeResponse) && $codeResponse['command'] == 'updateClientData') {
                    $this->clientsData[$resourceId] = (array)$codeResponse['clientData'];
                    $response = array(
                        'command' => 'showMessage',
                        'type' => $codeResponse['type'],
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
