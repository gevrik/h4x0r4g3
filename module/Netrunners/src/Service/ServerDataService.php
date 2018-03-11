<?php

/**
 * Server Data Service.
 * This service handles the server data, which is a representation of the database.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

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

class ServerDataService
{
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
     * @var EntityManager
     */
    protected $entityManager;


    /**
     * ServerDataService constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(
        EntityManager $entityManager
    )
    {
        $this->entityManager = $entityManager;
    }

    /**
     *
     */
    public function loadDataFromDb()
    {
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

    }

    /** RETRIEVAL METHODS */

    /**
     * @param string $name
     * @param int $id
     * @return null|array
     */
    public function findById(string $name, int $id)
    {
        if (method_exists(self::class, $this->getMethodName($name))) {
            if (array_key_exists($id, $this->$name)) {
                return $this->$name[$id];
            }
        }
        return null;
    }

    /**
     * @param string $name
     * @param string $property
     * @param $value
     * @return array
     * @throws \Exception
     */
    public function findByPropertyValue(string $name, string $property, $value)
    {
        $results = [];
        $methodName = $this->getMethodName($name);
        if (!method_exists(self::class, $methodName)) {
            throw new \Exception(sprintf("%s does not exist", $name));
        }
        foreach ($this->$methodName as $record) {
            if (!array_key_exists($property, $record)) {
                throw new \Exception(sprintf("%s property does not exist in %s", $property, $name));
            }
            if ($record[$property] == $value) {
                $results[] = $record;
            }
        }
        return $results;
    }

    /**
     * @param string $methodName
     * @param array $parameters
     * @return array|null
     * @throws \Exception
     */
    public function executeDataServiceMethod(string $methodName, array $parameters = [])
    {
        if (!method_exists(self::class, $methodName)) {
            throw new \Exception(sprintf("Method %s does not exist in data service"));
        }
        switch ($methodName) {
            default:
                return NULL;
            case 'findConnectionBySourceNodeAndTargetNode':
                return $this->findConnectionBySourceNodeAndTargetNode($parameters[0], $parameters[1]);
            case 'findKnownNodesByProfileAndSystem':
                return $this->findKnownNodesByProfileAndSystem($parameters[0], $parameters[1]);
                break;
        }
    }

    /**
     * @param int $targetNodeId
     * @param int $sourceNodeId
     * @return array|null
     */
    protected function findConnectionBySourceNodeAndTargetNode(int $sourceNodeId, int $targetNodeId)
    {
        foreach ($this->connections as $connectionId => $connection) {
            if ($connection['targetNodeId'] == $targetNodeId && $connection['sourceNodeId'] == $sourceNodeId) {
                return $connection;
            }
        }
        return NULL;
    }

    /**
     * @param int $profileId
     * @param int $systemId
     * @return array
     */
    protected function findKnownNodesByProfileAndSystem(int $profileId, int $systemId)
    {
        $results = [];
        foreach ($this->knownnodes as $id => $knownnode) {
            if ($knownnode['profileId'] == $profileId && $knownnode['systemId'] == $systemId) {
                $results[] = $knownnode;
            }
        }
        return $results;
    }

    /**
     * @param string $name
     * @param string $property
     * @param $value
     * @return int
     * @throws \Exception
     */
    public function countByPropertyValue(string $name, string $property, $value)
    {
        $counter = 0;
        $methodName = $this->getMethodName($name);
        if (!method_exists(self::class, $methodName)) {
            throw new \Exception(sprintf("%s does not exist", $name));
        }
        foreach ($this->$methodName as $record) {
            if (!array_key_exists($property, $record)) {
                throw new \Exception(sprintf("property %s does not exist in %s", $property, $name));
            }
            if ($record[$property] == $value) {
                $counter++;
            }
        }
        return $counter;
    }

    /**
     * @param string $name
     * @param string $property
     * @param int $id
     * @return mixed
     * @throws \Exception
     */
    public function getPropertyValueById(string $name, string $property, int $id)
    {
        if (!method_exists(self::class, $this->getMethodName($name))) {
            throw new \Exception(sprintf("%s does not exist", $name));
        }
        if (array_key_exists($property, $this->$name[$id])) {
            throw new \Exception(sprintf("property %s does not exist in %s", $property, $name));
        }
        return $this->$name[$id][$property];
    }

    /**
     * @param $lat
     * @param $lng
     * @param $placeId
     * @return mixed|null
     */
    public function findByLatLngPlace($lat, $lng, $placeId)
    {
        foreach ($this->geocoords as $geocoord) {
            if ($geocoord['lat'] == $lat && $geocoord['lng'] == $lng && $geocoord['placeId'] == $placeId) {
                return $geocoord;
                break;
            }
        }
        return NULL;
    }

    /**
     * @param string $name
     * @param array $data
     * @return int
     * @throws \Exception
     */
    public function addRecord(string $name, array $data)
    {
        if (!method_exists(self::class, $this->getMethodName($name))) {
            throw new \Exception(sprintf("%s does not exist", $name));
        }
        $id = max(array_keys($this->$name)) + 1;
        $data['id'] = $id;
        $this->$name[] = $data;
        return $id;
    }

    /**
     * @param string $name
     * @param int $id
     * @param string $property
     * @param $value
     * @return array
     * @throws \Exception
     */
    public function updatePropertyValueById(string $name, int $id, string $property, $value)
    {
        if (!method_exists(self::class, $this->getMethodName($name))) {
            throw new \Exception(sprintf("%s does not exist", $name));
        }
        if (array_key_exists($id, $this->$name)) {
            throw new \Exception(sprintf("record with id %s does not exist in %s", $id, $name));
        }
        if (array_key_exists($property, $this->$name[$id])) {
            throw new \Exception(sprintf("property %s does not exist in %s", $property, $name));
        }
        $this->$name[$id][$property] = $value;
        return $this->$name[$id];
    }

    /**
     * @param string $name
     * @return string
     */
    private function getMethodName(string $name)
    {
        return 'get' . ucfirst($name);
    }

    /** GETTERS */

    /**
     * @return array
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return array
     */
    public function getAivatars(): array
    {
        return $this->aivatars;
    }

    /**
     * @return array
     */
    public function getAuctions(): array
    {
        return $this->auctions;
    }

    /**
     * @return array
     */
    public function getAuctionbids(): array
    {
        return $this->auctionbids;
    }

    /**
     * @return array
     */
    public function getBannedips(): array
    {
        return $this->bannedips;
    }

    /**
     * @return array
     */
    public function getChatchannels(): array
    {
        return $this->chatchannels;
    }

    /**
     * @return array
     */
    public function getCompanynames(): array
    {
        return $this->companynames;
    }

    /**
     * @return array
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * @return array
     */
    public function getEffects(): array
    {
        return $this->effects;
    }

    /**
     * @return array
     */
    public function getFactions(): array
    {
        return $this->factions;
    }

    /**
     * @return array
     */
    public function getFactionroles(): array
    {
        return $this->factionroles;
    }

    /**
     * @return array
     */
    public function getFeedbacks(): array
    {
        return $this->feedbacks;
    }

    /**
     * @return array
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return array
     */
    public function getFilecategories(): array
    {
        return $this->filecategories;
    }

    /**
     * @return array
     */
    public function getFilemods(): array
    {
        return $this->filemods;
    }

    /**
     * @return array
     */
    public function getFilemodinstances(): array
    {
        return $this->filemodinstances;
    }

    /**
     * @return array
     */
    public function getFactionroleinstances(): array
    {
        return $this->factionroleinstances;
    }

    /**
     * @return array
     */
    public function getFileparts(): array
    {
        return $this->fileparts;
    }

    /**
     * @return array
     */
    public function getFilepartinstances(): array
    {
        return $this->filepartinstances;
    }

    /**
     * @return array
     */
    public function getFilepartskills(): array
    {
        return $this->filepartskills;
    }

    /**
     * @return array
     */
    public function getFiletypes(): array
    {
        return $this->filetypes;
    }

    /**
     * @return array
     */
    public function getFiletypemods(): array
    {
        return $this->filetypemods;
    }

    /**
     * @return array
     */
    public function getFiletypeskills(): array
    {
        return $this->filetypeskills;
    }

    /**
     * @return array
     */
    public function getGameoptions(): array
    {
        return $this->gameoptions;
    }

    /**
     * @return array
     */
    public function getGameoptioninstances(): array
    {
        return $this->gameoptioninstances;
    }

    /**
     * @return array
     */
    public function getGeocoords(): array
    {
        return $this->geocoords;
    }

    /**
     * @return array
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return array
     */
    public function getGrouproles(): array
    {
        return $this->grouproles;
    }

    /**
     * @return array
     */
    public function getGrouproleinstances(): array
    {
        return $this->grouproleinstances;
    }

    /**
     * @return array
     */
    public function getInvitations(): array
    {
        return $this->invitations;
    }

    /**
     * @return array
     */
    public function getKnownnodes(): array
    {
        return $this->knownnodes;
    }

    /**
     * @return array
     */
    public function getMailmessages(): array
    {
        return $this->mailmessages;
    }

    /**
     * @return array
     */
    public function getManpages(): array
    {
        return $this->manpages;
    }

    /**
     * @return array
     */
    public function getMilkruns(): array
    {
        return $this->milkruns;
    }

    /**
     * @return array
     */
    public function getMilkrunaivatars(): array
    {
        return $this->milkrunaivatars;
    }

    /**
     * @return array
     */
    public function getMilkrunaivatarinstances(): array
    {
        return $this->milkrunaivatarinstances;
    }

    /**
     * @return array
     */
    public function getMilkrunice(): array
    {
        return $this->milkrunice;
    }

    /**
     * @return array
     */
    public function getMilkruninstances(): array
    {
        return $this->milkruninstances;
    }

    /**
     * @return array
     */
    public function getMissions(): array
    {
        return $this->missions;
    }

    /**
     * @return array
     */
    public function getMissionarchetypes(): array
    {
        return $this->missionarchetypes;
    }

    /**
     * @return array
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array
     */
    public function getNodetypes(): array
    {
        return $this->nodetypes;
    }

    /**
     * @return array
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    /**
     * @return array
     */
    public function getNpcs(): array
    {
        return $this->npcs;
    }

    /**
     * @return array
     */
    public function getNpcinstances(): array
    {
        return $this->npcinstances;
    }

    /**
     * @return array
     */
    public function getPlaysessions(): array
    {
        return $this->playsessions;
    }

    /**
     * @return array
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * @return array
     */
    public function getProfileeffects(): array
    {
        return $this->profileeffects;
    }

    /**
     * @return array
     */
    public function getProfilefactionratings(): array
    {
        return $this->profilefactionratings;
    }

    /**
     * @return array
     */
    public function getProfileratings(): array
    {
        return $this->profileratings;
    }

    /**
     * @return array
     */
    public function getServersettings(): array
    {
        return $this->serversettings;
    }

    /**
     * @return array
     */
    public function getSkills(): array
    {
        return $this->skills;
    }

    /**
     * @return array
     */
    public function getSkillratings(): array
    {
        return $this->skillratings;
    }

    /**
     * @return array
     */
    public function getSystems(): array
    {
        return $this->systems;
    }

    /**
     * @return array
     */
    public function getSystemlogs(): array
    {
        return $this->systemlogs;
    }

    /**
     * @return array
     */
    public function getWords(): array
    {
        return $this->words;
    }

}
