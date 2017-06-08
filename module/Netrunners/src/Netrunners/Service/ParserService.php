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
     * @param EntityManager $entityManager
     * @param FileService $fileService
     * @param ChatService $chatService
     * @param MailMessageService $mailMessageService
     * @param ProfileService $profileService
     * @param CodingService $codingService
     * @param SystemService $systemService
     */
    public function __construct(
        EntityManager $entityManager,
        FileService $fileService,
        ChatService $chatService,
        MailMessageService $mailMessageService,
        ProfileService $profileService,
        CodingService $codingService,
        SystemService $systemService
    )
    {
        $this->entityManager = $entityManager;
        $this->fileService = $fileService;
        $this->chatService = $chatService;
        $this->mailMessageService = $mailMessageService;
        $this->profileService = $profileService;
        $this->codingService = $codingService;
        $this->systemService = $systemService;
    }

    /**
     * Main method that takes care of delegating commands to their corresponding service.
     * @param ConnectionInterface $from
     * @param $clientData
     * @param string $content
     * @param \SplObjectStorage $wsClients
     * @param array $wsClientsData
     * @return bool|ConnectionInterface
     */
    public function parseInput(ConnectionInterface $from, $clientData, $content = '', \SplObjectStorage $wsClients, $wsClientsData = array())
    {
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
                // unknown command, might be a file name - try to find a file with the corresponding name and try to execute it
                $targetFiles = $fileRepository->findFileInSystemByName(
                    $profile->getCurrentDirectory()->getSystem(),
                    $profile->getCurrentDirectory(),
                    $content
                );
                if (count($targetFiles) < 1) {
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'sysmsg',
                        'message' => 'Unknown command'
                    );
                }
                else {
                    $targetFile = $targetFiles[0];
                    $response = $this->fileService->executeFile($targetFile, $clientData);
                }
                break;
            case 'cd':
                    $response = $this->fileService->changeDirectory($clientData, $contentArray);
                break;
            case 'clear':
                $response = array(
                    'command' => 'clear',
                    'message' => 'default'
                );
                break;
            case 'code':
                $response = $this->codingService->enterCodeMode($clientData);
                break;
            case 'commands':
                $response = $this->showCommands($clientData);
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
                    if (count($this->fileService->findRunningInSystemByType($clientUser->getProfile()->getSystem(), true, File::TYPE_CHAT_CLIENT)) < 1) continue;
                    $wsClient->send(json_encode($response));
                }
                return true;
            case 'kill':
                $response = $this->fileService->killProcess($clientData, $contentArray);
                break;
            case 'ls':
            case 'dir':
                $response = $this->fileService->listDirectory($clientData);
                break;
            case 'mail':
                $response = $this->mailMessageService->enterMailMode($clientData);
                break;
            case 'makedir':
            case 'mkdir':
                $response = $this->fileService->makeDirectory($clientData, $contentArray);
                break;
            case 'ps':
                $response = $this->fileService->listProcesses($clientData);
                break;
            case 'removedir':
            case 'rmdir':
                $response = $this->fileService->removeDirectory($clientData, $contentArray);
                break;
            case 'score':
                $response = $this->profileService->showScore($clientData);
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
            case 'system':
                $response = $this->systemService->showSystemStats($clientData);
                break;
            /** ADMIN STUFF */
        }
        return $from->send(json_encode($response));
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
                return true;
            case 'level':
                $response = $this->codingService->commandLevel($clientData, $contentArray, $codeOptions);
                break;
            case 'options':
                $response = $this->codingService->commandOptions($clientData, $contentArray, $codeOptions);
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
        $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s%-20s<br />%-20s%-20s%-20s</pre>',
            'cd',
            'clear',
            'code',
            'commands',
            'gc',
            'kill',
            'ls',
            'dir',
            'mail',
            'mkdir',
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
