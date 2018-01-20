<?php

namespace Netrunners\Model;

use Application\Service\WebsocketService;

class GameClientResponse {

    const CLASS_SYSMSG = 'sysmsg';
    const CLASS_MUTED = 'muted';
    const CLASS_WHITE = 'white';
    const CLASS_WARNING = 'warning';
    const CLASS_DANGER = 'danger';
    const CLASS_INFO = 'info';
    const CLASS_SUCCESS = 'success';
    const CLASS_ADDON = 'addon';
    const CLASS_SURVEY = 'survey';
    const CLASS_DIRECTORY = 'directory';
    const CLASS_EXECUTABLE = 'executable';
    const CLASS_ATTENTION = 'attention';
    const CLASS_USERS = 'users';
    const CLASS_NPCS = 'npcs';
    const CLASS_RAW = 'raw';

    const COMMAND_FLYTO = 'flytocoords';
    const COMMAND_SHOWMESSAGE = 'showmessage';
    const COMMAND_SHOWOUTPUT = 'showoutput';
    const COMMAND_SHOWOUTPUT_PREPEND = 'showoutputprepend';
    const COMMAND_SHOWMAP = 'showmap';
    const COMMAND_ENTERCODEMODE = 'entercodemode';
    const COMMAND_EXITCODEMODE = 'exitcodemode';
    const COMMAND_ENTERCONFIRMMODE = 'enterconfirmmode';
    const COMMAND_CLOSEPANEL = 'closepanel';
    const COMMAND_SHOWPANEL = 'showpanel';
    const COMMAND_ENTERMAILMODE = 'entermailmode';
    const COMMAND_EXITMAILMODE = 'exitmailmode';
    const COMMAND_OPENMANPAGEMENU = 'openmanpagemenu';
    const COMMAND_STARTMILKRUN = 'startmilkrun';
    const COMMAND_STOPMILKRUN = 'stopmilkrun';
    const COMMAND_COMPLETEMILKRUN = 'completemilkrun';
    const COMMAND_SETOPACITY = 'setbgopacity';
    const COMMAND_GETRANDOMGEOCOORDS = 'getrandomgeocoords';
    const COMMAND_UPDATEPROMPT = 'updateprompt';
    const COMMAND_UPDATEDIVHTML = 'updatedivhtml';
    const COMMAND_CONFIRMUSERCREATE = 'confirmusercreate';
    const COMMAND_PROMPTFORPASSWORD = 'promptforpassword';
    const COMMAND_SOLVECAPTCHA = 'solvecaptcha';
    const COMMAND_ENTERINVITATIONCODE = 'enterinvitationcode';
    const COMMAND_CREATEPASSWORD = 'createpassword';
    const COMMAND_CREATEPASSWORDCONFIRM = 'createpasswordconfirm';
    const COMMAND_CREATEUSERDONE = 'createuserdone';
    const COMMAND_LOGINCOMPLETE = 'logincomplete';
    const COMMAND_ECHOCOMMAND = 'echocommand';
    const COMMAND_GETIPADDY = 'getipaddy';

    const INVALID_MESSAGE = 'unknown command';

    const OPT_CONTENT = 'content';
    const OPT_MOVED = 'moved';
    const OPT_TIMER = 'deadline';
    const OPT_MAIL_NUMBER = 'mailNumber';
    const OPT_LEVEL = 'level';
    const OPT_EEG = 'eeg';
    const OPT_ATTACK = 'attack';
    const OPT_ARMOR = 'armor';
    const OPT_MUSIC = 'music';
    const OPT_DISCONNECTX = 'disconnectx';
    const OPT_CLEARDEADLINE = 'cleardeadline';
    const OPT_EXITCONFIRMMODE = 'exitconfirmmode';
    const OPT_ELEMENT = 'element';
    const OPT_PLAYSOUND = 'playsound';
    const OPT_HASH = 'hash';
    const OPT_HOMECOORDS = 'homecoords';
    const OPT_GEOCOORDS = 'geocoords';
    const OPT_BGOPACITY = 'bgopacity';

    /**
     * @var int
     */
    private $resourceId;

    /**
     * @var bool|string
     */
    private $command;

    /**
     * @var array
     */
    private $messages;

    /**
     * @var bool
     */
    private $silent;

    /**
     * @var array
     */
    private $options;

    /**
     * @var string
     */
    private $prompt;

    /**
     * @var bool|array
     */
    private $response = false;


