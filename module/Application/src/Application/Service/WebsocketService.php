<?php

namespace Application\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Service\LoginService;
use Netrunners\Service\LoopService;
use Netrunners\Service\NodeService;
use Netrunners\Service\ParserService;
use Netrunners\Service\UtilityService;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Zend\Log\Logger;
use Zend\Validator\Ip;

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
     * @var Logger
     */
    protected $logger;

    /**
     * @var
     */
    protected $hash;

    /**
     * @param EntityManager $entityManager
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param LoopService $loopService
     * @param NodeService $nodeService
     * @param LoginService $loginService
     * @param LoopInterface $loop
     * @param $hash
     */
    public function __construct(
        EntityManager $entityManager,
        UtilityService $utilityService,
        ParserService $parserService,
        LoopService $loopService,
        NodeService $nodeService,
        LoginService $loginService,
        LoopInterface $loop,
        $hash
    ) {
        $this->clients = new \SplObjectStorage;
        $this->entityManager = $entityManager;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->loopService = $loopService;
        $this->nodeService = $nodeService;
        $this->loginService = $loginService;
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
            ]
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
                    if ($this->clientsData[$resourceId]['spamcount'] >= 10) {
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
                }
            }
        }
        // init vars
        $hash = $msgData->hash;
        $content = $msgData->content;
        $content = trim($content);
        $content = htmLawed($content, ['safe'=>1,'elements'=>'strong']);
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
                    $this->clientsData[$resourceId]['ipaddy'] = $content;
                } else {
                    $this->logger->log(Logger::ALERT, $resourceId . ': SOMETHING FISHY GOING ON - NO IP ADDRESS COULD BE FOUND - DISCONNECT SOCKET');
                    $from->close();
                }
                break;
            case 'login':
                $response = $this->loginService->login($resourceId, $content);
                $from->send(json_encode($response));
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
                return $this->nodeService->saveNodeDescription($from, (object)$this->clientsData[$resourceId], $content);
            case 'showprompt':
                if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                return $this->utilityService->showPrompt($this->getClientData($resourceId));
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
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $conn->resourceId;
        // The connection is closed, remove it, as we can no longer send it messages
        unset($this->clientsData[$resourceId]);
        $this->clients->detach($conn);
        echo "Connection {$resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        unset($this->clientsData[$conn->resourceId]);
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

}
