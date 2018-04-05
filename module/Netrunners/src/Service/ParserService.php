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
use Netrunners\Model\GameClientResponse;
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
     * @var AuctionService
     */
    protected $auctionService;

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
     * @var MilkrunAivatarService
     */
    protected $milkrunAivatarService;

    /**
     * @var MissionService
     */
    protected $missionService;

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
     * @var PartyService
     */
    protected $partyService;

    /**
     * @var StoryService
     */
    protected $storyService;

    /**
     * @var PassageService
     */
    protected $passageService;

    /**
     * @var BountyService
     */
    protected $bountyService;

    /**
     * @param EntityManager $entityManager
     * @param Translator $translator
     * @param AuctionService $auctionService
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
     * @param MilkrunAivatarService $milkrunAivatarService
     * @param MissionService $missionService
     * @param HangmanService $hangmanService
     * @param CodebreakerService $codebreakerService
     * @param GameOptionService $gameOptionService
     * @param ManpageService $manpageService
     * @param CombatService $combatService
     * @param NpcInstanceService $npcInstanceService
     * @param FactionService $factionService
     * @param ResearchService $researchService
     * @param GroupService $groupService
     * @param PartyService $partyService
     * @param StoryService $storyService
     * @param PassageService $passageService
     * @param BountyService $bountyService
     */
    public function __construct(
        EntityManager $entityManager,
        Translator $translator,
        AuctionService $auctionService,
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
        MilkrunAivatarService $milkrunAivatarService,
        MissionService $missionService,
        HangmanService $hangmanService,
        CodebreakerService $codebreakerService,
        GameOptionService $gameOptionService,
        ManpageService $manpageService,
        CombatService $combatService,
        NpcInstanceService $npcInstanceService,
        FactionService $factionService,
        ResearchService $researchService,
        GroupService $groupService,
        PartyService $partyService,
        StoryService $storyService,
        PassageService $passageService,
        BountyService $bountyService
    )
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->auctionService = $auctionService;
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
        $this->milkrunAivatarService = $milkrunAivatarService;
        $this->missionService = $missionService;
        $this->hangmanService = $hangmanService;
        $this->codebreakerService = $codebreakerService;
        $this->gameOptionService = $gameOptionService;
        $this->manpageService = $manpageService;
        $this->combatService = $combatService;
        $this->npcInstanceService = $npcInstanceService;
        $this->factionService = $factionService;
        $this->researchService = $researchService;
        $this->groupService = $groupService;
        $this->partyService = $partyService;
        $this->storyService = $storyService;
        $this->passageService = $passageService;
        $this->bountyService = $bountyService;
    }

    /**
     * @return WebsocketService
     */
    private function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * @param $content
     * @return array
     */
    private function prepareData($content)
    {
        $contentArray = explode(' ', $content);
        $userCommand = array_shift($contentArray);
        $userCommand = trim($userCommand);
        return [$contentArray, $userCommand];
    }

    /**
     * @param $msgData
     * @return array
     */
    private function prepareFrontendData($msgData)
    {
        $content = (isset($msgData->content)) ? $msgData->content : false;
        $entityId = (isset($msgData->entityId)) ? $msgData->entityId : false;
        $subcommand = (isset($msgData->subcommand)) ? $msgData->subcommand: false;
        return [$content, $entityId, $subcommand];
    }

    /**
     * Main method that takes care of delegating commands to their corresponding service.
     * @param ConnectionInterface $from
     * @param string $content
     * @param bool $entityId
     * @param bool $jobs
     * @param bool $silent
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function parseInput(ConnectionInterface $from, $content = '', $entityId = false, $jobs = false, $silent = false)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        list($contentArray, $userCommand) = $this->prepareData($content);
        switch (strtolower($userCommand)) {
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
            case 'kill':
            case 'k':
                return $this->combatService->attackCommand($resourceId, $contentArray);
            case 'aptitudes':
            case 'apt':
                return $this->profileService->showAptitudes($resourceId);
            case 'auctionclaim':
                return $this->auctionService->claimAuction($resourceId, $contentArray);
            case 'auction':
            case 'auctionfile':
                return $this->auctionService->auctionFile($resourceId, $contentArray);
            case 'auctions':
                return $this->auctionService->listAuctions($resourceId);
            case 'auctionbid':
            case 'bid':
                return $this->auctionService->bidOnAuction($resourceId, $contentArray);
            case 'auctionbids':
            case 'bids':
                return $this->auctionService->showBids($resourceId);
            case 'auctionbuyout':
            case 'buyout':
                return $this->auctionService->buyoutAuction($resourceId, $contentArray);
            case 'auctioncancel':
            case 'cancelauction':
                return $this->auctionService->cancelAuction($resourceId, $contentArray);
            case 'claimnode':
            case 'claimsystem':
            case 'claim':
                return $this->nodeService->claimCommand($resourceId);
            case 'clear':
                $response = array(
                    'command' => 'clear',
                    'message' => 'default'
                );
                break;
            case self::CMD_ADDNODE:
                return $this->nodeService->enterMode($resourceId, $userCommand, $contentArray);
            case self::CMD_ADDCONNECTION:
                return $this->connectionService->addConnection($resourceId, $contentArray);
            case 'addmanpage':
                return $this->manpageService->addManpage($resourceId, $contentArray);
            case 'bgopacity':
                return $this->profileService->changeBackgroundOpacity($resourceId, $contentArray);
            case 'cancel':
                return $this->profileService->cancelCurrentAction($resourceId, true, true);
            case 'cd':
                return $this->connectionService->useConnection($resourceId, $contentArray);
            case 'close':
                return $this->connectionService->closeConnection($resourceId, $contentArray);
            case 'code':
                return $this->codingService->openCodingInterface($resourceId);
            case 'showcodingdetailpanel':
                return $this->codingService->showCodingDetailPanel($resourceId, $contentArray);
                break;
            case 'commands':
                return $this->getWebsocketServer()->getUtilityService()->showCommands($resourceId);
            case 'compare':
                return $this->fileService->compareCommand($resourceId, $contentArray);
            case 'connect':
                return $this->nodeService->systemConnect($resourceId, $contentArray);
            case 'consider':
            case 'con':
                return $this->npcInstanceService->considerNpc($resourceId, $contentArray);
            case 'creategroup':
                return $this->groupService->createGroup($resourceId, $contentArray);
            case 'createparty':
                return $this->partyService->createParty($resourceId);
            case 'createpasskey':
            case 'passkey':
                return $this->fileService->createPasskeyCommand($resourceId);
            case 'shownotifications':
                return $this->notificationService->showNotifications($resourceId);
            case 'decompile':
                return $this->fileService->decompileFile($resourceId, $contentArray);
            case 'updatepartsstring':
                return $this->codingService->updatePartsString($resourceId, $contentArray);
            case 'updatelastcodinglevel':
                return $this->codingService->updateLastCodingLevel($resourceId, $contentArray);
            case 'deposit':
                return $this->profileService->depositCredits($resourceId, $contentArray);
            case 'dismissnotification':
            case 'dn':
                return $this->notificationService->dismissNotification($resourceId, $entityId);
            case 'dismissallnotifications':
            case 'dan':
                return $this->notificationService->dismissNotification($resourceId, $entityId, true);
            case 'download':
            case 'dl':
                return $this->fileService->downloadFile($resourceId, $contentArray);
            case 'editfile':
                return $this->fileService->editFileDescription($resourceId, $contentArray);
            case 'editmanpage':
                return $this->manpageService->editManpage($resourceId, $contentArray);
            case 'editnode':
                return $this->nodeService->editNodeDescription($resourceId);
            case 'emote':
            case 'em':
                return $this->chatService->emoteChat($resourceId, $contentArray);
            case 'entityname':
                return $this->npcInstanceService->changeNpcName($resourceId, $contentArray);
            case 'equipment':
            case 'eq':
                return $this->profileService->showEquipment($resourceId);
            case 'eset':
                return $this->npcInstanceService->esetCommand($resourceId, $contentArray);
            case 'exe':
            case 'execute':
                return $this->fileService->executeFile($resourceId, $contentArray);
            case 'explore':
                return $this->nodeService->exploreCommand($resourceId);
            case 'factionchat':
            case 'fc':
                return $this->chatService->factionChat($resourceId, $contentArray);
            case 'factionratings':
                return $this->profileService->showFactionRatings($resourceId);
            case 'factions':
                return $this->factionService->listFactions($resourceId);
            case 'filecategories':
            case 'filecats':
                return $this->fileService->showFileCategories($resourceId);
            case 'filemods':
                return $this->fileService->showFileMods($resourceId);
            case 'fn':
            case 'filename':
            return $this->fileService->changeFileName($resourceId, $contentArray);
            case 'filetypes':
                return $this->fileService->showFileTypes($resourceId);
            case 'glob':
                return $this->chatService->globalChat($resourceId, $contentArray);
            case 'gc':
                return $this->chatService->groupChat($resourceId, $contentArray);
            case 'hangmanletterclick':
                return $this->hangmanService->letterClicked($resourceId, $contentArray);
            case 'hangmansolution':
                return $this->hangmanService->solutionAttempt($resourceId, $contentArray);
            case 'help':
            case 'man':
                return $this->manpageService->helpCommand($resourceId, $contentArray);
            case 'harvest':
                return $this->fileService->harvestCommand($resourceId, $contentArray);
            case 'say':
                return $this->chatService->sayChat($resourceId, $contentArray);
            case 'home':
            case 'homerecall':
                return $this->systemService->homeRecall($resourceId);
            case 'initarmor':
                return $this->fileService->initArmorCommand($resourceId, $contentArray);
            case 'i':
            case 'inv':
            case 'inventory':
                return $this->profileService->showInventory($resourceId);
            case 'invitations':
                return $this->profileService->showInvitations($resourceId);
            case 'joinfaction':
                return $this->factionService->joinFaction($resourceId);
            case 'joingroup':
                return $this->groupService->joinGroup($resourceId);
            case 'killprocess':
            case 'killp':
                return $this->fileService->killProcess($resourceId, $contentArray);
            case 'jobs':
                return $this->profileService->showJobs($resourceId, $jobs);
            case 'leaveparty':
                return $this->partyService->leaveParty($resourceId);
            case 'listmanpages':
                return $this->manpageService->listManpages($resourceId);
            case 'listpasskeys':
            case 'passkeys':
                return $this->fileService->listPasskeysCommand($resourceId);
            case 'logout':
                return $this->profileService->logoutCommand($resourceId);
            case 'ls':
            case 'l':
            case 'look':
                return $this->nodeService->showNodeInfoNew($resourceId, NULL, true);
            case 'mail':
                return $this->mailMessageService->manageMails($resourceId);
            case 'mailread':
                return $this->mailMessageService->mailReadCommand($resourceId, $contentArray);
            case 'maildetach':
                return $this->mailMessageService->mailDetachCommand($resourceId, $contentArray);
            case 'mailattachinfo':
                return $this->mailMessageService->mailAttachInfoCommand($resourceId, $contentArray);
            case 'mailattachmentdelete':
                return $this->mailMessageService->mailAttachmentDeleteCommand($resourceId, $contentArray);
            case 'maildelete':
                return $this->mailMessageService->mailDeleteCommand($resourceId, $contentArray);
            case 'mailcreate':
                return $this->mailMessageService->mailCreateCommand($resourceId);
            case 'mailreply':
                return $this->mailMessageService->mailReplyCommand($resourceId, $contentArray);
            case 'groupdeposit':
                // TODO finish this
                break;
            case 'groupwithdraw':
                // TODO finish this
                break;
            case 'managegroup':
                return $this->groupService->manageGroupCommand($resourceId);
            case 'manageparts':
                return $this->codingService->managePartsCommand($resourceId);
            case 'grouptogglerecruitment':
                return $this->groupService->toggleRecruitment($resourceId);
            case 'groupinvitation':
                return $this->groupService->groupInvitation($resourceId, $contentArray);
            case 'map':
                return $this->systemService->updateMap($resourceId, $user->getProfile(), false);
            case 'showmra':
            case 'showmilkrunaivatars':
                return $this->milkrunAivatarService->showMilkrunAivatars($resourceId);
            case 'defaultmra':
                return $this->milkrunAivatarService->setDefaultMrai($resourceId, $contentArray);
            case 'repairmra':
                return $this->milkrunAivatarService->repairMrai($resourceId);
            case 'upgrademra':
                return $this->milkrunAivatarService->upgradeMra($resourceId, $contentArray);
            case 'missiondetails':
                return $this->missionService->showMissionDetails($resourceId);
            case 'modchat':
            case 'mc':
                return $this->chatService->moderatorChat($resourceId, $contentArray);
            case 'mod':
            case 'modfile':
                return $this->fileService->modFile($resourceId, $contentArray);
            case 'mods':
                return $this->profileService->showFileModInstances($resourceId, $contentArray);
            case 'motd':
                return $this->getWebsocketServer()->getUtilityService()->showMotd($resourceId);
            case 'new':
            case 'newbie':
                return $this->chatService->newbieChat($resourceId, $contentArray);
            case 'ninfo':
                return $this->nodeService->ninfoCommand($resourceId);
            case 'nodename':
                return $this->nodeService->changeNodeName($resourceId, $contentArray);
            case 'nodes':
                return $this->nodeService->listNodes($resourceId);
            case 'nodetype':
                return $this->nodeService->enterMode($resourceId, $userCommand, $contentArray);
            case 'nset':
                return $this->nodeService->nset($resourceId, $contentArray);
            case 'open':
                return $this->connectionService->openConnection($resourceId, $contentArray);
            case 'options':
                return $this->gameOptionService->optionsCommand($resourceId, $contentArray);
            case 'party':
                return $this->partyService->partyCommand($resourceId);
            case 'partychat':
            case 'pc':
                return $this->chatService->partyChat($resourceId, $contentArray);
            case 'partyfollow':
                return $this->partyService->partyFollowCommand($resourceId);
            case 'recipes':
                return $this->codingService->showRecipes($resourceId);
            case 'removeconnection':
            case 'rmconnection':
                return $this->connectionService->removeConnection($resourceId, $contentArray);
            case 'removenode':
            case 'rmnode':
                return $this->nodeService->removeNode($resourceId);
            case 'removepasskey':
            case 'rmpasskey':
                return $this->fileService->removePasskeyCommand($resourceId, $contentArray);
            case 'removeresource':
            case 'rmres':
                return $this->codingService->removeResourceCommand($resourceId, $contentArray);
            case 'reply':
                return $this->chatService->replyChat($resourceId, $contentArray);
            case 'research':
                return $this->researchService->researchCommand($resourceId, $contentArray);
            case 'showresearch':
                return $this->researchService->showResearchers($resourceId);
            case 'rm':
                return $this->fileService->enterMode($resourceId, $userCommand, $contentArray);
            case 'requestmission':
            case 'mission':
                return $this->missionService->enterMode($resourceId);
            case 'resources':
            case 'res':
            case 'parts':
                return $this->profileService->showFilePartInstances($resourceId, $contentArray);
            case 'passageadd':
                return $this->passageService->passageAddCommand($resourceId);
            case 'passageedit':
                return $this->passageService->passageEditCommand($resourceId, $contentArray);
            case 'passageeditor':
                return $this->passageService->passageEditorCommand($resourceId, $contentArray);
            case 'passagelist':
                return $this->passageService->passageListCommand($resourceId);
            case 'passwd':
            case 'changepassword':
                return $this->profileService->changePassword($resourceId, $contentArray);
            case 'partyinvite':
                return $this->partyService->partyInviteCommand($resourceId, $contentArray);
            case 'partykick':
                return $this->partyService->partyKickCommand($resourceId, $contentArray);
            case 'partyrequest':
                return $this->partyService->partyRequestCommand($resourceId, $contentArray);
            case 'pignore':
                return $this->profileService->pignoreCommand($resourceId, $contentArray);
            case 'placebounty':
                return $this->bountyService->postBounty($resourceId, $contentArray);
            case 'ps':
                return $this->fileService->listProcesses($resourceId, $contentArray);
            case self::CMD_REQUESTMILKRUN:
            case 'milkrun':
                return $this->milkrunService->enterMilkrunMode($resourceId);
            case 'milkrunclick':
                return $this->milkrunService->clickTile($resourceId, $contentArray);
            case 'scan':
                return $this->connectionService->scanConnection($resourceId, $contentArray);
            case self::CMD_SCORE:
                return $this->profileService->showScore($resourceId);
            case 'secureconnection':
                return $this->connectionService->secureConnection($resourceId, $contentArray);
            case 'slay':
                return $this->combatService->slayCommand($resourceId, $contentArray);
            case 'unsecure':
            case 'unsecureconnection':
                return $this->connectionService->unsecureConnection($resourceId, $contentArray);
            case 'update':
            case 'fix':
            case 'updatefile':
            case 'fixfile':
                return $this->fileService->updateFile($resourceId, $contentArray);
            case 'setemail':
                return $this->profileService->setEmail($resourceId, $contentArray);
            case 'setlocale':
                return $this->profileService->setProfileLocale($resourceId, $contentArray);
            case 'skillpoints':
                return $this->profileService->spendSkillPoints($resourceId, $contentArray);
            case 'skills':
                return $this->profileService->showSkills($resourceId);
            case 'showbalance':
                return $this->profileService->showBankBalance($resourceId);
            case 'showbounties':
                return $this->bountyService->showBounties($resourceId, $contentArray);
            case 'showunreadmails':
                return $this->mailMessageService->displayAmountUnreadMails($resourceId);
            case 'sneak':
            case 'stealth':
                return $this->profileService->startStealthing($resourceId);
            case 'startcoding':
                return $this->codingService->startCodingCommand($resourceId, $contentArray);
            case 'stat':
                return $this->fileService->statFile($resourceId, $contentArray);
            case 'storyadd':
                return $this->storyService->storyAddCommand($resourceId);
            case 'storyedit':
                return $this->storyService->storyEditCommand($resourceId, $contentArray);
            case 'storyeditor':
                return $this->storyService->storyEditorCommand($resourceId, $contentArray);
            case 'storylist':
                return $this->storyService->storyListCommand($resourceId);
            case 'survey':
                return $this->nodeService->surveyNode($resourceId);
            case 'system':
                return $this->systemService->showSystemStats($resourceId);
            case 'tell':
                return $this->chatService->tellChat($resourceId, $contentArray);
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
                return $this->fileService->touchFile($resourceId, $contentArray);
            case 'typo':
                return $this->profileService->openSubmitFeedbackPanel($resourceId);
            case 'idea':
                return $this->profileService->openSubmitFeedbackPanel($resourceId, Feedback::TYPE_IDEA_ID);
            case 'bug':
                return $this->profileService->openSubmitFeedbackPanel($resourceId, Feedback::TYPE_BUG_ID);
            case 'unload':
            case 'ul':
            case 'upload':
                return $this->fileService->unloadFile($resourceId, $contentArray);
            case 'updatesystemcoords':
                return $this->systemService->changeGeocoords($resourceId, $contentArray);
            case 'upgradenode':
                return $this->nodeService->enterMode($resourceId, $userCommand);
            case 'use':
                return $this->fileService->useCommand($resourceId, $contentArray);
            case 'visible':
            case 'vis':
                return $this->profileService->stopStealthing($resourceId);
            case 'withdraw':
                return $this->profileService->withdrawCredits($resourceId, $contentArray);
            /** ADMIN STUFF */
            case 'banip':
                return $this->adminService->banIp($resourceId, $contentArray);
            case 'unbanip':
                return $this->adminService->unbanIp($resourceId, $contentArray);
            case 'banuser':
                return $this->adminService->banUser($resourceId, $contentArray);
            case 'unbanuser':
                return $this->adminService->unbanUser($resourceId, $contentArray);
            case 'clients':
            case 'showclients':
                return $this->adminService->adminShowClients($resourceId);
            case 'giveinvitation':
                return $this->adminService->giveInvitation($resourceId, $contentArray);
            case 'goto':
                return $this->adminService->gotoNodeCommand($resourceId, $contentArray);
            case 'grantrole':
                return $this->adminService->grantRoleCommand($resourceId, $contentArray);
            case 'removerole':
                return $this->adminService->removeRoleCommand($resourceId, $contentArray);
            case 'kickclient':
                return $this->adminService->kickClient($resourceId, $contentArray);
            case 'nlist':
                return $this->adminService->nListCommand($resourceId, $contentArray);
            case 'setsnippets':
                return $this->adminService->adminSetSnippets($resourceId, $contentArray);
            case 'setcredits':
                return $this->adminService->adminSetCredits($resourceId, $contentArray);
            case 'syslist':
                return $this->adminService->sysListCommand($resourceId);
            case 'toggleadminmode':
                return $this->adminService->adminToggleAdminMode($resourceId);
            case 'showusers':
                return $this->adminService->adminShowUsers($resourceId);
            case 'cybermap':
                return $this->adminService->showCyberspaceMap($resourceId);
            case 'setmotd':
                return $this->adminService->adminSetMotd($resourceId, $contentArray);
            case 'silenceplayer':
                return $this->adminService->silencePlayer($resourceId, $contentArray);
            case 'invokefile':
                return $this->adminService->invokeFile($resourceId, $contentArray);
            case 'setfileproperty':
                return $this->adminService->setfileproperty($resourceId, $contentArray);
            case 'invokefilemod':
                return $this->adminService->invokeFileMod($resourceId, $contentArray);
            case 'setfilemodproperty':
                return $this->adminService->setfilemodproperty($resourceId, $contentArray);
            case 'invokenpc':
                return $this->adminService->invokeNpc($resourceId, $contentArray);
            case 'setnpcproperty':
                return $this->adminService->setnpcproperty($resourceId, $contentArray);
            case 'setfiletypeproperty':
                return $this->adminService->setfiletypeproperty($resourceId, $contentArray);
            case 'setproperty':
                return $this->adminService->setPropertyCommand($resourceId, $contentArray);
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
                    case 'ls':
                        $additionalResponse = $this->nodeService->showNodeInfoNew($resourceId);
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
                    case 'closepanel':
                        $additionalResponse = [
                            'command' => 'closepanel',
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
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function parseMailInput(ConnectionInterface $from, $content = '', $mailOptions = array())
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        list($contentArray, $userCommand) = $this->prepareData($content);
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
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function parseCodeInput(ConnectionInterface $from, $content = '', $jobs = false)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        list($contentArray, $userCommand) = $this->prepareData($content);
        $codeOptions = (object)$clientData->codingOptions;
        switch ($userCommand) {
            default:
            case 'options':
                return $this->codingService->commandOptions($resourceId, $codeOptions);
            case 'code':
                return $this->codingService->commandCode($resourceId, $codeOptions, $contentArray);
            case 'jobs':
                return $this->profileService->showJobs($resourceId, $jobs);
            case 'level':
                return $this->codingService->commandLevel($resourceId, $contentArray);
            case 'mode':
                return $this->codingService->switchCodeMode($resourceId, $contentArray);
            case 'parts':
            case 'resources':
            case 'res':
                return $this->profileService->showFilePartInstances($resourceId, $contentArray);
            case 'type':
                return $this->codingService->commandType($resourceId, $contentArray, $codeOptions);
            case 'q':
                return $this->codingService->exitCodeMode($resourceId);
        }
    }

    /**
     * @param ConnectionInterface $from
     * @param string $content
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function parseConfirmInput(ConnectionInterface $from, $content = '')
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return false;
        $confirmData = (object)$clientData->confirm;
        if (!isset($confirmData->command)) return false;
        $response = false;
        if ($content == 'yes' || $content == 'y' || $content == 'confirm') {
            switch ($confirmData->command) {
                default:
                    break;
                case 'addnode':
                    $response = $this->nodeService->addNode($resourceId);
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
                case 'milkrun':
                    $response = $this->milkrunService->requestMilkrun($resourceId, $confirmData);
                    break;
                case 'mission':
                    $response = $this->missionService->requestMission($resourceId, $confirmData);
                    break;
            }
        }
        $this->getWebsocketServer()->setConfirm($resourceId, '');
        if (!$response) {
            $response = new GameClientResponse($resourceId);
            $response->addMessage($this->translator->translate('You cancel your action'), GameClientResponse::CLASS_WHITE);
            $response->addOption(GameClientResponse::OPT_EXITCONFIRMMODE, true);
        }
        else {
            $response->addOption(GameClientResponse::OPT_EXITCONFIRMMODE, true);
        }
        return $response->send();
    }

    /**
     * @param ConnectionInterface $from
     * @param $msgData
     * @return array|bool|false|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function parseFrontendInput(ConnectionInterface $from, $msgData)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $from->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        list($content, $entityId, $userCommand) = $this->prepareFrontendData($msgData);
        switch (strtolower($userCommand)) {
            default:
                break;
            case 'savefiledescription':
                return $this->fileService->saveFileDescription($resourceId, $content, $entityId);
            case 'savenodedescription':
                return $this->nodeService->saveNodeDescription($resourceId, $content, $entityId);
            case 'savemanpage':
                $mpTitle = (isset($msgData->title)) ? $msgData->title : false;
                $mpStatus = (isset($msgData->status)) ? $msgData->status : false;
                return $this->manpageService->saveManpage($resourceId, $content, $mpTitle, $entityId, $mpStatus);
            case 'sendmail':
                $recipient = (isset($msgData->recipient)) ? $msgData->recipient : null;
                $subject = (isset($msgData->subject)) ? $msgData->subject : false;
                return $this->mailMessageService->sendMail($resourceId, $content, $recipient, $subject);
            case 'savestory':
                $title = (isset($msgData->title)) ? $msgData->title: null;
                $status = (isset($msgData->status)) ? $msgData->status: false;
                $entityId = (isset($msgData->entityId)) ? $msgData->entityId: false;
                return $this->storyService->saveStoryCommand($resourceId, $entityId, $content, $title, $status);
            case 'savepassage':
                $title = (isset($msgData->title)) ? $msgData->title: null;
                $status = (isset($msgData->status)) ? $msgData->status: false;
                $entityId = (isset($msgData->entityId)) ? $msgData->entityId: false;
                $allowChoiceSubmissions = (isset($msgData->allowChoiceSubmissions)) ? $msgData->allowChoiceSubmissions: false;
                return $this->passageService->savePassageCommand($resourceId, $entityId, $content, $title, $status, $allowChoiceSubmissions);
        }
        return true;
    }

}
