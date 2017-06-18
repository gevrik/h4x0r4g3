<?php

/**
 * Parser Service.
 * The service parses user input and delegates actions to the respective services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Application\Service\WebsocketService;
use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Profile;
use Ratchet\ConnectionInterface;
use TmoAuth\Entity\User;

class ParserService
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var NodeService
     */
    protected $nodeService;

    /**
     * @var ChatService
     */
    protected $chatService;

    /**
     * @var MailMessageService
     */
    protected $mailMessageService;

    /**
     * @var ProfileService
     */
    protected $profileService;

    /**
     * @var CodingService
     */
    protected $codingService;

    /**
     * @var SystemService
     */
    protected $systemService;

    /**
     * @var ConnectionService
     */
    protected $connectionService;

    /**
     * @var NotificationService
     */
    protected $notificationService;


    /**
     * @param EntityManager $entityManager
     * @param FileService $fileService
     * @param NodeService $nodeService
     * @param ChatService $chatService
     * @param MailMessageService $mailMessageService
     * @param ProfileService $profileService
     * @param CodingService $codingService
     * @param SystemService $systemService
     * @param ConnectionService $connectionService
     * @param NotificationService $notificationService
     */
    public function __construct(
        EntityManager $entityManager,
        FileService $fileService,
        NodeService $nodeService,
        ChatService $chatService,
        MailMessageService $mailMessageService,
        ProfileService $profileService,
        CodingService $codingService,
        SystemService $systemService,
        ConnectionService $connectionService,
        NotificationService $notificationService
    )
    {
        $this->entityManager = $entityManager;
        $this->fileService = $fileService;
        $this->nodeService = $nodeService;
        $this->chatService = $chatService;
        $this->mailMessageService = $mailMessageService;
        $this->profileService = $profileService;
        $this->codingService = $codingService;
        $this->systemService = $systemService;
        $this->connectionService = $connectionService;
        $this->notificationService = $notificationService;
    }

    /**
     * @return WebsocketService
     */
    private function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * Main method that takes care of delegating commands to their corresponding service.
     * @param ConnectionInterface $from
     * @param string $content
     * @param int|bool $entityId
     * @param bool $jobs
     * @return bool|ConnectionInterface
     */
    public function parseInput(ConnectionInterface $from, $content = '', $entityId = false, $jobs = false)
    {
        $clientData = $this->getWebsocketServer()->getClientData($from->resourceId);
        $response = false;
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        switch ($userCommand) {
            default:
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Unknown command</pre>')
                );
                break;
            case 'clear':
                $response = array(
                    'command' => 'clear',
                    'message' => 'default'
                );
                break;
            case 'addnode':
                $response = $this->nodeService->addNode($from->resourceId);
                break;
            case 'addconnection':
                $response = $this->connectionService->addConnection($from->resourceId, $contentArray);
                break;
            case 'cd':
                $response = $this->connectionService->useConnection($from->resourceId, $contentArray);
                break;
            case 'code':
                $response = $this->codingService->enterCodeMode($from->resourceId);
                break;
            case 'commands':
                $response = $this->showCommands($from->resourceId);
                break;
            case 'connect':
                $response = $this->nodeService->systemConnect($from->resourceId, $contentArray);
                break;
            case 'ticker':
                $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
                if (!$user) return true;
                /** @var User $user */
                $profile = $user->getProfile();
                /** @var Profile $profile */
                $countUnreadNotifications = $this->entityManager->getRepository('Netrunners\Entity\Notification')->countUnreadByProfile($profile);
                $response = array(
                    'command' => 'ticker',
                    'amount' => $countUnreadNotifications
                );
                break;
            case 'shownotifications':
                $response = $this->notificationService->showNotifications($from->resourceId);
                break;
            case 'dismissnotification':
                $this->notificationService->dismissNotification($from->resourceId, $entityId);
                break;
            case 'dismissallnotifications':
                $this->notificationService->dismissNotification($from->resourceId, $entityId, true);
                break;
            case 'editnode':
                $response = $this->nodeService->editNodeDescription($from->resourceId);
                break;
            case 'exe':
            case 'execute':
                $response = $this->fileService->executeFile($from->resourceId, $contentArray);
                break;
            case 'fn':
            case 'filename':
                $response = $this->fileService->changeFileName($from->resourceId, $contentArray);
                break;
            case 'gc':
                return $this->chatService->globalChat($from->resourceId, $contentArray);
            case 'home':
            case 'homerecall':
                $response = $this->systemService->homeRecall($from->resourceId);
                break;
            case 'i':
            case 'inv':
            case 'inventory':
                $response = $this->profileService->showInventory($from->resourceId);
                break;
            case 'kill':
                $response = $this->fileService->killProcess($from->resourceId, $contentArray);
                break;
            case 'jobs':
                $response = $this->profileService->showJobs($from->resourceId, $jobs);
                break;
            case 'ls':
                $response = $this->nodeService->showNodeInfo($from->resourceId);
                break;
            case 'mail':
                $response = $this->mailMessageService->enterMailMode($from->resourceId);
                break;
            case 'map':
                if ($profile->getCurrentNode()->getSystem()->getProfile() != $profile) {
                    $response = $this->systemService->showAreaMap($from->resourceId);
                }
                else {
                    $response = $this->systemService->showSystemMap($from->resourceId);
                }
                break;
            case 'nodename':
                $response = $this->nodeService->changeNodeName($from->resourceId, $contentArray);
                break;
            case 'nodes':
                $response = $this->nodeService->listNodes($from->resourceId);
                break;
            case 'nodetype':
                $response = $this->nodeService->changeNodeType($from->resourceId, $contentArray);
                break;
            case 'removenode':
                $response = $this->nodeService->removeNode($from->resourceId);
                break;
            case 'parts':
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($from->resourceId);
                break;
            case 'ps':
                $response = $this->fileService->listProcesses($from->resourceId);
                break;
            case 'score':
                $response = $this->profileService->showScore($from->resourceId);
                break;
            case 'secureconnection':
                $response = $this->connectionService->secureConnection($from->resourceId, $contentArray);
                break;
            case 'skills':
                $response = $this->profileService->showSkills($from->resourceId);
                break;
            case 'showunreadmails':
                $response = $this->mailMessageService->displayAmountUnreadMails($from->resourceId);
                break;
            case 'stat':
                $response = $this->fileService->statFile($from->resourceId, $contentArray);
                break;
            case 'survey':
                $response = $this->nodeService->surveyNode($from->resourceId);
                break;
            case 'system':
                $response = $this->systemService->showSystemStats($from->resourceId);
                break;
            case 'time':
                $now = new \DateTime();
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">current server time: %s</pre>', $now->format('Y/m/d H:i:s'))
                );
                break;
            case 'touch':
                $response = $this->fileService->touchFile($from->resourceId, $contentArray);
                break;
            /** ADMIN STUFF */
        }
        if ($response) $from->send(json_encode($response));
        return true;
    }

    /**
     * Method that takes care of delegating commands within the mail console mode.
     * @param ConnectionInterface $from
     * @param string $content
     * @param array $mailOptions
     * @return bool|ConnectionInterface
     */
    public function parseMailInput(ConnectionInterface $from, $content = '', $mailOptions = array())
    {
        $clientData = $this->getWebsocketServer()->getClientData($from->resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $mailOptions = (object)$mailOptions;
        switch ($userCommand) {
            default:
                $response = $this->mailMessageService->displayMail($from->resourceId, $mailOptions);
                break;
            case 'd':
                $response = $this->mailMessageService->deleteMail($from->resourceId, $contentArray, $mailOptions);
                break;
            case 'q':
                $response = $this->mailMessageService->exitMailMode();
                break;
        }
        return $from->send(json_encode($response));
    }

    public function parseCodeInput(ConnectionInterface $from, $content = '', $jobs = false)
    {
        $clientData = $this->getWebsocketServer()->getClientData($from->resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $codeOptions = (object)$clientData->codingOptions;
        switch ($userCommand) {
            default:
            case 'options':
                $response = $this->codingService->commandOptions($from->resourceId, $codeOptions);
                break;
            case 'code':
                return $this->codingService->commandCode($from->resourceId, $codeOptions);
            case 'jobs':
                $response = $this->profileService->showJobs($from->resourceId, $jobs);
                break;
            case 'level':
                $response = $this->codingService->commandLevel($from->resourceId, $contentArray);
                break;
            case 'mode':
                $response = $this->codingService->switchCodeMode($from->resourceId, $contentArray);
                break;
            case 'parts':
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($from->resourceId);
                break;
            case 'type':
                $response = $this->codingService->commandType($from->resourceId, $contentArray, $codeOptions);
                break;
            case 'q':
                $response = $this->codingService->exitCodeMode();
                break;
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showCommands($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s%-20s<br /></pre>',
            'clear',
            'code',
            'commands',
            'gc',
            'kill',
            'mail',
            'ps',
            'score',
            'skills',
            'showunreadmails',
            'stat',
            'system');
        $response = array(
            'command' => 'score',
            'message' => $returnMessage
        );
        return $response;
    }

}
