<?php

namespace Application\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Repository\BannedIpRepository;
use Netrunners\Service\LoginService;
use Netrunners\Service\LoopService;
use Netrunners\Service\ManpageService;
use Netrunners\Service\NodeService;
use Netrunners\Service\ParserService;
use Netrunners\Service\UtilityService;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Zend\Log\Logger;
use Zend\Validator\Ip;

class WebsocketService implements MessageComponentInterface {

    /**
     * @const LOOP_TIME_JOBS the amount of seconds between coding job checks
     */
    const LOOP_TIME_JOBS = 1;

    /**
     * @const LOOP_TIME_RESOURCES the amount of seconds between resource gain checks
     */
    const LOOP_TIME_RESOURCES = 900;

    /**
     * @const LOOP_NPC_SPAWN the amount of seconds between npc spawn checks
     */
    const LOOP_NPC_SPAWN = 300;

    /**
     * @const LOOP_NPC_ROAM the amount of seconds between npc roaming checks
     */
    const LOOP_NPC_ROAM = 30;

    /**
     * @const MAX_CLIENTS the maximum amount of clients that can be connected at the same time
     */
    const MAX_CLIENTS = 5;

    /**
     * @var WebsocketService
     */
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
     * @var bool
     */
    protected $adminMode = false;

    /**
     * @var LoopInterface
     */
    protected $loop;

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
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $hash;


    /**
     * WebsocketService constructor.
     * @param EntityManager $entityManager
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param LoopService $loopService
     * @param NodeService $nodeService
     * @param LoginService $loginService
     * @param ManpageService $manpageService
     * @param LoopInterface $loop
     * @param $hash
     * @param $adminMode
     */
    public function __construct(
        EntityManager $entityManager,
        UtilityService $utilityService,
        ParserService $parserService,
        LoopService $loopService,
        NodeService $nodeService,
        LoginService $loginService,
        ManpageService $manpageService,
        LoopInterface $loop,
        $hash,
        $adminMode
    ) {
        $this->clients = new \SplObjectStorage;
        $this->entityManager = $entityManager;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->loopService = $loopService;
        $this->nodeService = $nodeService;
        $this->loginService = $loginService;
        $this->manpageService = $manpageService;
        $this->loop = $loop;
        $this->hash = $hash;
        $this->setAdminMode($adminMode);

        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, array('stream' => getcwd() . '/data/log/command_log.txt'));

        $this->loop->addPeriodicTimer(self::LOOP_TIME_JOBS, function(){
            $this->loopService->loopJobs();
        });

        $this->loop->addPeriodicTimer(self::LOOP_TIME_RESOURCES, function(){
            $this->loopService->loopResources();
        });

        $this->loop->addPeriodicTimer(self::LOOP_NPC_SPAWN, function(){
            $this->loopService->loopNpcSpawn();
        });