    /**
     * GameClientResponse constructor.
     * @param int|null $resourceId
     * @param string $command
     * @param array $messages
     * @param bool $silent
     * @param array $options
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function __construct(
        $resourceId = NULL,
        $command = self::COMMAND_SHOWOUTPUT,
        $messages = [],
        $silent = false,
        $options = []
    )
    {
        $this->resourceId = $resourceId;
        $this->reset($command, $messages, $silent, $options);
    }

    /**
     * @return WebsocketService
     */
    private function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * @param array $messages
     * @param string $class
     * @return GameClientResponse
     */
    public function addMessages($messages = [], $class = self::CLASS_WHITE)
    {
        if (is_array($messages)) {
            foreach ($messages as $message) {
                $this->addMessage($message, $class);
            }
        }
        else {
            var_dump($messages);
        }
        return $this;
    }

    /**
     * @param bool|string $text
     * @param string $class
     * @return GameClientResponse
     */
    public function addMessage($text = '', $class = self::CLASS_WARNING)
    {
        if (!empty($text)) {
            $this->messages[] = [
                'text' => $text,
                'class' => $class
            ];
        }
        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return GameClientResponse
     */
    public function addOption($key = '', $value)
    {
        if (!empty($key)) {
            $this->options[$key] = $value;
        }
        return $this;
    }

    /**
     * @param string $command
     * @param array $messages
     * @param bool $silent
     * @param array $options
     * @return GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function reset($command = self::COMMAND_SHOWOUTPUT, $messages = [], $silent = false, $options = [])
    {
        $this->command = $command;
        $this->messages = $messages;
        $this->silent = $silent;
        $this->options = $options;
        $clientData = ($this->resourceId) ? $this->getWebsocketServer()->getClientData($this->resourceId) : NULL;
        $this->prompt = ($clientData) ? $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData) : NULL;
        return $this;
    }

    /**
     * @return $this
     */
    public function create()
    {
        $messages = [];
        // send unknown command if no messages are stored
        if (empty($this->messages)) {
            $this->messages[] = ['text' => self::INVALID_MESSAGE, 'class' => self::CLASS_DANGER];
        }
        // populate returned messages
        foreach ($this->messages as $message) {
            if ($message['class'] == self::CLASS_RAW) {
                $messages[] = $message['text'];
            }
            else {
                $messages[] = sprintf('<pre style="white-space: pre-wrap;" class="text-%s">%s</pre>', $message['class'], $message['text']);
            }
        }
        // set response
        $this->response = [
            'command' => $this->command,
            'message' => $messages,
            'silent' => $this->silent,
            'prompt' => $this->prompt,
        ];
        // add options to response if given
        if (!empty($this->options)) {
            foreach ($this->options as $key => $value) {
                $this->response[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * @param bool $resourceId
     * @return GameClientResponse
     */
    public function send($resourceId = false)
    {
        $ws = $this->getWebsocketServer();
        if ($resourceId) $this->resourceId = $resourceId;
        if ($this->resourceId) {
            $this->create();
            foreach ($ws->getClients() as $wsClient) {
                if ($wsClient->resourceId == $this->resourceId) {
                    $wsClient->send(json_encode($this->response));
                    break;
                }
            }
        }
        return $this;
    }

    /**
     * @return bool|int
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * @param bool|int $resourceId
     * @return GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function setResourceId($resourceId)
    {
        $this->resourceId = $resourceId;
        $clientData = ($this->resourceId) ? $this->getWebsocketServer()->getClientData($resourceId) : NULL;
        $this->prompt = ($clientData) ? $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData) : NULL;
        return $this;
    }

    /**
     * @return bool|string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param bool|string $command
     * @return GameClientResponse
     */
    public function setCommand($command)
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSilent()
    {
        return $this->silent;
    }

    /**
     * @param bool $silent
     * @return GameClientResponse
     */
    public function setSilent($silent)
    {
        $this->silent = $silent;
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array $options
     * @return GameClientResponse
     */
    public function setOptions($options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * @return array|bool
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param array|bool $response
     * @return GameClientResponse
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param array $messages
     * @return GameClientResponse
     */
    public function setMessages($messages)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * @return string
     */
    public function getPrompt()
    {
        return $this->prompt;
    }

    /**
     * @param string $prompt
     * @return GameClientResponse
     */
    public function setPrompt($prompt)
    {
        $this->prompt = $prompt;
        return $this;
    }

}
