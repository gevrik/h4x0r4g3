<?php

namespace Application\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Aivatar;
use Netrunners\Entity\Auction;
use Netrunners\Entity\AuctionBid;
use Netrunners\Entity\BannedIp;
use Netrunners\Entity\ChatChannel;
use Netrunners\Entity\CompanyName;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Effect;
use Netrunners\Entity\Faction;
use Netrunners\Entity\FactionRole;
use Netrunners\Entity\FactionRoleInstance;
use Netrunners\Entity\Feedback;
use Netrunners\Entity\File;
use Netrunners\Entity\FileCategory;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartInstance;
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeMod;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\GameOption;
use Netrunners\Entity\GameOptionInstance;
use Netrunners\Entity\Geocoord;
use Netrunners\Entity\Group;
use Netrunners\Entity\GroupRole;
use Netrunners\Entity\GroupRoleInstance;
use Netrunners\Entity\Invitation;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\MailMessage;
use Netrunners\Entity\Manpage;
use Netrunners\Entity\Milkrun;
use Netrunners\Entity\MilkrunAivatar;
use Netrunners\Entity\MilkrunAivatarInstance;
use Netrunners\Entity\MilkrunIce;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Mission;
use Netrunners\Entity\MissionArchetype;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\PlaySession;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileEffect;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Entity\ProfileRating;
use Netrunners\Entity\ServerSetting;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Entity\SystemLog;
use Netrunners\Entity\Word;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\BannedIpRepository;
use Netrunners\Repository\GeocoordRepository;
use Netrunners\Repository\PlaySessionRepository;
use Netrunners\Service\LoginService;
use Netrunners\Service\LoopService;
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
     * @const LOOP_TIME_COMBAT the amount of seconds between combat rounds
     */
    const LOOP_TIME_COMBAT = 2;

    /**
     * @const LOOP_TIME_RESOURCES the amount of seconds between resource gain checks
     */
    const LOOP_TIME_RESOURCES = 900;

    /**
     * @const LOOP_NPC_SPAWN the amount of seconds between npc spawn checks
     */
    const LOOP_NPC_SPAWN = 600;

    /**
     * @const LOOP_REGENERATION the amount of seconds between regenerations
     */
    const LOOP_REGENERATION = 120;

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
     * @var LoginService
     */
    protected $loginService;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $hash;

    /**
     * @var array
     */
    protected $combatants = [
        'npcs' => [],
        'profiles' => []
    ];

    /**
     * @var array
     */
    protected $users = [];

    /**
     * @var array
     */
    protected $roles = [];

    /**
     * @var array
     */
    protected $aivatars = [];

    /**
     * @var array
     */
    protected $auctions = [];

    /**
     * @var array
     */
    protected $auctionbids = [];

    /**
     * @var array
     */
    protected $bannedips = [];

    /**
     * @var array
     */
    protected $chatchannels = [];

    /**
     * @var array
     */
    protected $companynames = [];

    /**
     * @var array
     */
    protected $connections = [];

    /**
     * @var array
     */
    protected $effects = [];

    /**
     * @var array
     */
    protected $factions = [];

    /**
     * @var array
     */
    protected $factionroles = [];

    /**
     * @var array
     */
    protected $feedbacks = [];

    /**
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $filecategories = [];

    /**
     * @var array
     */
    protected $filemods = [];

    /**
     * @var array
     */
    protected $filemodinstances = [];

    /**
     * @var array
     */
    protected $factionroleinstances = [];

    /**
     * @var array
     */
    protected $fileparts = [];

    /**
     * @var array
     */
    protected $filepartinstances = [];

    /**
     * @var array
     */
    protected $filepartskills = [];

    /**
     * @var array
     */
    protected $filetypes = [];

    /**
     * @var array
     */
    protected $filetypemods = [];

    /**
     * @var array
     */
    protected $filetypeskills = [];

    /**
     * @var array
     */
    protected $gameoptions = [];

    /**
     * @var array
     */
    protected $gameoptioninstances = [];

    /**
     * @var array
     */
    protected $geocoords = [];

    /**
     * @var array
     */
    protected $groups = [];

    /**
     * @var array
     */
    protected $grouproles = [];

    /**
     * @var array
     */
    protected $grouproleinstances = [];

    /**
     * @var array
     */
    protected $invitations = [];

    /**
     * @var array
     */
    protected $knownnodes = [];

    /**
     * @var array
     */
    protected $mailmessages = [];

    /**
     * @var array
     */
    protected $manpages = [];

    /**
     * @var array
     */
    protected $milkruns = [];

    /**
     * @var array
     */
    protected $milkrunaivatars = [];

    /**
     * @var array
     */
    protected $milkrunaivatarinstances = [];

    /**
     * @var array
     */
    protected $milkrunice = [];

    /**
     * @var array
     */
    protected $milkruninstances = [];

    /**
     * @var array
     */
    protected $missions = [];

    /**
     * @var array
     */
    protected $missionarchetypes = [];

    /**
     * @var array
     */
    protected $nodes = [];

    /**
     * @var array
     */
    protected $nodetypes = [];

    /**
     * @var array
     */
    protected $notifications = [];

    /**
     * @var array
     */
    protected $npcs = [];

    /**
     * @var array
     */
    protected $npcinstances = [];

    /**
     * @var array
     */
    protected $playsessions = [];

    /**
     * @var array
     */
    protected $profiles = [];

    /**
     * @var array
     */
    protected $profileeffects = [];

    /**
     * @var array
     */
    protected $profilefactionratings = [];

    /**
     * @var array
     */
    protected $profileratings = [];

    /**
     * @var array
     */
    protected $serversettings = [];

    /**
     * @var array
     */
    protected $skills = [];

    /**
     * @var array
     */
    protected $skillratings = [];

    /**
     * @var array
     */
    protected $systems = [];

    /**
     * @var array
     */
    protected $systemlogs = [];

    /**
     * @var array
     */
    protected $words = [];

    /**
     * WebsocketService constructor.
     * @param EntityManager $entityManager
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param LoopService $loopService
     * @param LoginService $loginService
     * @param LoopInterface $loop
     * @param $hash
     * @param $adminMode
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function __construct(
        EntityManager $entityManager,
        UtilityService $utilityService,
        ParserService $parserService,
        LoopService $loopService,
        LoginService $loginService,
        LoopInterface $loop,
        $hash,
        $adminMode
    ) {
        $this->clients = new \SplObjectStorage;
        $this->entityManager = $entityManager;
        $this->utilityService = $utilityService;
        $this->parserService = $parserService;
        $this->loopService = $loopService;
        $this->loginService = $loginService;
        $this->loop = $loop;
        $this->hash = $hash;
        $this->setAdminMode($adminMode);

        $this->logger = new Logger();
        $this->logger->addWriter('stream', null, array('stream' => getcwd() . '/data/log/command_log.txt'));

        /* DATABASE CLEANUP */

        // clear orphaned play-sessions
        $playSessionRepo = $this->entityManager->getRepository('Netrunners\Entity\PlaySession');
        /** @var PlaySessionRepository $playSessionRepo */
        foreach ($playSessionRepo->findOrphaned() as $orphanedPlaySession) {
            $this->entityManager->remove($orphanedPlaySession);
        }
        // clear all current-resource-id properties of all profiles
        $profiles = $this->entityManager->getRepository('Netrunners\Entity\Profile')->findAll();
        foreach ($profiles as $profile) {
            /** @var Profile $profile */
            $profile->setCurrentResourceId(NULL);
        }
        $this->entityManager->flush();

        /* LOAD DATABASE INTO MEMORY */

        // user
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u');
        $qb->from('TmoAuth\Entity\User', 'u');
        $users = $qb->getQuery()->getResult();
        foreach ($users as $user) {
            /** @var User $user */
            $this->users[$user->getId()] = [
                'id' => $user->getId(),
                'profileId' => $user->getProfile()->getId(),
                'username' => $user->getUsername(),
                'banned' => $user->getBanned(),
                'displayName' => $user->getDisplayName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'state' => $user->getState(),
                'roles' => []
            ];
            foreach ($user->getRoles() as $role) {
                /** @var Role $role */
                $this->users[$user->getId()]['roles'][] = $role->getId();
            }
        }

        // role
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r');
        $qb->from('TmoAuth\Entity\Role', 'r');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Role $entity */
            $this->roles[$entity->getId()] = [
                'id' => $entity->getId(),
                'roleId' => $entity->getRoleId(),
                'parentId' => ($entity->getParent()) ? $entity->getParent()->getId() : NULL,
            ];
        }

        // aivatar
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a');
        $qb->from('Netrunners\Entity\Aivatar', 'a');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Aivatar $entity */
            $this->aivatars[$entity->getId()] = [
                'id' => $entity->getId(),
                'nodeId' => $entity->getNode()->getId(),
                'coderId' => $entity->getCoder()->getId(),
                'profileId' => $entity->getProfile()->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'level' => $entity->getLevel(),
                'added' => $entity->getAdded(),
                'modified' => $entity->getModified(),
            ];
        }

        // auction
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a');
        $qb->from('Netrunners\Entity\Auction', 'a');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Auction $entity */
            $this->auctions[$entity->getId()] = [
                'id' => $entity->getId(),
                'expires' => $entity->getExpires(),
                'nodeId' => $entity->getNode()->getId(),
                'added' => $entity->getAdded(),
                'bought' => $entity->getBought(),
                'fileId' => ($entity->getFile()) ? $entity->getFile()->getId() : NULL,
                'buyoutPrice' => $entity->getBuyoutPrice(),
                'claimed' => $entity->getClaimed(),
                'currentPrice' => $entity->getCurrentPrice(),
                'buyerId' => ($entity->getBuyer()) ? $entity->getBuyer()->getId() : NULL,
                'auctioneerId' => ($entity->getAuctioneer()) ? $entity->getAuctioneer()->getId() : NULL,
                'startingPrice' => $entity->getStartingPrice(),
            ];
        }

        // auctionbid
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ab');
        $qb->from('Netrunners\Entity\AuctionBid', 'ab');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var AuctionBid $entity */
            $this->auctionbids[$entity->getId()] = [
                'id' => $entity->getId(),
                'bid' => $entity->getBid(),
                'added' => $entity->getAdded(),
                'modified' => $entity->getModified(),
                'auctionId' => ($entity->getAuction()) ? $entity->getAuction()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
            ];
        }

        // bannedip
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('bi');
        $qb->from('Netrunners\Entity\BannedIp', 'bi');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var BannedIp $entity */
            $this->bannedips[$entity->getId()] = [
                'id' => $entity->getId(),
                'ip' => $entity->getIp(),
                'added' => $entity->getAdded(),
                'bannerId' => ($entity->getBanner()) ? $entity->getBanner()->getId() : NULL,
            ];
        }

        // chat channel
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('cc');
        $qb->from('Netrunners\Entity\ChatChannel', 'cc');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var ChatChannel $entity */
            $this->chatchannels[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'joinable' => $entity->getJoinable(),
                'added' => $entity->getAdded(),
                'ownerId' => ($entity->getOwner()) ? $entity->getOwner()->getId() : NULL,
                'factionId' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'groupId' => ($entity->getGroup()) ? $entity->getGroup()->getId() : NULL,
            ];
        }

        // company name
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('cn');
        $qb->from('Netrunners\Entity\CompanyName', 'cn');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var CompanyName $entity */
            $this->companynames[$entity->getId()] = [
                'id' => $entity->getId(),
                'content' => $entity->getContent(),
            ];
        }

        // connection
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c');
        $qb->from('Netrunners\Entity\Connection', 'c');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Connection $entity */
            $this->connections[$entity->getId()] = [
                'id' => $entity->getId(),
                'type' => $entity->getType(),
                'level' => $entity->getLevel(),
                'created' => $entity->getCreated(),
                'isOpen' => $entity->getisOpen(),
                'sourceNodeId' => ($entity->getSourceNode()) ? $entity->getSourceNode()->getId() : NULL,
                'targetNodeId' => ($entity->getTargetNode()) ? $entity->getTargetNode()->getId() : NULL,
            ];
        }

        // effect
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e');
        $qb->from('Netrunners\Entity\Effect', 'e');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Effect $entity */
            $this->effects[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'expireTimer' => $entity->getExpireTimer(),
                'dimishTimer' => $entity->getDimishTimer(),
                'diminishValue' => $entity->getDiminishValue(),
                'immuneTimer' => $entity->getImmuneTimer(),
                'defaultRating' => $entity->getDefaultRating(),
            ];
        }

        // faction
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f');
        $qb->from('Netrunners\Entity\Faction', 'f');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Faction $entity */
            $this->factions[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'playerRun' => $entity->getPlayerRun(),
                'joinable' => $entity->getJoinable(),
                'credits' => $entity->getCredits(),
                'snippets' => $entity->getSnippets(),
                'added' => $entity->getAdded(),
                'openRecruitment' => $entity->getOpenRecruitment(),
            ];
        }

        // faction role
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fr');
        $qb->from('Netrunners\Entity\FactionRole', 'fr');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FactionRole $entity */
            $this->factionroles[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
            ];
        }

        // faction role instance
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fri');
        $qb->from('Netrunners\Entity\FactionRoleInstance', 'fri');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FactionRoleInstance $entity */
            $this->factionroleinstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'added' => $entity->getAdded(),
                'factionId' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'factionRoleId' => ($entity->getFactionRole()) ? $entity->getFactionRole()->getId() : NULL,
                'memberId' => ($entity->getMember()) ? $entity->getMember()->getId() : NULL,
                'changerId' => ($entity->getChanger()) ? $entity->getChanger()->getId() : NULL,
            ];
        }

        // feedback
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fb');
        $qb->from('Netrunners\Entity\Feedback', 'fb');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Feedback $entity */
            $this->feedbacks[$entity->getId()] = [
                'id' => $entity->getId(),
                'subject' => $entity->getSubject(),
                'description' => $entity->getDescription(),
                'added' => $entity->getAdded(),
                'type' => $entity->getType(),
                'status' => $entity->getStatus(),
                'internalData' => $entity->getInternalData(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
            ];
        }

        // file
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fi');
        $qb->from('Netrunners\Entity\File', 'fi');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var File $entity */
            $this->files[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'size' => $entity->getSize(),
                'level' => $entity->getLevel(),
                'maxIntegrity' => $entity->getMaxIntegrity(),
                'integrity' => $entity->getIntegrity(),
                'created' => $entity->getCreated(),
                'modified' => $entity->getModified(),
                'executable' => $entity->getExecutable(),
                'running' => $entity->getRunning(),
                'version' => $entity->getVersion(),
                'slots' => $entity->getSlots(),
                'data' => $entity->getData(),
                'content' => $entity->getContent(),
                'fileTypeId' => ($entity->getFileType()) ? $entity->getFileType()->getId() : NULL,
                'coderId' => ($entity->getCoder()) ? $entity->getCoder()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'systemId' => ($entity->getSystem()) ? $entity->getSystem()->getId() : NULL,
                'nodeId' => ($entity->getNode()) ? $entity->getNode()->getId() : NULL,
                'mailMessageId' => ($entity->getMailMessage()) ? $entity->getMailMessage()->getId() : NULL,
                'npcId' => ($entity->getNpc()) ? $entity->getNpc()->getId() : NULL,
            ];
        }

        // file category
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fc');
        $qb->from('Netrunners\Entity\FileCategory', 'fc');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FileCategory $entity */
            $this->filecategories[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'researchable' => $entity->getResearchable(),
            ];
        }

        // file mod
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fm');
        $qb->from('Netrunners\Entity\FileMod', 'fm');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FileMod $entity */
            $this->filemods[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'fileparts' => [],
            ];
            foreach ($entity->getFileParts() as $filePart) {
                /** @var FilePart $filePart */
                $this->filemods[$entity->getId()]['fileparts'][] = $filePart->getId();
            }
        }

        // file mod instance
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fmi');
        $qb->from('Netrunners\Entity\FileModInstance', 'fmi');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FileModInstance $entity */
            $this->filemodinstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'level' => $entity->getLevel(),
                'added' => $entity->getAdded(),
                'fileId' => ($entity->getFile()) ? $entity->getFile()->getId() : NULL,
                'fileModId' => ($entity->getFileMod()) ? $entity->getFileMod()->getId() : NULL,
                'coderId' => ($entity->getCoder()) ? $entity->getCoder()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
            ];
        }

        // file part
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fp');
        $qb->from('Netrunners\Entity\FilePart', 'fp');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FilePart $entity */
            $this->fileparts[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'type' => $entity->getType(),
                'level' => $entity->getLevel(),
            ];
        }

        // file part instances
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fpi');
        $qb->from('Netrunners\Entity\FilePartInstance', 'fpi');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FilePartInstance $entity */
            $this->filepartinstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'coderId' => ($entity->getCoder()) ? $entity->getCoder()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'filePartId' => ($entity->getFilePart()) ? $entity->getFilePart()->getId() : NULL,
                'level' => $entity->getLevel(),
            ];
        }

        // file part skills
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fps');
        $qb->from('Netrunners\Entity\FilePartSkill', 'fps');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FilePartSkill $entity */
            $this->filepartskills[$entity->getId()] = [
                'id' => $entity->getId(),
                'skillId' => ($entity->getSkill()) ? $entity->getSkill()->getId() : NULL,
                'filePartId' => ($entity->getFilePart()) ? $entity->getFilePart()->getId() : NULL,
            ];
        }

        // file types
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ft');
        $qb->from('Netrunners\Entity\FileType', 'ft');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FileType $entity */
            $this->filetypes[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'codeable' => $entity->getCodable(),
                'executable' => $entity->getExecutable(),
                'size' => $entity->getSize(),
                'executionTime' => $entity->getExecutionTime(),
                'fullblock' => $entity->getFullblock(),
                'blocking' => $entity->getBlocking(),
                'stealthing' => $entity->getStealthing(),
                'needRecipe' => $entity->getNeedRecipe(),
            ];
            foreach ($entity->getFileCategories() as $fileCategory) {
                /** @var FileCategory $fileCategory */
                $this->filetypes[$entity->getId()]['filecategories'][] = $fileCategory->getId();
            }
            foreach ($entity->getFileParts() as $filePart) {
                /** @var FilePart $filePart */
                $this->filetypes[$entity->getId()]['fileparts'][] = $filePart->getId();
            }
            foreach ($entity->getOptionalFileParts() as $optionalFilePart) {
                /** @var FilePart $optionalFilePart */
                $this->filetypes[$entity->getId()]['optionalfileparts'][] = $optionalFilePart->getId();
            }
        }

        // file type mods
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ftm');
        $qb->from('Netrunners\Entity\FileTypeMod', 'ftm');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FileTypeMod $entity */
            $this->filetypemods[$entity->getId()] = [
                'id' => $entity->getId(),
                'fileTypeId' => ($entity->getFileType()) ? $entity->getFileType()->getId() : NULL,
                'fileModId' => ($entity->getFileMod()) ? $entity->getFileMod()->getId() : NULL,
            ];
        }

        // file type skills
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('fts');
        $qb->from('Netrunners\Entity\FileTypeSkill', 'fts');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var FileTypeSkill $entity */
            $this->filetypeskills[$entity->getId()] = [
                'id' => $entity->getId(),
                'skillId' => ($entity->getSkill()) ? $entity->getSkill()->getId() : NULL,
                'fileTypeId' => ($entity->getFileType()) ? $entity->getFileType()->getId() : NULL,
            ];
        }

        // game options
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('go');
        $qb->from('Netrunners\Entity\GameOption', 'go');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var GameOption $entity */
            $this->gameoptions[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'defaultStatus' => $entity->getDefaultStatus(),
                'defaultValue' => $entity->getDefaultValue(),
            ];
        }

        // game option instances
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('goi');
        $qb->from('Netrunners\Entity\GameOptionInstance', 'goi');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var GameOptionInstance $entity */
            $this->gameoptioninstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'status' => $entity->getStatus(),
                'changed' => $entity->getChanged(),
                'gameOptionId' => ($entity->getGameOption()) ? $entity->getGameOption()->getId() : NULL,
                'value' => $entity->getValue(),
            ];
        }

        // geocoords
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('geo');
        $qb->from('Netrunners\Entity\Geocoord', 'geo');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Geocoord $entity */
            $this->geocoords[$entity->getId()] = [
                'id' => $entity->getId(),
                'lat' => $entity->getLat(),
                'lng' => $entity->getLng(),
                'placeId' => $entity->getPlaceId(),
                'added' => $entity->getAdded(),
                'data' => $entity->getData(),
                'zone' => $entity->getZone(),
            ];
        }

        // groups
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('g');
        $qb->from('Netrunners\Entity\Group', 'g');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Group $entity */
            $this->groups[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'credits' => $entity->getCredits(),
                'snippets' => $entity->getSnippets(),
                'added' => $entity->getAdded(),
                'factionid' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
            ];
        }

        // group roles
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('gr');
        $qb->from('Netrunners\Entity\GroupRole', 'gr');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var GroupRole $entity */
            $this->grouproles[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
            ];
        }

        // group role instances
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('gri');
        $qb->from('Netrunners\Entity\GroupRoleInstance', 'gri');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var GroupRoleInstance $entity */
            $this->grouproleinstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'groupId' => ($entity->getGroup()) ? $entity->getGroup()->getId() : NULL,
                'memberId' => ($entity->getMember()) ? $entity->getMember()->getId() : NULL,
                'changerId' => ($entity->getChanger()) ? $entity->getChanger()->getId() : NULL,
                'added' => $entity->getAdded(),
                'groupRoleId' => ($entity->getGroupRole()) ? $entity->getGroupRole()->getId() : NULL,
            ];
        }

        // invitation codes
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('i');
        $qb->from('Netrunners\Entity\Invitation', 'i');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Invitation $entity */
            $this->invitations[$entity->getId()] = [
                'id' => $entity->getId(),
                'code' => $entity->getCode(),
                'given' => $entity->getGiven(),
                'used' => $entity->getUsed(),
                'special' => $entity->getSpecial(),
                'givenToId' => ($entity->getGivenTo()) ? $entity->getGivenTo()->getId() : NULL,
                'usedById' => ($entity->getUsedBy()) ? $entity->getUsedBy()->getId() : NULL,
            ];
        }

        // known nodes
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('kn');
        $qb->from('Netrunners\Entity\KnownNode', 'kn');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var KnownNode $entity */
            $this->knownnodes[$entity->getId()] = [
                'nodeId' => ($entity->getNode()) ? $entity->getNode()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'created' => $entity->getCreated(),
                'type' => $entity->getType(),
            ];
        }

        // mail messages
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('mm');
        $qb->from('Netrunners\Entity\MailMessage', 'mm');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var MailMessage $entity */
            $this->mailmessages[$entity->getId()] = [
                'id' => $entity->getId(),
                'authorId' => ($entity->getAuthor()) ? $entity->getAuthor()->getId() : NULL,
                'recipientId' => ($entity->getRecipient()) ? $entity->getRecipient()->getId() : NULL,
                'parentId' => ($entity->getParent()) ? $entity->getParent()->getId() : NULL,
                'subject' => $entity->getSubject(),
                'content' => $entity->getContent(),
                'sentDateTime' => $entity->getSentDateTime(),
                'readDateTime' => $entity->getReadDateTime(),
            ];
        }

        // manpages
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('man');
        $qb->from('Netrunners\Entity\Manpage', 'man');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Manpage $entity */
            $this->manpages[$entity->getId()] = [
                'id' => $entity->getId(),
                'authorId' => ($entity->getAuthor()) ? $entity->getAuthor()->getId() : NULL,
                'parentId' => ($entity->getParent()) ? $entity->getParent()->getId() : NULL,
                'subject' => $entity->getSubject(),
                'content' => $entity->getContent(),
                'createdDateTime' => $entity->getCreatedDateTime(),
                'updatedDateTime' => $entity->getUpdatedDateTime(),
                'status' => $entity->getStatus(),
            ];
        }

        // milkruns
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('milk');
        $qb->from('Netrunners\Entity\Milkrun', 'milk');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Milkrun $entity */
            $this->milkruns[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'credits' => $entity->getCredits(),
                'snippets' => $entity->getSnippets(),
                'level' => $entity->getLevel(),
                'timer' => $entity->getTimer(),
                'factionRoleId' => ($entity->getFactionRole()) ? $entity->getFactionRole()->getId() : NULL,
            ];
        }

        // milkrun aivatars
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('milkai');
        $qb->from('Netrunners\Entity\MilkrunAivatar', 'milkai');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var MilkrunAivatar $entity */
            $this->milkrunaivatars[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'baseEeg' => $entity->getBaseEeg(),
                'baseAttack' => $entity->getBaseAttack(),
                'baseArmor' => $entity->getBaseArmor(),
                'specials' => $entity->getSpecials(),
            ];
        }

        // milkrun aivatar instances
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('mai');
        $qb->from('Netrunners\Entity\MilkrunAivatarInstance', 'mai');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var MilkrunAivatarInstance $entity */
            $this->milkrunaivatarinstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'name' => $entity->getName(),
                'maxEeg' => $entity->getMaxEeg(),
                'currentEeg' => $entity->getCurrentEeg(),
                'maxAttack' => $entity->getMaxAttack(),
                'currentAttack' => $entity->getCurrentAttack(),
                'maxArmor' => $entity->getMaxArmor(),
                'currentArmor' => $entity->getCurrentArmor(),
                'specials' => $entity->getSpecials(),
                'completed' => $entity->getCompleted(),
                'pointsearned' => $entity->getPointsearned(),
                'pointsused' => $entity->getPointsused(),
                'created' => $entity->getCreated(),
                'modified' => $entity->getModified(),
                'upgrades' => $entity->getUpgrades(),
                'milkrunAivatarId' => ($entity->getMilkrunAivatar()) ? $entity->getMilkrunAivatar()->getId() : NULL,
            ];
        }

        // milkrun ice
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('mri');
        $qb->from('Netrunners\Entity\MilkrunIce', 'mri');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var MilkrunIce $entity */
            $this->milkrunice[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'baseEeg' => $entity->getBaseEeg(),
                'baseAttack' => $entity->getBaseAttack(),
                'baseArmor' => $entity->getBaseArmor(),
                'specials' => $entity->getSpecials(),
            ];
        }

        // milkrun instances
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('mrinst');
        $qb->from('Netrunners\Entity\MilkrunInstance', 'mrinst');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var MilkrunInstance $entity */
            $this->milkruninstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'added' => $entity->getAdded(),
                'expires' => $entity->getExpires(),
                'level' => $entity->getLevel(),
                'sourceFactionId' => ($entity->getSourceFaction()) ? $entity->getSourceFaction()->getId() : NULL,
                'targetFactionId' => ($entity->getTargetFaction()) ? $entity->getTargetFaction()->getId() : NULL,
                'completed' => $entity->getCompleted(),
                'milkrunId' => ($entity->getMilkrun()) ? $entity->getMilkrun()->getId() : NULL,
                'expired' => $entity->getExpired(),
                'milkrunAvaitarInstanceId' => ($entity->getMilkrunAivatarInstance()) ? $entity->getMilkrunAivatarInstance()->getId() : NULL,
            ];
        }

        // missions
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('m');
        $qb->from('Netrunners\Entity\Mission', 'm');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Mission $entity */
            $this->missions[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'missionid' => ($entity->getMission()) ? $entity->getMission()->getId() : NULL,
                'added' => $entity->getAdded(),
                'completed' => $entity->getCompleted(),
                'expires' => $entity->getExpires(),
                'level' => $entity->getLevel(),
                'expired' => $entity->getExpired(),
                'sourceFactionId' => ($entity->getSourceFaction()) ? $entity->getSourceFaction()->getId() : NULL,
                'targetFactionId' => ($entity->getTargetFaction()) ? $entity->getTargetFaction()->getId() : NULL,
                'targetSystemId' => ($entity->getTargetSystem()) ? $entity->getTargetSystem()->getId() : NULL,
                'targetFileId' => ($entity->getTargetFile()) ? $entity->getTargetFile()->getId() : NULL,
                'targetNodeId' => ($entity->getTargetNode()) ? $entity->getTargetNode()->getId() : NULL,
            ];
        }

        // mission archetypes
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ma');
        $qb->from('Netrunners\Entity\MissionArchetype', 'ma');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var MissionArchetype $entity */
            $this->missionarchetypes[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'subtype' => $entity->getSubtype(),
            ];
        }

        // nodes
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('n');
        $qb->from('Netrunners\Entity\Node', 'n');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Node $entity */
            $this->nodes[$entity->getId()] = [
                'id' => $entity->getId(),
                'systemId' => ($entity->getSystem()) ? $entity->getSystem()->getId() : NULL,
                'name' => $entity->getName(),
                'level' => $entity->getLevel(),
                'created' => $entity->getCreated(),
                'description' => $entity->getDescription(),
                'nodeTypeId' => ($entity->getNodeType()) ? $entity->getNodeType()->getId() : NULL,
                'nomob' => $entity->getNomob(),
                'nopvp' => $entity->getNopvp(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'noclaim' => $entity->getNoclaim(),
                'integrity' => $entity->getIntegrity(),
                'data' => $entity->getData(),
            ];
        }

        // node types
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('nt');
        $qb->from('Netrunners\Entity\NodeType', 'nt');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var NodeType $entity */
            $this->nodetypes[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'shortName' => $entity->getShortName(),
                'description' => $entity->getDescription(),
                'cost' => $entity->getCost(),
            ];
        }

        // notifications
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('note');
        $qb->from('Netrunners\Entity\Notification', 'note');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Notification $entity */
            $this->notifications[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'subject' => $entity->getSubject(),
                'sentDateTime' => $entity->getSentDateTime(),
                'readDateTime' => $entity->getReadDateTime(),
                'severity' => $entity->getSeverity(),
            ];
        }

        // npcs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('npc');
        $qb->from('Netrunners\Entity\Npc', 'npc');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Npc $entity */
            $this->npcs[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'baseEeg' => $entity->getBaseEeg(),
                'baseSnippets' => $entity->getBaseSnippets(),
                'baseCredits' => $entity->getBaseCredits(),
                'level' => $entity->getLevel(),
                'baseBlade' => $entity->getBaseBlade(),
                'baseBlaster' => $entity->getBaseBlaster(),
                'baseShield' => $entity->getBaseShield(),
                'baseDetection' => $entity->getBaseDetection(),
                'baseStealth' => $entity->getBaseStealth(),
                'baseSlots' => $entity->getBaseSlots(),
                'aggressive' => $entity->getAggressive(),
                'roaming' => $entity->getRoaming(),
                'type' => $entity->getType(),
                'stealthing' => $entity->getStealthing(),
                'social' => $entity->getSocial(),
            ];
        }

        // npc instances
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('npci');
        $qb->from('Netrunners\Entity\NpcInstance', 'npci');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var NpcInstance $entity */
            $this->npcinstances[$entity->getId()] = [
                'id' => $entity->getId(),
                'npcId' => ($entity->getNpc()) ? $entity->getNpc()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'nodeId' => ($entity->getNode()) ? $entity->getNode()->getId() : NULL,
                'groupId' => ($entity->getGroup()) ? $entity->getGroup()->getId() : NULL,
                'factionId' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'maxEeg' => $entity->getMaxEeg(),
                'currentEeg' => $entity->getCurrentEeg(),
                'snippets' => $entity->getSnippets(),
                'credits' => $entity->getCredits(),
                'level' => $entity->getLevel(),
                'slots' => $entity->getSlots(),
                'aggressive' => $entity->getAggressive(),
                'added' => $entity->getAdded(),
                'homeNodeId' => ($entity->getHomeNode()) ? $entity->getHomeNode()->getId() : NULL,
                'roaming' => $entity->getRoaming(),
                'systemId' => ($entity->getSystem()) ? $entity->getSystem()->getId() : NULL,
                'homeSystemId' => ($entity->getHomeSystem()) ? $entity->getHomeSystem()->getId() : NULL,
                'stealthing' => $entity->getStealthing(),
                'bypassCodegates' => $entity->getBypassCodegates(),
                'bladeModuleId' => ($entity->getBladeModule()) ? $entity->getBladeModule()->getId() : NULL,
                'blasterModuleId' => ($entity->getBlasterModule()) ? $entity->getBlasterModule()->getId() : NULL,
                'shieldModuleId' => ($entity->getShieldModule()) ? $entity->getShieldModule()->getId() : NULL,
            ];
        }

        // play sessions
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ps');
        $qb->from('Netrunners\Entity\PlaySession', 'ps');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var PlaySession $entity */
            $this->playsessions[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'start' => $entity->getStart(),
                'end' => $entity->getEnd(),
                'ipAddy' => $entity->getIpAddy(),
                'socketId' => $entity->getSocketId(),
            ];
        }

        // profiles
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p');
        $qb->from('Netrunners\Entity\Profile', 'p');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Profile $entity */
            $this->profiles[$entity->getId()] = [
                'id' => $entity->getId(),
                'userId' => ($entity->getUser()) ? $entity->getUser()->getId() : NULL,
                'credits' => $entity->getCredits(),
                'snippets' => $entity->getSnippets(),
                'currentNodeId' => ($entity->getCurrentNode()) ? $entity->getCurrentNode()->getId() : NULL,
                'skillPoints' => $entity->getSkillPoints(),
                'homeNodeId' => ($entity->getHomeNode()) ? $entity->getHomeNode()->getId() : NULL,
                'eeg' => $entity->getEeg(),
                'willpower' => $entity->getWillpower(),
                'factionId' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'groupId' => ($entity->getGroup()) ? $entity->getGroup()->getId() : NULL,
                'securityRating' => $entity->getSecurityRating(),
                'email' => $entity->getEmail(),
                'locale' => $entity->getLocale(),
                'bladeId' => ($entity->getBlade()) ? $entity->getBlade()->getId() : NULL,
                'blasterId' => ($entity->getBlaster()) ? $entity->getBlaster()->getId() : NULL,
                'shieldId' => ($entity->getShield()) ? $entity->getShield()->getId() : NULL,
                'headArmorId' => ($entity->getHeadArmor()) ? $entity->getHeadArmor()->getId() : NULL,
                'shoulderArmorId' => ($entity->getShoulderArmor()) ? $entity->getShoulderArmor()->getId() : NULL,
                'upperArmArmorId' => ($entity->getUpperArmArmor()) ? $entity->getUpperArmArmor()->getId() : NULL,
                'lowerArmArmorId' => ($entity->getLowerArmArmor()) ? $entity->getLowerArmArmor()->getId() : NULL,
                'handArmorId' => ($entity->getHandArmor()) ? $entity->getHandArmor()->getId() : NULL,
                'torsoArmorId' => ($entity->getTorsoArmor()) ? $entity->getTorsoArmor()->getId() : NULL,
                'legArmorId' => ($entity->getLegArmor()) ? $entity->getLegArmor()->getId() : NULL,
                'shoesArmorId' => ($entity->getShoesArmor()) ? $entity->getShoesArmor()->getId() : NULL,
                'stealthing' => $entity->getStealthing(),
                'factionJoinBlockData' => $entity->getFactionJoinBlockDate(),
                'completedMilkruns' => $entity->getCompletedMilkruns(),
                'failedMilkruns' => $entity->getFaileddMilkruns(),
                'bankBalance' => $entity->getBankBalance(),
                'bgopacity' => $entity->getBgopacity(),
                'currentResourceId' => $entity->getCurrentResourceId(),
                'defaultMilkrunAivatarId' => ($entity->getDefaultMilkrunAivatar()) ? $entity->getDefaultMilkrunAivatar()->getId() : NULL,
                'completedMissions' => $entity->getCompletedMissions(),
                'failedMissions' => $entity->getFailedMissions(),
                'currentPlayStoryId' => ($entity->getCurrentPlayStory()) ? $entity->getCurrentPlayStory()->getId() : NULL,
                'currentEditorStoryId' => ($entity->getCurrentEditorStory()) ? $entity->getCurrentEditorStory()->getId() : NULL,
            ];
        }

        // profile effects
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('pe');
        $qb->from('Netrunners\Entity\ProfileEffect', 'pe');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var ProfileEffect $entity */
            $this->profileeffects[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'effectId' => ($entity->getEffect()) ? $entity->getEffect()->getId() : NULL,
                'expires' => $entity->getExpires(),
                'rating' => $entity->getRating(),
                'npcInstanceId' => ($entity->getNpcInstance()) ? $entity->getNpcInstance()->getId() : NULL,
                'diminishUntil' => $entity->getDimishUntil(),
                'immuneUntil' => $entity->getImmuneUntil(),
            ];
        }

        // profile faction ratings
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('pfr');
        $qb->from('Netrunners\Entity\ProfileFactionRating', 'pfr');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var ProfileFactionRating $entity */
            $this->profilefactionratings[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'raterId' => ($entity->getRater()) ? $entity->getRater()->getId() : NULL,
                'added' => $entity->getAdded(),
                'sourceRating' => $entity->getSourceRating(),
                'targetRating' => $entity->getTargetRating(),
                'source' => $entity->getSource(),
                'milkrunInstanceId' => ($entity->getMilkrunInstance()) ? $entity->getMilkrunInstance()->getId() : NULL,
                'sourceFactionId' => ($entity->getSourceFaction()) ? $entity->getSourceFaction()->getId() : NULL,
                'targetFactionId' => ($entity->getTargetFaction()) ? $entity->getTargetFaction()->getId() : NULL,
                'missionId' => ($entity->getMission()) ? $entity->getMission()->getId() : NULL,
            ];
        }

        // profile ratings
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('pr');
        $qb->from('Netrunners\Entity\ProfileRating', 'pr');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var ProfileRating $entity */
            $this->profileratings[$entity->getId()] = [
                'id' => $entity->getId(),
                'sourceProfileId' => ($entity->getSourceProfile()) ? $entity->getSourceProfile()->getId() : NULL,
                'targetProfileId' => ($entity->getTargetProfile()) ? $entity->getTargetProfile()->getId() : NULL,
                'changed' => $entity->getChanged(),
                'rating' => $entity->getRating(),
            ];
        }

        // server settings
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ss');
        $qb->from('Netrunners\Entity\ServerSetting', 'ss');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var ServerSetting $entity */
            $this->serversettings[$entity->getId()] = [
                'id' => $entity->getId(),
                'wildernessSystemId' => $entity->getWildernessSystemId(),
                'chatsuboSystemId' => $entity->getChatsuboSystemId(),
                'wildernessHubNodeId' => $entity->getWildernessHubNodeId(),
                'chatsuboNodeId' => $entity->getChatsuboNodeId(),
                'motd' => $entity->getMotd(),
            ];
        }

        // skills
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('sk');
        $qb->from('Netrunners\Entity\Skill', 'sk');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Skill $entity */
            $this->skills[$entity->getId()] = [
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'description' => $entity->getDescription(),
                'advanced' => $entity->getAdvanced(),
                'added' => $entity->getAdded(),
                'level' => $entity->getLevel(),
            ];
        }

        // skill ratings
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('skr');
        $qb->from('Netrunners\Entity\SkillRating', 'skr');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var SkillRating $entity */
            $this->skillratings[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'skillId' => ($entity->getSkill()) ? $entity->getSkill()->getId() : NULL,
                'rating' => $entity->getRating(),
                'npcId' => ($entity->getNpc()) ? $entity->getNpc()->getId() : NULL,
            ];
        }

        // systems
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('sys');
        $qb->from('Netrunners\Entity\System', 'sys');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var System $entity */
            $this->systems[$entity->getId()] = [
                'id' => $entity->getId(),
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'name' => $entity->getName(),
                'addy' => $entity->getAddy(),
                'alertLevel' => $entity->getAlertLevel(),
                'factionId' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'groupId' => ($entity->getGroup()) ? $entity->getGroup()->getId() : NULL,
                'maxSize' => $entity->getMaxSize(),
                'noclaim' => $entity->getNoclaim(),
                'integrity' => $entity->getIntegrity(),
                'geocoords' => $entity->getGeocoords(),
            ];
        }

        // system logs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('sl');
        $qb->from('Netrunners\Entity\SystemLog', 'sl');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var SystemLog $entity */
            $this->systemlogs[$entity->getId()] = [
                'id' => $entity->getId(),
                'systemId' => ($entity->getSystem()) ? $entity->getSystem()->getId() : NULL,
                'profileId' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'nodeId' => ($entity->getNode()) ? $entity->getNode()->getId() : NULL,
                'fileId' => ($entity->getFile()) ? $entity->getFile()->getId() : NULL,
                'subject' => $entity->getSubject(),
                'severity' => $entity->getSeverity(),
                'added' => $entity->getAdded(),
                'details' => $entity->getDetails(),
            ];
        }

        // words
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('w');
        $qb->from('Netrunners\Entity\Word', 'w');
        $entities = $qb->getQuery()->getResult();
        foreach ($entities as $entity) {
            /** @var Word $entity */
            $this->words[$entity->getId()] = [
                'id' => $entity->getId(),
                'content' => $entity->getContent(),
                'length' => $entity->getLength(),
            ];
        }

        // TODO keep working on this

        /* LOOPS */

        $this->loop->addPeriodicTimer(self::LOOP_TIME_JOBS, function(){
            //$this->logger->log(Logger::INFO, 'looping jobs');
            $this->loopService->loopJobs();
        });

        $this->loop->addPeriodicTimer(self::LOOP_TIME_COMBAT, function(){
            //$this->logger->log(Logger::INFO, 'looping combat');
            $this->loopService->loopCombatRound();
        });

        $this->loop->addPeriodicTimer(self::LOOP_TIME_RESOURCES, function(){
            $this->log(Logger::INFO, 'looping resources');
            $this->loopService->loopResources();
        });

        $this->loop->addPeriodicTimer(self::LOOP_NPC_SPAWN, function(){
            $this->log(Logger::INFO, 'looping npcspawn');
            $this->loopService->loopNpcSpawn();
        });

        $this->loop->addPeriodicTimer(self::LOOP_NPC_ROAM, function(){
            $this->log(Logger::INFO, 'looping npcroam');
            $this->loopService->loopNpcRoam();
        });

        $this->loop->addPeriodicTimer(self::LOOP_REGENERATION, function(){
            $this->loopService->loopRegeneration();
        });

        /* INIT SINGLETON */

        self::$instance = $this;

    }

    /**
     * @param $priority
     * @param $message
     */
    public function log($priority, $message)
    {
        $this->logger->log($priority, $message);
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
        if ($resourceId && $key && $value) {
            $this->clientsData[$resourceId][$key] = $value;
        }
        return $this;
    }

    /**
     * @param $resourceId
     * @param \DateTime $cooldown
     * @return $this
     */
    public function setClientCombatFileCooldown($resourceId, $cooldown)
    {
        $this->clientsData[$resourceId]['combatFileCooldown'] = $cooldown;
        return $this;
    }

    /**
     * @param $resourceId
     * @param $replyId
     * @return $this
     */
    public function setClientDataReplyId($resourceId, $replyId)
    {
        $this->clientsData[$resourceId]['replyId'] = $replyId;
        return $this;
    }

    /**
     * @param $resourceId
     * @return mixed
     */
    public function getClientDataReplyId($resourceId)
    {
        return $this->clientsData[$resourceId]['replyId'];
    }

    /**
     * @param $resourceId
     * @param int $count
     * @return $this
     */
    public function setClientDataSpamcount($resourceId, $count = 0)
    {
        $this->clientsData[$resourceId]['spamcount'] = $count;
        return $this;
    }

    /**
     * @param $resourceId
     * @return $this
     */
    public function clearClientActionData($resourceId)
    {
        $this->clientsData[$resourceId]['action'] = [];
        return $this;
    }

    /**
     * @param $resourceId
     * @param $actionData
     * @return $this
     */
    public function setClientActionData($resourceId, $actionData)
    {
        $this->clientsData[$resourceId]['action'] = $actionData;
        return $this;
    }

    /**
     * @param $resourceId
     * @return $this
     */
    public function clearClientHangmanData($resourceId)
    {
        $this->clientsData[$resourceId]['hangman'] = [];
        return $this;
    }

    /**
     * @param $resourceId
     * @param $actionData
     * @return $this
     */
    public function setClientHangmanData($resourceId, $actionData)
    {
        $this->clientsData[$resourceId]['hangman'] = $actionData;
        return $this;
    }

    /**
     * @param $resourceId
     * @return $this
     */
    public function clearClientCodebreakerData($resourceId)
    {
        $this->clientsData[$resourceId]['codebreaker'] = [];
        return $this;
    }

    /**
     * @param $resourceId
     * @param $actionData
     * @return $this
     */
    public function setClientCodebreakerData($resourceId, $actionData)
    {
        $this->clientsData[$resourceId]['codebreaker'] = $actionData;
        return $this;
    }

    /**
     * @param $resourceId
     * @return $this
     */
    public function clearClientMilkrunData($resourceId)
    {
        $this->clientsData[$resourceId]['milkrun'] = [];
        return $this;
    }

    /**
     * @param $resourceId
     * @param $actionData
     * @return $this
     */
    public function setClientMilkrunData($resourceId, $actionData)
    {
        $this->clientsData[$resourceId]['milkrun'] = $actionData;
        return $this;
    }

    /**
     * @return array
     */
    public function getJobs()
    {
        return $this->loopService->getJobs();
    }

    /**
     * @param array $jobData
     */
    public function addJob($jobData = [])
    {
        $this->loopService->addJob($jobData);
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
     * @param $attacker
     * @param $defender
     * @param null $attackerResourceId
     * @param null $defenderResourceId
     */
    public function addCombatant($attacker, $defender, $attackerResourceId = NULL, $defenderResourceId = NULL)
    {
        if ($attacker instanceof Profile) {
            if ($defender instanceof Profile) {
                $this->combatants['profiles'][$attacker->getId()] = [
                    'profileTarget' => $defender->getId(),
                    'npcTarget' => NULL,
                    'attackerResourceId' => $attackerResourceId,
                    'defenderResourceId' => $defenderResourceId
                ];
            }
            else if ($defender instanceof NpcInstance) {
                $this->combatants['profiles'][$attacker->getId()] = [
                    'profileTarget' => NULL,
                    'npcTarget' => $defender->getId(),
                    'attackerResourceId' => $attackerResourceId,
                    'defenderResourceId' => $defenderResourceId
                ];
            }
        }
        if ($attacker instanceof NpcInstance) {
            if ($defender instanceof Profile) {
                $this->combatants['npcs'][$attacker->getId()] = [
                    'profileTarget' => $defender->getId(),
                    'npcTarget' => NULL,
                    'attackerResourceId' => $attackerResourceId,
                    'defenderResourceId' => $defenderResourceId
                ];
            }
            else if ($defender instanceof NpcInstance) {
                $this->combatants['npcs'][$attacker->getId()] = [
                    'profileTarget' => NULL,
                    'npcTarget' => $defender->getId(),
                    'attackerResourceId' => $attackerResourceId,
                    'defenderResourceId' => $defenderResourceId
                ];
            }
        }
    }

    /**
     * Removes a combatant from the game.
     * If endCombat is true, it also removes all of the combatants that had this combatant as their target.
     * @param $combatant
     * @param bool $endCombat
     */
    public function removeCombatant($combatant, $endCombat = true)
    {
        if ($combatant instanceof NpcInstance) {
            unset($this->combatants['npcs'][$combatant->getId()]);
        }
        if ($combatant instanceof Profile) {
            unset($this->combatants['profiles'][$combatant->getId()]);
        }
        if ($endCombat) {
            // remove all combatants that also had this combatant as their target
            foreach ($this->combatants['npcs'] as $combatantId => $combatantData) {
                if ($combatant instanceof NpcInstance) {
                    if ($combatantData['npcTarget'] == $combatant->getId()) {
                        unset($this->combatants['npcs'][$combatantId]);
                    }
                }
                if ($combatant instanceof Profile) {
                    if ($combatantData['profileTarget'] == $combatant->getId()) {
                        unset($this->combatants['npcs'][$combatantId]);
                    }
                }
            }
            foreach ($this->combatants['profiles'] as $combatantId => $combatantData) {
                if ($combatant instanceof NpcInstance) {
                    if ($combatantData['npcTarget'] == $combatant->getId()) {
                        unset($this->combatants['profiles'][$combatantId]);
                    }
                }
                if ($combatant instanceof Profile) {
                    if ($combatantData['profileTarget'] == $combatant->getId()) {
                        unset($this->combatants['profiles'][$combatantId]);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getCombatants()
    {
        return $this->combatants;
    }

    /**
     * @param $profileId
     * @param bool $asObject
     * @return null|object|array
     */
    public function getProfileCombatData($profileId, $asObject = true)
    {
        $result = (array_key_exists($profileId, $this->combatants['profiles'])) ? $this->combatants['profiles'][$profileId] : NULL;
        if ($result && $asObject) $result = (object)$result;
        return $result;
    }

    /**
     * @param $npcId
     * @param bool $asObject
     * @return null|object|array
     */
    public function getNpcCombatData($npcId, $asObject = true)
    {
        $result = (array_key_exists($npcId, $this->combatants['npcs'])) ? $this->combatants['npcs'][$npcId] : NULL;
        if ($result && $asObject) $result = (object)$result;
        return $result;
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return $this
     */
    public function setConfirm($resourceId, $command, $contentArray = [])
    {
        $this->clientsData[$resourceId]['confirm'] = [
            'command' => $command,
            'contentArray' => $contentArray
        ];
        return $this;
    }

    /**
     * @param ConnectionInterface $conn
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
            'geocoords' => false,
            'awaitingcoords' => false,
            'codingOptions' => [
                'fileType' => 0,
                'fileLevel' => 0,
                'mode' => 'resource'
            ],
            'action' => [],
            'milkrun' => [],
            'hangman' => [],
            'codebreaker' => [],
            'combatFileCooldown' => new \DateTime(),
            'confirm' => [
                'command' => '',
                'contentArray' => []
            ],
            'captchasolution' => NULL,
            'invitationid' => NULL,
            'replyId' => NULL
        );
        $response = new GameClientResponse($resourceId);
        $response->setCommand(GameClientResponse::COMMAND_GETIPADDY);
        $response->send();
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
        try {
            // get resource id of socket
            /** @noinspection PhpUndefinedFieldInspection */
            $resourceId = $from->resourceId;
            // decode received data and if the data is not valid, disconnect the client
            $msgData = json_decode($msg);
            // check if we have everything that we need in the $msgData
            if (!is_object($msgData) || !isset($msgData->command) || !isset($msgData->hash) || !isset($msgData->content)) {
                $this->log(Logger::ALERT, $resourceId . ': SOCKET IS SENDING GIBBERISH - GET RID OF THEM - ' . $msg);
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
                if ($command != 'ticker' && $command != 'setgeocoords' && $command != 'processlocations') {
                    $querytime = $this->microtime_diff($this->clientsData[$resourceId]['millis']);
                    if ($querytime <= 0.2) {
                        $this->clientsData[$resourceId]['spamcount']++;
                        if ($this->clientsData[$resourceId]['spamcount'] >= mt_rand(5, 10)) {
                            $this->log(Logger::ALERT, $resourceId . ': SOCKET IS SPAMMING - DISCONNECT SOCKET - ' . $msg);
                            $response = new GameClientResponse($resourceId);
                            $response->addMessage('DISCONNECTED - REASON: SPAMMING', GameClientResponse::CLASS_DANGER);
                            $response->send();
                            $from->close();
                            return true;
                        }
                    }
                    else {
                        $this->clientsData[$resourceId]['millis'] = microtime();
                        $this->setClientDataSpamcount($resourceId);
                    }
                }
            }
            // init vars
            $hash = $msgData->hash;
            $content = $msgData->content;
            if ($command != 'parseFrontendInput' && $command != 'setgeocoords' && $command != 'processlocations') {
                $content = trim($content);
                $content = htmLawed($content, ['safe'=>1,'elements'=>'strong']);
            }
            $silent = (isset($msgData->silent)) ? $msgData->silent : false;
            $entityId = (isset($msgData->entityId)) ? (int)$msgData->entityId : false;
            if (!$content || $content == '') {
                if ($command != 'parseMailInput' && $command != 'processlocations') {
                    return true;
                }
            }
            if (!$silent) {
                $response = new GameClientResponse($resourceId);
                $response->setCommand(GameClientResponse::COMMAND_ECHOCOMMAND);
                $response->addOption(GameClientResponse::OPT_CONTENT, $content);
                $response->send();
            }
            // log this command unless it is automated or contains sensitive informations
            if (
                $content != 'ticker' &&
                $command != 'promptforpassword' &&
                $command != 'processlocations' &&
                $command != 'createpassword' &&
                $command != 'createpasswordconfirm'
            ) {
                $this->log(Logger::INFO, $resourceId . ': ' . $msg);
            }
            // check if we know the ip addy of the socket - if not, disconnect them
            if ($command != 'setipaddy') {
                if (!$this->clientsData[$resourceId]['ipaddy']) {
                    $this->log(Logger::ALERT, $resourceId . ': SOCKET WITH NO IP ADDY INFO IS SENDING COMMANDS - DISCONNECT SOCKET');
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
                            $response = new GameClientResponse($resourceId);
                            $message = 'This IP address has been banned!';
                            $response->addMessage($message, GameClientResponse::CLASS_DANGER);
                            $response->send();
                            $from->close();
                            return true;
                        }
                        // not banned, set ip addy
                        $this->clientsData[$resourceId]['ipaddy'] = $content;
                    } else {
                        $this->log(Logger::ALERT, $resourceId . ': SOMETHING FISHY GOING ON - NO IP ADDRESS COULD BE FOUND - DISCONNECT SOCKET');
                        $from->close();
                        return true;
                    }
                    break;
                case 'setgeocoords':
                    $content = implode(',', $content);
                    $content = trim($content);
                    $geocoords = htmLawed($content, ['safe'=>1,'elements'=>'strong']);
                    $this->clientsData[$resourceId]['geocoords'] = $geocoords;
                    break;
                case 'processlocations':
                    if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                    $coordRepo = $this->entityManager->getRepository('Netrunners\Entity\Geocoord');
                    /** @var GeocoordRepository $coordRepo */
                    $needFlush = false;
                    $possibleLocations = [];
                    $awaitingcoords = $this->clientsData[$resourceId]['awaitingcoords'];
                    foreach ($content as $locationData) {
                        $lat = $locationData->geometry->location->lat;
                        $lng = $locationData->geometry->location->lng;
                        $placeId = $locationData->place_id;
                        $existingGeocoord = $coordRepo->findOneUnique($lat, $lng, $placeId);
                        if (!$existingGeocoord) {
                            $geocoord = new Geocoord();
                            $geocoord->setAdded(new \DateTime());
                            $geocoord->setLat($lat);
                            $geocoord->setLng($lng);
                            $geocoord->setPlaceId($placeId);
                            $geocoord->setData(json_encode($locationData));
                            $geocoord->setZone('global');
                            $this->entityManager->persist($geocoord);
                            if (!$needFlush) $needFlush = true;
                            if ($awaitingcoords) $possibleLocations[] = $geocoord;
                        }
                        else {
                            if ($awaitingcoords) $possibleLocations[] = $existingGeocoord;
                        }
                    }
                    if ($awaitingcoords) {
                        $this->clientsData[$resourceId]['awaitingcoords'] = false;
                        if (!empty($possibleLocations)) {
                            $count = count($possibleLocations);
                            $randLocNumber = mt_rand(0, $count-1);
                            $location = $possibleLocations[$randLocNumber];
                            $flytoResponse = new GameClientResponse($resourceId);
                            $flytoResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
                            $flytoResponse->addOption(GameClientResponse::OPT_CONTENT, [$location->getLat(),$location->getLng()]);
                            $flytoResponse->send();
                            return $this->utilityService->updateSystemCoords($resourceId, $location, true);
                        }
                        else {
                            $message = 'Unable to process coordinates at this time - please try again later';
                            $response = new GameClientResponse($resourceId);
                            $response->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND)->addMessage($message);
                            $response->send();
                        }
                    }
                    return true;
                case 'login':
                    list($response, $disconnect) = $this->loginService->login($resourceId, $content);
                    /** @var GameClientResponse $response */
                    $response->send();
                    if ($disconnect) $from->close();
                    return true;
                case 'confirmusercreate':
                    list($disconnect, $response) = $this->loginService->confirmUserCreate($resourceId, $content);
                    if ($response instanceof GameClientResponse) $response->send();
                    if ($disconnect) {
                        $from->close();
                    }
                    return true;
                case 'solvecaptcha':
                    list($disconnect, $response) = $this->loginService->solveCaptcha($resourceId, $content);
                    if ($response instanceof GameClientResponse) $response->send();
                    if ($disconnect) {
                        $from->close();
                    }
                    return true;
                case 'enterinvitationcode':
                    list($disconnect, $response) = $this->loginService->enterInvitationCode($resourceId, $content);
                    if ($response instanceof GameClientResponse) $response->send();
                    if ($disconnect) {
                        $from->close();
                    }
                    return true;
                case 'createpassword':
                    list($disconnect, $response) = $this->loginService->createPassword($resourceId, $content);
                    if ($response instanceof GameClientResponse) $response->send();
                    if ($disconnect) {
                        $from->close();
                    }
                    return true;
                case 'createpasswordconfirm':
                    list($disconnect, $response) = $this->loginService->createPasswordConfirm($resourceId, $content);
                    /** @var GameClientResponse $response */
                    $response->send();
                    if ($disconnect) {
                        $from->close();
                    }
                    return true;
                case 'promptforpassword':
                    list($disconnect, $response) = $this->loginService->promptForPassword($resourceId, $content);
                    /** @var GameClientResponse $response */
                    $response->send();
                    if ($disconnect) {
                        $from->close();
                    }
                    else {
                        return $this->utilityService->showMotd($resourceId);
                    }
                    return true;
                case 'saveFeedback':
                    if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                    $fTitle = (isset($msgData->title)) ? $msgData->title : false;
                    $fType = (isset($msgData->type)) ? $msgData->type : false;
                    return $this->saveFeedback($resourceId, $content, $fTitle, $fType);
                case 'parseFrontendInput':
                    if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                    return $this->parserService->parseFrontendInput($from, $msgData);
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
                    return $this->parserService->parseCodeInput($from, $content, $this->loopService->getJobs());
                case 'parseConfirmInput':
                    if ($hash != $this->clientsData[$resourceId]['hash']) return true;
                    return $this->parserService->parseConfirmInput($from, $content);
            }
        }
        catch (\Exception $e) {
            $this->log(Logger::ALERT, $resourceId . ' : CAUGHT EXCEPTION : ' . $e->getMessage() . ' [' . $e->getCode() . ']');
            $this->log(Logger::INFO, $e->getTraceAsString());
            $from->close();
        }
        return true;
    }

    /**
     * @param ConnectionInterface $conn
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function onClose(ConnectionInterface $conn)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $conn->resourceId;
        // end play-session
        $profile = (array_key_exists($resourceId, $this->clientsData)) ? $this->entityManager->find('Netrunners\Entity\Profile', $this->clientsData[$resourceId]['profileId']) : NULL;
        if ($profile) {
            /** @var Profile $profile */
            $playSessionRepo = $this->entityManager->getRepository('Netrunners\Entity\PlaySession');
            /** @var PlaySessionRepository $playSessionRepo */
            $currentPlaySession = $playSessionRepo->findCurrentPlaySession($profile);
            if ($currentPlaySession) {
                $currentPlaySession->setEnd(new \DateTime());
            }
            // set current resource-id to null
            $profile->setCurrentResourceId(NULL);
            $this->entityManager->flush();
            // inform admins
            $informerText = sprintf(
                'user [%s] has disconnected',
                $profile->getUser()->getUsername()
            );
            foreach ($this->getClients() as $wsClientId => $wsClient) {
                if ($wsClient->resourceId == $resourceId) continue;
                $xClientData = $this->getClientData($wsClient->resourceId);
                if (!$xClientData) continue;
                if (!$xClientData->userId) continue;
                $xUser = $this->entityManager->find('TmoAuth\Entity\User', $xClientData->userId);
                if (!$xUser) continue;
                if (!$this->getUtilityService()->hasRole($xUser, Role::ROLE_ID_ADMIN)) continue;
                $informer = new GameClientResponse($wsClient->resourceId);
                $informer->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
                $informer->addMessage($informerText, GameClientResponse::CLASS_ADDON);
                $informer->send();
            }
        }
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

    /**
     * @param $resourceId
     * @param string $content
     * @param string $fTitle
     * @param $type
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveFeedback(
        $resourceId,
        $content = '===invalid content===',
        $fTitle = '===invalid title===',
        $type
    )
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $this->clientsData[$resourceId]['userId']);
        if (!$user) return false;
        if (!array_key_exists($type, Feedback::$lookup)) return false;
        $content = htmLawed($content, ['safe'=>1,'elements'=>'strong,i,ul,ol,li,p,a,br']);
        $fTitle = htmLawed($fTitle, ['safe'=>1,'elements'=>'strong']);
        $feedback = new Feedback();
        $feedback->setSubject($fTitle);
        $feedback->setDescription($content);
        $feedback->setProfile($user->getProfile());
        $feedback->setAdded(new \DateTime());
        $feedback->setType($type);
        $feedback->setStatus(Feedback::STATUS_SUBMITTED_ID);
        $internalData = [
            'currentNode' => $user->getProfile()->getCurrentNode()->getId()
        ];
        $feedback->setInternalData(json_encode($internalData));
        $this->entityManager->persist($feedback);
        $this->entityManager->flush($feedback);
        $response = new GameClientResponse($resourceId);
        $response->addMessage('Feedback saved', GameClientResponse::CLASS_SUCCESS);
        return $response->send();
    }

}
