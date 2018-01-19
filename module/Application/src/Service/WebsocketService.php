<?php

namespace Application\Service;

use Doctrine\ORM\EntityManager;
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
use Netrunners\Entity\Geocoord;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
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
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
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
     * WebsocketService constructor.
     * @param EntityManager $entityManager
     * @param UtilityService $utilityService
     * @param ParserService $parserService
     * @param LoopService $loopService
     * @param LoginService $loginService
     * @param LoopInterface $loop
     * @param $hash
     * @param $adminMode
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
                'owner' => ($entity->getOwner()) ? $entity->getOwner()->getId() : NULL,
                'faction' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'group' => ($entity->getGroup()) ? $entity->getGroup()->getId() : NULL,
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
                'sourceNode' => ($entity->getSourceNode()) ? $entity->getSourceNode()->getId() : NULL,
                'targetNode' => ($entity->getTargetNode()) ? $entity->getTargetNode()->getId() : NULL,
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
            $this->factionroles[$entity->getId()] = [
                'id' => $entity->getId(),
                'added' => $entity->getAdded(),
                'faction' => ($entity->getFaction()) ? $entity->getFaction()->getId() : NULL,
                'factionRole' => ($entity->getFactionRole()) ? $entity->getFactionRole()->getId() : NULL,
                'member' => ($entity->getMember()) ? $entity->getMember()->getId() : NULL,
                'changer' => ($entity->getChanger()) ? $entity->getChanger()->getId() : NULL,
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
                'profile' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
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
                'fileType' => ($entity->getFileType()) ? $entity->getFileType()->getId() : NULL,
                'coder' => ($entity->getCoder()) ? $entity->getCoder()->getId() : NULL,
                'profile' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
                'system' => ($entity->getSystem()) ? $entity->getSystem()->getId() : NULL,
                'node' => ($entity->getNode()) ? $entity->getNode()->getId() : NULL,
                'mailMessage' => ($entity->getMailMessage()) ? $entity->getMailMessage()->getId() : NULL,
                'npc' => ($entity->getNpc()) ? $entity->getNpc()->getId() : NULL,
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
                'file' => ($entity->getFile()) ? $entity->getFile()->getId() : NULL,
                'fileMod' => ($entity->getFileMod()) ? $entity->getFileMod()->getId() : NULL,
                'coder' => ($entity->getCoder()) ? $entity->getCoder()->getId() : NULL,
                'profile' => ($entity->getProfile()) ? $entity->getProfile()->getId() : NULL,
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
