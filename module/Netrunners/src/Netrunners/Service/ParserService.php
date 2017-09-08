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
use Netrunners\Entity\Feedback;
use Netrunners\Entity\Profile;
use Ratchet\ConnectionInterface;
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
     * @var NpcInstanceService
     */
    protected $npcInstanceService;

    /**
     * @var FactionService
     */
    protected $factionService;

    /**
     * @var ResearchService
     */
    protected $researchService;

    /**
     * @var GroupService
     */
    protected $groupService;


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
     * @param NpcInstanceService $npcInstanceService
     * @param FactionService $factionService
     * @param ResearchService $researchService
     * @param GroupService $groupService
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
        CombatService $combatService,
        NpcInstanceService $npcInstanceService,
        FactionService $factionService,
        ResearchService $researchService,
        GroupService $groupService
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
        $this->npcInstanceService = $npcInstanceService;
        $this->factionService = $factionService;
        $this->researchService = $researchService;
        $this->groupService = $groupService;
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
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translator->translate('unknown command')
                    )
                );
                break;
            case 'attack':
            case 'a':
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
            case 'bgopacity':
                $response = $this->profileService->changeBackgroundOpacity($resourceId, $contentArray);
                break;
            case 'cd':
                $response = $this->connectionService->useConnection($resourceId, $contentArray);
                break;
            case 'code':
                $response = $this->codingService->enterCodeMode($resourceId);
                break;
            case 'commands':
                $response = $this->getWebsocketServer()->getUtilityService()->showCommands($resourceId);
                break;
            case 'connect':
                $response = $this->nodeService->systemConnect($resourceId, $contentArray);
                break;
            case 'consider':
            case 'con':
                $response = $this->npcInstanceService->considerNpc($resourceId, $contentArray);
                break;
            case 'creategroup':
                $response = $this->groupService->createGroup($resourceId, $contentArray);
                break;
            case 'shownotifications':
                $response = $this->notificationService->showNotifications($resourceId);
                break;
            case 'deposit':
                $response = $this->profileService->depositCredits($resourceId, $contentArray);
                break;
            case 'dismissnotification':
            case 'dn':
                $this->notificationService->dismissNotification($resourceId, $entityId);
                break;
            case 'dismissallnotifications':
            case 'dan':
                $this->notificationService->dismissNotification($resourceId, $entityId, true);
                break;
            case 'download':
            case 'dl':
                $response = $this->fileService->downloadFile($resourceId, $contentArray);
                break;
            case 'editmanpage':
                $response = $this->manpageService->editManpage($resourceId, $contentArray);
                break;
            case 'editnode':
                $response = $this->nodeService->editNodeDescription($resourceId);
                break;
            case 'entityname':
                $response = $this->npcInstanceService->changeNpcName($resourceId, $contentArray);
                break;
            case 'equipment':
            case 'eq':
                $response = $this->profileService->showEquipment($resourceId);
                break;
            case 'eset':
                $response = $this->npcInstanceService->esetCommand($resourceId, $contentArray);
                break;
            case 'exe':
            case 'execute':
                $response = $this->fileService->executeFile($resourceId, $contentArray);
                break;
            case 'explore':
                $response = $this->nodeService->exploreCommand($resourceId);
                break;
            case 'factionchat':
            case 'fc':
                $response = $this->chatService->factionChat($resourceId, $contentArray);
                break;
            case 'factionratings':
                $response = $this->profileService->showFactionRatings($resourceId);
                break;
            case 'factions':
                $response = $this->factionService->listFactions($resourceId);
                break;
            case 'filecategories':
            case 'filecats':
                $response = $this->fileService->showFileCategories();
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
            case 'harvest':
                $response = $this->fileService->harvestCommand($resourceId, $contentArray);
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
            case 'joinfaction':
                $response = $this->factionService->joinFaction($resourceId);
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
            case 'l':
            case 'look':
                $response = $this->nodeService->showNodeInfo($resourceId);
                break;
            case 'mail':
                $response = $this->mailMessageService->enterMailMode($resourceId);
                break;
            case 'map':
                if ($profile->getCurrentNode()->getSystem()->getProfile() !== $profile) {
                    $response = $this->systemService->showAreaMap($resourceId);
                }
                else {
                    $response = $this->systemService->showSystemMap($resourceId);
                }
                break;
            case 'modchat':
            case 'mc':
                $response = $this->chatService->moderatorChat($resourceId, $contentArray);
                break;
            case 'motd':
                $response = $this->getWebsocketServer()->getUtilityService()->showMotd($resourceId);
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
                $response = $this->nodeService->enterMode($resourceId, $userCommand, $contentArray);
                break;
            case 'options':
                $response = $this->gameOptionService->optionsCommand($resourceId, $contentArray);
                break;
            case 'removeconnection':
                $response = $this->connectionService->removeConnection($resourceId, $contentArray);
                break;
            case 'removenode':
                $response = $this->nodeService->removeNode($resourceId);
                break;
            case 'research':
                $response = $this->researchService->researchCommand($resourceId, $contentArray);
                break;
            case 'showresearch':
                $response = $this->researchService->showResearchers($resourceId);
                break;
            case 'parts':
            case 'rm':
                $response = $this->fileService->enterMode($resourceId, $userCommand, $contentArray);
                break;
            case 'resources':
            case 'res':
                $response = $this->profileService->showFilePartInstances($resourceId);
                break;
            case 'passwd':
            case 'changepassword':
                $response = $this->profileService->changePassword($resourceId, $contentArray);
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
            case 'unsecure':
            case 'unsecureconnection':
                $response = $this->connectionService->unsecureConnection($resourceId, $contentArray);
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
            case 'showbalance':
                $response = $this->profileService->showBankBalance($resourceId);
                break;
            case 'showunreadmails':
                $response = $this->mailMessageService->displayAmountUnreadMails($resourceId);
                break;
            case 'sneak':
            case 'stealth':
                $response = $this->profileService->startStealthing($resourceId);
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
            case 'date':
                $now = new \DateTime();
                $response = array(
                    'command' => self::CMD_SHOWMESSAGE,
                    'message' => sprintf(
                        $this->translator->translate('<pre style="white-space: pre-wrap;" class="text-info">current server time: %s</pre>'),
                        $now->format('Y/m/d H:i:s')
                    )
                );
                break;
            case 'touch':
                $response = $this->fileService->touchFile($resourceId, $contentArray);
                break;
            case 'typo':
                $response = $this->profileService->openSubmitFeedbackPanel($resourceId);
                break;
            case 'idea':
                $response = $this->profileService->openSubmitFeedbackPanel($resourceId, Feedback::TYPE_IDEA_ID);
                break;
            case 'bug':
                $response = $this->profileService->openSubmitFeedbackPanel($resourceId, Feedback::TYPE_BUG_ID);
                break;
            case 'unload':
            case 'ul':
                $response = $this->fileService->unloadFile($resourceId, $contentArray);
                break;
            case 'updatesystemcoords':
                $response = $this->systemService->changeGeocoords($resourceId, $contentArray);
                break;
            case 'upgradenode':
                $response = $this->nodeService->enterMode($resourceId, $userCommand);
                break;
            case 'use':
                $response = $this->fileService->useCommand($resourceId, $contentArray);
                break;
            case 'visible':
            case 'vis':
                $response = $this->profileService->stopStealthing($resourceId);
                break;
            case 'withdraw':
                $response = $this->profileService->withdrawCredits($resourceId, $contentArray);
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
            case 'goto':
                $response = $this->adminService->gotoNodeCommand($resourceId, $contentArray);
                break;
            case 'grantrole':
                $response = $this->adminService->grantRoleCommand($resourceId, $contentArray);
                break;
            case 'removerole':
                $response = $this->adminService->removeRoleCommand($resourceId, $contentArray);
                break;
            case 'kickclient':
                $response = $this->adminService->kickClient($resourceId, $contentArray);
                break;
            case 'nlist':
                $response = $this->adminService->nListCommand($resourceId, $contentArray);
                break;
            case 'setsnippets':
                $response = $this->adminService->adminSetSnippets($resourceId, $contentArray);
                break;
            case 'setcredits':
                $response = $this->adminService->adminSetCredits($resourceId, $contentArray);
                break;
            case 'syslist':
                $response = $this->adminService->sysListCommand($resourceId);
                break;
            case 'toggleadminmode':
                $response = $this->adminService->adminToggleAdminMode($resourceId);
                break;
            case 'cybermap':
                $response = $this->adminService->showCyberspaceMap($resourceId);
                break;
            case 'setmotd':
                $response = $this->adminService->adminSetMotd($resourceId, $contentArray);
                break;
        }
        if (!is_array($response)) {
            if (!$silent) {
                $response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translator->translate('Your command could not be parsed properly... This could either be a bug or you using the command in a wrong way...')
                    )
                ];
                $response['prompt'] = $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData);
            }
        }
        else {
            $response['prompt'] = $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData);
            $response['silent'] = $silent;
        }
        if ($response) $from->send(json_encode($response));
        // check if we have to work on more commands
        if (is_array($response) && array_key_exists('additionalCommands', $response)) {
            foreach ($response['additionalCommands'] as $additionalCommandId => $additionalCommandData) {
                $additionalResponse = false;
                switch ($additionalCommandData['command']) {
                    default:
                        break;
                    case 'map':
                        $additionalResponse = $this->systemService->showAreaMap($resourceId);
                        break;
                    case 'flyto':
                        $additionalResponse = [
                            'command' => 'flytocoords',
                            'content' => explode(',', $additionalCommandData['content'])
                        ];
                        break;
                    case 'setopacity':
                        $additionalResponse = [
                            'command' => 'setbgopacity',
                            'content' => $additionalCommandData['content']
                        ];
                        break;
                    case 'getrandomgeocoords':
                        $additionalResponse = [
                            'command' => 'getrandomgeocoords',
                            'content' => $additionalCommandData['content']
                        ];
                        break;
                }
                if (is_array($additionalResponse)) {
                    $additionalResponse['silent'] = $additionalCommandData['silent'];
                    $from->send(json_encode($additionalResponse));
                }
            }
        }
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
                return $this->codingService->commandCode($resourceId, $codeOptions, $contentArray);
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
        if ($content == 'yes' || $content == 'y' || $content == 'confirm') {
            switch ($confirmData->command) {
                default:
                    break;
                case 'upgradenode':
                    $response = $this->nodeService->upgradeNode($resourceId);
                    break;
                case 'nodetype':
                    $response = $this->nodeService->changeNodeType($resourceId, $confirmData->contentArray);
                    break;
                case 'rm':
                    $response = $this->fileService->removeFile($resourceId, $confirmData->contentArray);
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
            $response['prompt'] = $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData);
        }
        return $response;
    }

}
