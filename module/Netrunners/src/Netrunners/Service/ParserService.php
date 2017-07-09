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
use Zend\Mvc\I18n\Translator;

class ParserService
{

    const CMD_REQUESTMILKRUN = 'requestmilkrun';
    const CMD_SCORE = 'score';
    const CMD_SHOWMESSAGE = 'showmessage';
    const CMD_ADDNODE = 'addnode';
    const CMD_ADDCONNECTION = 'addconnection';

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Translator
     */
    protected $translator;

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
     * @var AdminService
     */
    protected $adminService;

    /**
     * @var MilkrunService
     */
    protected $milkrunService;

    /**
     * @var HangmanService
     */
    protected $hangmanService;

    /**
     * @var CodebreakerService
     */
    protected $codebreakerService;

    /**
     * @var GameOptionService
     */
    protected $gameOptionService;

    /**
     * @var ManpageService
     */
    protected $manpageService;

    /**
     * @var CombatService
     */
    protected $combatService;


    /**
     * @param EntityManager $entityManager
     * @param Translator $translator
     * @param FileService $fileService
     * @param NodeService $nodeService
     * @param ChatService $chatService
     * @param MailMessageService $mailMessageService
     * @param ProfileService $profileService
     * @param CodingService $codingService
     * @param SystemService $systemService
     * @param ConnectionService $connectionService
     * @param NotificationService $notificationService
     * @param AdminService $adminService
     * @param MilkrunService $milkrunService
     * @param HangmanService $hangmanService
     * @param CodebreakerService $codebreakerService
     * @param GameOptionService $gameOptionService
     * @param ManpageService $manpageService
     * @param CombatService $combatService
     */
    public function __construct(
        EntityManager $entityManager,
        Translator $translator,
        FileService $fileService,
        NodeService $nodeService,
        ChatService $chatService,
        MailMessageService $mailMessageService,
        ProfileService $profileService,
        CodingService $codingService,
        SystemService $systemService,
        ConnectionService $connectionService,
        NotificationService $notificationService,
        AdminService $adminService,
        MilkrunService $milkrunService,
        HangmanService $hangmanService,
        CodebreakerService $codebreakerService,
        GameOptionService $gameOptionService,
        ManpageService $manpageService,
        CombatService $combatService
    )
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->fileService = $fileService;
        $this->nodeService = $nodeService;
        $this->chatService = $chatService;
        $this->mailMessageService = $mailMessageService;
        $this->profileService = $profileService;
        $this->codingService = $codingService;
        $this->systemService = $systemService;
        $this->connectionService = $connectionService;
        $this->notificationService = $notificationService;
        $this->adminService = $adminService;
        $this->milkrunService = $milkrunService;
        $this->hangmanService = $hangmanService;
        $this->codebreakerService = $codebreakerService;
        $this->gameOptionService = $gameOptionService;
        $this->manpageService = $manpageService;
        $this->combatService = $combatService;
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
     * @param bool $entityId
     * @param bool $jobs
     * @param bool $silent
     * @return bool
     */
    public function parseInput(ConnectionInterface $from, $content = '', $entityId = false, $jobs = false, $silent = false)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $response = false;
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $userCommand = trim($userCommand);
        switch ($userCommand) {
            default:
                $response = array(
                    'command' => self::CMD_SHOWMESSAGE,
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translator->translate('Unknown command')
                    )
                );
                break;
            case 'attack':
                $response = $this->combatService->attackCommand($resourceId, $contentArray);
                break;
            case 'clear':
                $response = array(
                    'command' => 'clear',
                    'message' => 'default'
                );
                break;
            case self::CMD_ADDNODE:
                $response = $this->nodeService->addNode($resourceId);
                break;
            case self::CMD_ADDCONNECTION:
                $response = $this->connectionService->addConnection($resourceId, $contentArray);
                break;
            case 'addmanpage':
                $response = $this->manpageService->addManpage($resourceId, $contentArray);
                break;
            case 'cd':
                $response = $this->connectionService->useConnection($resourceId, $contentArray);
                break;
            case 'code':
                $response = $this->codingService->enterCodeMode($resourceId);
                break;
            case 'commands':
                $response = $this->showCommands($resourceId);
                break;
            case 'connect':
                $response = $this->nodeService->systemConnect($resourceId, $contentArray);
                break;
            case 'shownotifications':
                $response = $this->notificationService->showNotifications($resourceId);
                break;
            case 'dismissnotification':
            case 'dn':
                $this->notificationService->dismissNotification($resourceId, $entityId);
                break;
            case 'dismissallnotifications':
            case 'dan':
                $this->notificationService->dismissNotification($resourceId, $entityId, true);
                break;
            case 'editmanpage':
                $response = $this->manpageService->editManpage($resourceId, $contentArray);
                break;
            case 'editnode':
                $response = $this->nodeService->editNodeDescription($resourceId);
                break;
            case 'equipment':
            case 'eq':
                $response = $this->profileService->showEquipment($resourceId);
                break;
            case 'exe':
            case 'execute':
                $response = $this->fileService->executeFile($resourceId, $contentArray);
                break;
            case 'factionratings':
                $response = $this->profileService->showFactionRatings($resourceId);
                break;
            case 'filemods':
                $response = $this->fileService->showFileMods();
                break;
            case 'fn':
            case 'filename':
                $response = $this->fileService->changeFileName($resourceId, $contentArray);
                break;
            case 'filetypes':
                $response = $this->fileService->showFileTypes();
                break;
            case 'gc':
                $response = $this->chatService->globalChat($resourceId, $contentArray);
                break;
            case 'hangman':
                $response = $this->hangmanService->startHangmanGame($resourceId);
                break;
            case 'hangmanletterclick':
                $response = $this->hangmanService->letterClicked($resourceId, $contentArray);
                break;
            case 'hangmansolution':
                $response = $this->hangmanService->solutionAttempt($resourceId, $contentArray);
                break;
            case 'help':
            case 'man':
                $response = $this->manpageService->helpCommand($resourceId, $contentArray);
                break;
            case 'say':
                $response = $this->chatService->sayChat($resourceId, $contentArray);
                break;
            case 'home':
            case 'homerecall':
                $response = $this->systemService->homeRecall($resourceId);
                break;
            case 'initarmor':
                $response = $this->fileService->initArmorCommand($resourceId, $contentArray);
                break;
            case 'i':
            case 'inv':
            case 'inventory':
                $response = $this->profileService->showInventory($resourceId);
                break;
            case 'kill':
                $response = $this->fileService->killProcess($resourceId, $contentArray);
                break;
            case 'jobs':
                $response = $this->profileService->showJobs($resourceId, $jobs);
                break;
            case 'listmanpages':
                $response = $this->manpageService->listManpages($resourceId);
                break;
            case 'ls':
                $response = $this->nodeService->showNodeInfo($resourceId);
                break;
            case 'mail':
                $response = $this->mailMessageService->enterMailMode($resourceId);
                break;
            case 'map':
                if ($profile->getCurrentNode()->getSystem()->getProfile() != $profile) {
                    $response = $this->systemService->showAreaMap($resourceId);
                }
                else {
                    $response = $this->systemService->showSystemMap($resourceId);
                }
                break;
            case 'new':
            case 'newbie':
                $response = $this->chatService->newbieChat($resourceId, $contentArray);
                break;
            case 'nodename':
                $response = $this->nodeService->changeNodeName($resourceId, $contentArray);
                break;
            case 'nodes':
                $response = $this->nodeService->listNodes($resourceId);
                break;
            case 'nodetype':
                $response = $this->nodeService->changeNodeType($resourceId, $contentArray);
                break;
            case 'options':
                $response = $this->gameOptionService->optionsCommand($resourceId, $contentArray);
                break;
            case 'removenode':
                $response = $this->nodeService->removeNode($resourceId);
                break;
            case 'parts':
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($resourceId);
                break;
            case 'ps':
                $response = $this->fileService->listProcesses($resourceId, $contentArray);
                break;
            case self::CMD_REQUESTMILKRUN:
            case 'milkrun':
                $response = $this->milkrunService->requestMilkrun($resourceId);
                break;
            case 'milkrunclick':
                $response = $this->milkrunService->clickTile($resourceId, $contentArray);
                break;
            case 'scan':
                $response = $this->connectionService->scanConnection($resourceId, $contentArray);
                break;
            case self::CMD_SCORE:
                $response = $this->profileService->showScore($resourceId);
                break;
            case 'secureconnection':
                $response = $this->connectionService->secureConnection($resourceId, $contentArray);
                break;
            case 'setemail':
                $response = $this->profileService->setEmail($resourceId, $contentArray);
                break;
            case 'setlocale':
                $response = $this->profileService->setProfileLocale($resourceId, $contentArray);
                break;
            case 'skillpoints':
                $response = $this->profileService->spendSkillPoints($resourceId, $contentArray);
                break;
            case 'skills':
                $response = $this->profileService->showSkills($resourceId);
                break;
            case 'showunreadmails':
                $response = $this->mailMessageService->displayAmountUnreadMails($resourceId);
                break;
            case 'stat':
                $response = $this->fileService->statFile($resourceId, $contentArray);
                break;
            case 'survey':
                $response = $this->nodeService->surveyNode($resourceId);
                break;
            case 'system':
                $response = $this->systemService->showSystemStats($resourceId);
                break;
            case 'time':
                $now = new \DateTime();
                $response = array(
                    'command' => self::CMD_SHOWMESSAGE,
                    'message' => sprintf(
                        $this->translator->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">current server time: %s</pre>'),
                        $now->format('Y/m/d H:i:s')
                    )
                );
                break;
            case 'touch':
                $response = $this->fileService->touchFile($resourceId, $contentArray);
                break;
            case 'upgradenode':
                $response = $this->nodeService->enterUpgradeMode($resourceId, $userCommand);
                break;
            /** ADMIN STUFF */
            case 'banip':
                $response = $this->adminService->banIp($resourceId, $contentArray);
                break;
            case 'unbanip':
                $response = $this->adminService->unbanIp($resourceId, $contentArray);
                break;
            case 'banuser':
                $response = $this->adminService->banUser($resourceId, $contentArray);
                break;
            case 'unbanuser':
                $response = $this->adminService->unbanUser($resourceId, $contentArray);
                break;
            case 'clients':
            case 'showclients':
                $response = $this->adminService->adminShowClients($resourceId);
                break;
            case 'kickclient':
                $response = $this->adminService->kickClient($resourceId, $contentArray);
                break;
            case 'setsnippets':
                $response = $this->adminService->adminSetSnippets($resourceId, $contentArray);
                break;
            case 'setcredits':
                $response = $this->adminService->adminSetCredits($resourceId, $contentArray);
                break;
            case 'toggleadminmode':
                $response = $this->adminService->adminToggleAdminMode($resourceId);
                break;
        }
        if (!is_array($response)) return true;
        $response['prompt'] = $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData);
        $response['silent'] = $silent;
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
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $userCommand = trim($userCommand);
        $mailOptions = (object)$mailOptions;
        switch ($userCommand) {
            default:
                $response = $this->mailMessageService->displayMail($resourceId, $mailOptions);
                break;
            case 'd':
                $response = $this->mailMessageService->deleteMail($resourceId, $contentArray, $mailOptions);
                break;
            case 'q':
                $response = $this->mailMessageService->exitMailMode($resourceId);
                break;
        }
        return $from->send(json_encode($response));
    }

    /**
     * @param ConnectionInterface $from
     * @param string $content
     * @param bool $jobs
     * @return array|bool
     */
    public function parseCodeInput(ConnectionInterface $from, $content = '', $jobs = false)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $userCommand = trim($userCommand);
        $codeOptions = (object)$clientData->codingOptions;
        switch ($userCommand) {
            default:
            case 'options':
                $response = $this->codingService->commandOptions($resourceId, $codeOptions);
                break;
            case 'code':
                return $this->codingService->commandCode($resourceId, $codeOptions);
            case 'jobs':
                $response = $this->profileService->showJobs($resourceId, $jobs);
                break;
            case 'level':
                $response = $this->codingService->commandLevel($resourceId, $contentArray);
                break;
            case 'mode':
                $response = $this->codingService->switchCodeMode($resourceId, $contentArray);
                break;
            case 'parts':
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($resourceId);
                break;
            case 'type':
                $response = $this->codingService->commandType($resourceId, $contentArray, $codeOptions);
                break;
            case 'q':
                $response = $this->codingService->exitCodeMode($resourceId);
                break;
        }
        return $response;
    }

    /**
     * @param ConnectionInterface $from
     * @param string $content
     * @return array|bool|false
     */
    public function parseConfirmInput(ConnectionInterface $from, $content = '')
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $confirmData = (object)$clientData->confirm;
        if (!isset($confirmData->command)) return true;
        $response = false;
        if ($content == 'yes') {
            switch ($confirmData->command) {
                default:
                    break;
                case 'upgradenode':
                    $response = $this->nodeService->upgradeNode($resourceId);
                    break;
            }
        }
        $this->getWebsocketServer()->setConfirm($resourceId, '');
        if (!is_array($response)) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                    $this->translator->translate('You cancel your action')
                ),
                'prompt' => $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData),
                'exitconfirmmode' => true
            ];
        }
        else {
            $response['exitconfirmmode'] = true;
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
        $message = $this->translator->translate('addconnection addnode attack cd clear code commands connect editnode equipment execute factionratings filemods filename gc help home initarmor inventory jobs kill ls mail map newbie nodename nodes nodetype options ps removenode resources say scan secureconnection setemail setlocale skillpoints skills stat survey system time touch');
        $returnMessage = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
            wordwrap($message, 120)
        );
        $response = array(
            'command' => self::CMD_SHOWMESSAGE,
            'message' => $returnMessage
        );
        return $response;
    }

}