        $this->loop->addPeriodicTimer(self::LOOP_NPC_ROAM, function(){
            $this->loopService->loopNpcRoam();
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
     * @return mixed
     */
    public function getHash()
    {
        return $this->hash;
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
     * @param $resourceId
     * @param $key
     * @param $value
     * @return $this
     */
    public function setClientData($resourceId, $key, $value)
    {
        $this->clientsData[$resourceId][$key] = $value;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAdminMode()
    {
        return $this->adminMode;
    }

    /**
     * @param bool $adminMode
     * @return WebsocketService
     */
    public function setAdminMode($adminMode)
    {
        $this->adminMode = $adminMode;
        return $this;
    }

    /**
     * @return UtilityService
     */
    public function getUtilityService()
    {
        return $this->utilityService;
    }

    /**
     * @return NodeService
     */
    public function getNodeService()
    {
        return $this->nodeService;
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
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $conn->resourceId;
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        echo "New connection! ({$resourceId})\n";
        $this->clientsData[$resourceId] = array(
            'socketId' => $resourceId,
            'username' => false,
            'userId' => false,
            'hash' => false,
            'tempPassword' => false,
            'profileId' => false,
            'ipaddy' => false,
            'codingOptions' => [
                'fileType' => 0,
                'fileLevel' => 0,
                'mode' => 'resource'
            ],
            'action' => [],
            'milkrun' => [],
            'hangman' => [],
            'codebreaker' => []
        );
        $response = array(
            'command' => 'getipaddy',
            'message' => 'default'
        );
        $conn->send(json_encode($response));
    }

    /**
     * @param $start
     * @param null $end
     * @return float
     */
    public function microtime_diff($start, $end = null)
    {
        if (!$end) {
            $end = microtime();
        }
        list($start_usec, $start_sec) = explode(" ", $start);
        list($end_usec, $end_sec) = explode(" ", $end);
        $diff_sec = intval($end_sec) - intval($start_sec);
        $diff_usec = floatval($end_usec) - floatval($start_usec);
        return floatval($diff_sec) + $diff_usec;
    }

    /**
     * @param ConnectionInterface $from
     * @param string $msg
     * @return bool
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        // get resource id of socket
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        // decode received data and if the data is not valid, disconnect the client
        $msgData = json_decode($msg);
        // check if we have everything that we need in the $msgData
        if (!is_object($msgData) || !isset($msgData->command) || !isset($msgData->hash) || !isset($msgData->content)) {
            $this->logger->log(Logger::ALERT, $resourceId . ': SOCKET IS SENDING GIBBERISH - GET RID OF THEM - ' . $msg);
            $from->close();
            return true;
        }
        // get the message data parts
        $command = $msgData->command;
        // check if socket is spamming messages
        if (!isset($this->clientsData[$resourceId]['millis'])) {
            $this->clientsData[$resourceId]['millis'] = microtime();
            $this->clientsData[$resourceId]['spamcount'] = 0;
        }
        else {
            if ($command != 'ticker') {
                $querytime = $this->microtime_diff($this->clientsData[$resourceId]['millis']);
                if ($querytime <= 0.2) {
                    $this->clientsData[$resourceId]['spamcount']++;
                    if ($this->clientsData[$resourceId]['spamcount'] >= mt_rand(5, 10)) {
                        $this->logger->log(Logger::ALERT, $resourceId . ': SOCKET IS SPAMMING - DISCONNECT SOCKET - ' . $msg);
                        $response = array(
                            'command' => 'showmessage',
                            'message' => '<pre style="white-space: pre-wrap;" class="text-danger">DISCONNECTED - REASON: SPAMMING</pre>'
                        );
                        $from->send(json_encode($response));
                        $from->close();
                        return true;
                    }
                }
                else {
                    $this->clientsData[$resourceId]['millis'] = microtime();
                    $this->setClientData($resourceId, 'spamcount', 0);
                }
            }
        }
        // init vars
        $hash = $msgData->hash;
        $content = $msgData->content;
        if ($command != 'saveManpage') {
            $content = trim($content);
            $content = htmLawed($content, ['safe'=>1,'elements'=>'strong']);
        }
        $silent = (isset($msgData->silent)) ? $msgData->silent : false;
        $entityId = (isset($msgData->entityId)) ? (int)$msgData->entityId : false;
        if (!$content || $content == '') {
            if ($command != 'parseMailInput') {
                return true;
            }
        }
        if (!$silent) {
            $response = array(
                'command' => 'echocommand',
                'content' => $content
            );
            $from->send(json_encode($response));
        }
        if ($content != 'ticker') {
            $this->logger->log(Logger::INFO, $resourceId . ': ' . $msg);
        }
        // check if we know the ip addy of the socket - if not, disconnect them
        if ($command != 'setipaddy') {
            if (!$this->clientsData[$resourceId]['ipaddy']) {
                $this->logger->log(Logger::ALERT, $resourceId . ': SOCKET WITH NO IP ADDY INFO IS SENDING COMMANDS - DISCONNECT SOCKET');
                $from->close();
                return true;
            }
        }
        // data ok, check which command was sent
        switch ($command) {
            default:
                break;
            case 'setipaddy':
                $validator = new Ip();
                if ($validator->isValid($content)) {
                    // check if the ip is banned
                    $bannedIpRepo = $this->entityManager->getRepository('Netrunners\Entity\BannedIp');
                    /** @var BannedIpRepository $bannedIpRepo */
                    $bannedIpEntry = $bannedIpRepo->findOneBy([
                        'ip' => $content
                    ]);
                    if ($bannedIpEntry) {
                        $response = [
                            'command' => 'showmessage',
                            'message' => sprintf(
                                '<pre style="white-space: pre-wrap;" class="text-danger">This IP address has been banned!</pre>'
                            )
                        ];
                        $from->send(json_encode($response));
                        $from->close();
                        return true;
                    }
                    // not banned, set ip addy
                    $this->clientsData[$resourceId]['ipaddy'] = $content;
                } else {
                    $this->logger->log(Logger::ALERT, $resourceId . ': SOMETHING FISHY GOING ON - NO IP ADDRESS COULD BE FOUND - DISCONNECT SOCKET');
                    $from->close();
                    return true;
                }
                break;
            case 'login':
                list($response, $disconnect) = $this->loginService->login($resourceId, $content);
                $from->send(json_encode($response));
                if ($disconnect) $from->close();
                break;
            case 'confirmusercreate':
                list($disconnect, $response) = $this->loginService->confirmUserCreate($resourceId, $content);
                $from->send(json_encode($response));
                if ($disconnect) {
                    $from->close();
                }
                break;
            case 'createpassword':
                list($disconnect, $response) = $this->loginService->createPassword($resourceId, $content);
                $from->send(json_encode($response));
                if ($disconnect) {
                    $from->close();
                }
                break;
            case 'createpasswordconfirm':
                list($disconnect, $response) = $this->loginService->createPasswordConfirm($resourceId, $content);
                $from->send(json_encode($response));
                if ($disconnect) {
                    $from->close();
                }
                break;
            case 'promptforpassword':
                list($disconnect, $response) = $this->loginService->promptForPassword($resourceId, $content);
                $from->send(json_encode($response));
                if ($disconnect) {
                    $from->close();
                }
                break;
            case 'saveNodeDescription':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                $response = $this->nodeService->saveNodeDescription($resourceId, $content);
                $from->send(json_encode($response));
                break;
            case 'saveManpage':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                $mpTitle = (isset($msgData->title)) ? $msgData->title : false;
                var_dump($mpTitle);
                var_dump($content);
                $response = $this->manpageService->saveManpage($resourceId, $content, $mpTitle, $entityId);
                $from->send(json_encode($response));
                break;
            case 'showprompt':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->showPrompt($this->getClientData($resourceId));
            case 'autocomplete':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->autocomplete($from, (object)$this->clientsData[$resourceId], $content);
            case 'parseInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->parserService->parseInput($from, $content, $entityId, $this->loopService->getJobs(), $silent);
            case 'parseMailInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->parserService->parseMailInput($from, $content, $msgData->mailOptions);
            case 'parseCodeInput':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                $from->send(json_encode($this->parserService->parseCodeInput($from, $content, $this->loopService->getJobs())));
                break;
        }
        return true;
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $conn->resourceId;
        // The connection is closed, remove it, as we can no longer send it messages
        unset($this->clientsData[$resourceId]);
        $this->clients->detach($conn);
        echo "Connection {$resourceId} has disconnected\n";
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        unset($this->clientsData[$conn->resourceId]);
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

}
