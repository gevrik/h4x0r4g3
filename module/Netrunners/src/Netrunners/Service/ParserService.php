<?php

/**
 * Parser Service.
 * The service parses user input and delegates actions to the respective services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Profile;
use Netrunners\Repository\FileRepository;
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
     * Main method that takes care of delegating commands to their corresponding service.
     * @param ConnectionInterface $from
     * @param $clientData
     * @param string $content
     * @param \SplObjectStorage $wsClients
     * @param array $wsClientsData
     * @param bool $entityId
     * @return bool|ConnectionInterface
     */
    public function parseInput(ConnectionInterface $from, $clientData, $content = '', \SplObjectStorage $wsClients, $wsClientsData = array(), $entityId = false, $jobs = false)
    {
        $response = false;
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        switch ($userCommand) {
            default:
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'sysmsg',
                    'message' => 'Unknown command'
                );
                break;
            case 'clear':
                $response = array(
                    'command' => 'clear',
                    'message' => 'default'
                );
                break;
            case 'addnode':
                $response = $this->nodeService->addNode($clientData);
                break;
            case 'addconnection':
                $response = $this->connectionService->addConnection($clientData, $contentArray);
                break;
            case 'cd':
                $response = $this->connectionService->useConnection($clientData, $contentArray, $wsClientsData, $wsClients);
                break;
            case 'code':
                $response = $this->codingService->enterCodeMode($clientData);
                break;
            case 'commands':
                $response = $this->showCommands($clientData);
                break;
            case 'connect':
                $response = $this->nodeService->systemConnect($clientData, $contentArray);
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
                $response = $this->notificationService->showNotifications($clientData);
                break;
            case 'dismissnotification':
                $this->notificationService->dismissNotification($clientData, $entityId);
                break;
            case 'dismissallnotifications':
                $this->notificationService->dismissNotification($clientData, $entityId, true);
                break;
            case 'editnode':
                $response = $this->nodeService->editNodeDescription($clientData);
                break;
            case 'exe':
            case 'execute':
                $response = $this->fileService->executeFile($clientData, $contentArray);
                break;
            case 'fn':
            case 'filename':
                $response = $this->fileService->changeFileName($clientData, $contentArray);
                break;
            case 'gc':
                $messageContent = $this->chatService->globalChat($clientData, $contentArray);
                foreach ($wsClients as $wsClient) {
                    /** @var ConnectionInterface $wsClient */
                    if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
                    $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
                    if (!$clientUser) continue;
                    /** @var User $clientUser */
                    if ($clientUser == $user) {
                        $response = array(
                            'command' => 'showMessage',
                            'type' => ChatService::CHANNEL_GLOBAL,
                            'message' => $messageContent
                        );
                    }
                    else {
                        $response = array(
                            'command' => 'showMessagePrepend',
                            'type' => ChatService::CHANNEL_GLOBAL,
                            'message' => $messageContent
                        );
                    }
                    $wsClient->send(json_encode($response));
                }
                return true;
            case 'home':
            case 'homerecall':
                $response = $this->systemService->homeRecall($clientData);
                break;
            case 'i':
            case 'inv':
            case 'inventory':
                $response = $this->profileService->showInventory($clientData);
                break;
            case 'kill':
                $response = $this->fileService->killProcess($clientData, $contentArray);
                break;
            case 'jobs':
                $response = $this->profileService->showJobs($clientData, $jobs);
                break;
            case 'ls':
                $response = $this->nodeService->showNodeInfo($clientData, $wsClientsData);
                break;
            case 'mail':
                $response = $this->mailMessageService->enterMailMode($clientData);
                break;
            case 'map':
                if ($profile->getCurrentNode()->getSystem()->getProfile() != $profile) {
                    $response = $this->systemService->showAreaMap($clientData);
                }
                else {
                    $response = $this->systemService->showSystemMap($clientData);
                }
                break;
            case 'nodename':
                $response = $this->nodeService->changeNodeName($clientData, $contentArray);
                break;
            case 'nodes':
                $response = $this->nodeService->listNodes($clientData);
                break;
            case 'nodetype':
                $response = $this->nodeService->changeNodeType($clientData, $contentArray);
                break;
            case 'removenode':
                $response = $this->nodeService->removeNode($clientData);
                break;
            case 'parts':
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($clientData);
                break;
            case 'ps':
                $response = $this->fileService->listProcesses($clientData);
                break;
            case 'score':
                $response = $this->profileService->showScore($clientData);
                break;
            case 'secureconnection':
                $response = $this->connectionService->secureConnection($clientData, $contentArray);
                break;
            case 'skills':
                $response = $this->profileService->showSkills($clientData);
                break;
            case 'showunreadmails':
                $response = $this->mailMessageService->displayAmountUnreadMails($clientData);
                break;
            case 'stat':
                $response = $this->fileService->statFile($clientData, $contentArray);
                break;
            case 'survey':
                $response = $this->nodeService->surveyNode($clientData);
                break;
            case 'system':
                $response = $this->systemService->showSystemStats($clientData);
                break;
            case 'time':
                $now = new \DateTime();
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'sysmsg',
                    'message' => sprintf('current server time: %s', $now->format('Y/m/d H:i:s'))
                );
                break;
            case 'touch':
                $response = $this->fileService->touchFile($clientData, $contentArray);
                break;
            /** ADMIN STUFF */
        }
        if ($response) $from->send(json_encode($response));
        return true;
    }

    /**
     * Method that takes care of delegating commands within the mail console mode.
     * @param ConnectionInterface $from
     * @param $clientData
     * @param string $content
     * @param \SplObjectStorage $wsClients
     * @param array $wsClientsData
     * @return bool|ConnectionInterface
     */
    public function parseMailInput(ConnectionInterface $from, $clientData, $content = '', \SplObjectStorage $wsClients, $wsClientsData = array(), $mailOptions = array())
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $mailOptions = (object)$mailOptions;
        switch ($userCommand) {
            default:
                $response = $this->mailMessageService->displayMail($clientData, $mailOptions);
                break;
            case 'd':
                $response = $this->mailMessageService->deleteMail($clientData, $contentArray, $mailOptions);
                break;
            case 'q':
                $response = $this->mailMessageService->exitMailMode();
                break;
        }
        return $from->send(json_encode($response));
    }

    public function parseCodeInput(ConnectionInterface $from, $clientData, $content = '', \SplObjectStorage $wsClients, $wsClientsData = array(), $codeOptions = array())
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $codeOptions = (object)$codeOptions;
        switch ($userCommand) {
            default:
            case 'options':
                $response = $this->codingService->commandOptions($clientData, $contentArray, $codeOptions);
                break;
            case 'code':
                return $this->codingService->commandCode($clientData, $codeOptions);
            case 'jobs':
                $response = $this->profileService->showJobs($clientData);
                break;
            case 'level':
                $response = $this->codingService->commandLevel($clientData, $contentArray);
                break;
            case 'mode':
                $response = $this->codingService->switchCodeMode($clientData, $contentArray);
                break;
            case 'parts':
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($clientData);
                break;
            case 'type':
                $response = $this->codingService->commandType($clientData, $contentArray, $codeOptions);
                break;
            case 'q':
                $response = $this->codingService->exitCodeMode();
                break;
        }
        return $from->send(json_encode($response));
    }

    public function showCommands($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s%-20s<br /></pre>',
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
