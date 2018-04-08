<?php

/**
 * Node Service.
 * The service supplies methods that resolve logic around Node objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\Group;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\AuctionRepository;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SystemRepository;
use TmoAuth\Entity\Role;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class NodeService extends BaseService
{

    const NAME_STRING = "name";
    const TYPE_STRING = "type";
    const LEVEL_STRING = "level";
    const CONNECTIONS_STRING = "connections";
    const FILES_STRING = "files";
    const USERS_STRING = "users";
    const NPCS_STRING = "entities";

    const RAW_NODE_COST = 50;
    const MAX_NODES_MULTIPLIER = 5;
    const MAX_NODE_LEVEL = 8;

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;

    /**
     * @var NodeRepository
     */
    protected $nodeRepo;

    /**
     * @var SystemRepository
     */
    protected $systemRepo;

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;

    /**
     * @var array
     */
    public $connectionsChecked = [];

    /**
     * NodeService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        $this->nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $this->systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
    }

    /**
     *
     */
    public function initConnectionsChecked()
    {
        $this->connectionsChecked = [];
        return $this;
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function enterMode($resourceId, $command, $contentArray = [])
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $this->getWebsocketServer()->setConfirm($resourceId, $command, $contentArray);
        switch ($command) {
            default:
                break;
            case 'addnode':
                $checkResult = $this->addnodeChecks();
                if ($checkResult) {
                    return $this->gameClientResponse->addMessage($checkResult)->send();
                }
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                $message = $this->translate('Are you sure that you want to add a node - please confirm this action:');
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
            case 'upgradenode':
                $checkResult = $this->upgradeNodeChecks();
                if (!$checkResult instanceof Node) {
                    return $this->gameClientResponse->addMessage($checkResult)->send();
                }
                $nodeType = $checkResult->getNodeType();
                $upgradeCost = $nodeType->getCost() * pow($checkResult->getLevel(), $checkResult->getLevel() + 1);
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                $message = sprintf(
                    $this->translate('You need %s credits to upgrade this node - please confirm this action:'),
                    $upgradeCost
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
            case 'nodetype':
                var_dump('here');
                $checkResult = $this->changeNodeTypeChecks($contentArray);
                if (!$checkResult instanceof NodeType) {
                    if ($checkResult instanceof GameClientResponse) {
                        return $checkResult->send();
                    }
                    return $this->gameClientResponse->addMessage($checkResult)->send();
                }
                var_dump('here1');
                $currentNode = $profile->getCurrentNode();
                if ($currentNode->getLevel() > 1) {
                    var_dump('here2');
                    $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                    $message = sprintf(
                        $this->translate('You need [%s] credits to change the node type - <span class="text-danger">the current node [%s] will be reset to level 1</span>'),
                        $checkResult->getCost(),
                        $currentNode->getNodeType()->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                }
                else {
                    var_dump('here3');
                    $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                    $message = sprintf(
                        $this->translate('You need [%s] credits to change the node type - the current node type is [%s]'),
                        $checkResult->getCost(),
                        $currentNode->getNodeType()->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                }
                break;
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function upgradeNode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked);
        }
        $checkResult = $this->upgradeNodeChecks();
        if (!$checkResult instanceof Node) {
            return $this->gameClientResponse->addMessage($checkResult);
        }
        $nodeType = $checkResult->getNodeType();
        $upgradeCost = $nodeType->getCost() * pow($checkResult->getLevel(), $checkResult->getLevel() + 1);
        $profile->setCredits($profile->getCredits() - $upgradeCost);
        $newLevel = $checkResult->getLevel() + 1;
        $checkResult->setLevel($newLevel);
        // upgrade all connections too
        $connections = $this->connectionRepo->findBySourceNode($checkResult);
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $connection->setLevel($newLevel);
        }
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have upgraded [%s] to level [%s]'),
            $checkResult->getName(),
            $checkResult->getLevel()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // TODO update npc that have this home as home node
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has upgraded the node to level [%s]'),
            $this->user->getUsername(),
            $checkResult->getLevel()
        );
        $this->messageEveryoneInNodeNew($checkResult, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse;
    }

    /**
     * @return Node|NULL|string
     */
    private function upgradeNodeChecks()
    {
        $profile = $this->user->getProfile();
        $node = $profile->getCurrentNode();
        $checker = $this->checkSystemPermission($profile, $node->getSystem());
        if ($checker !== false) {
            return $checker;
        }
        if ($node->getLevel() >= self::MAX_NODE_LEVEL) {
            return $this->translate('This node is already at max level');
        }
        $nodeType = $node->getNodeType();
        $upgradeCost = $nodeType->getCost() * pow($node->getLevel(), $node->getLevel() + 1);
        if ($upgradeCost > $profile->getCredits()) {
            return sprintf(
                $this->translate('You need %s credits to upgrade this node'),
                $upgradeCost
            );
        }
        return $node;
    }

    /**
     * @return bool|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function addnodeChecks()
    {
        $profile = $this->user->getProfile();
        $node = $profile->getCurrentNode();
        $system = $node->getSystem();
        $checker = $this->checkSystemPermission($profile, $system);
        if ($checker !== false) {
            return $checker;
        }
        // check if they have enough credits
        if ($profile->getCredits() < self::RAW_NODE_COST) {
            return sprintf(
                $this->translate('You need %s credits to add a node to the system'),
                self::RAW_NODE_COST
            );
        }
        // check if the system has reached its max size
        $nodeamount = $this->nodeRepo->countBySystem($system);
        if ($nodeamount >= $system->getMaxSize()) {
            return $this->translate('System has reached its maximum size');
        }
        // check if we are in a home node, you can't add nodes to a home node
        if ($node->getNodeType()->getId() == NodeType::ID_HOME) {
            return $this->translate('You can not add nodes to a home node');
        }
        // check if there are enough cpus to support the new node
        $maxNodes = $this->getCurrentNodeMaximumForSystem($system);
        if ($nodeamount >= $maxNodes) {
            return $this->translate('You do not have enough CPU rating to add another node to this system - upgrade CPU nodes or add new CPU nodes');
        }
        return false;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function addNode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // check if they are busy
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $checkResult = $this->addnodeChecks();
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        /* checks passed, we can now add the node */
        // take creds from user
        $newCredits = $profile->getCredits() - self::RAW_NODE_COST;
        $profile->setCredits($newCredits);
        // create the new node
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_RAW);
        /** @var NodeType $nodeType */
        $node = new Node();
        $node->setCreated(new \DateTime());
        $node->setLevel(1);
        $node->setName($nodeType->getShortName());
        $node->setSystem($currentNode->getSystem());
        $node->setNodeType($nodeType);
        $this->entityManager->persist($node);
        $sourceConnection = new Connection();
        $sourceConnection->setType(Connection::TYPE_NORMAL);
        $sourceConnection->setLevel(1);
        $sourceConnection->setCreated(new \DateTime());
        $sourceConnection->setSourceNode($currentNode);
        $sourceConnection->setTargetNode($node);
        $sourceConnection->setIsOpen(false);
        $this->entityManager->persist($sourceConnection);
        $targetConnection = new Connection();
        $targetConnection->setType(Connection::TYPE_NORMAL);
        $targetConnection->setLevel(1);
        $targetConnection->setCreated(new \DateTime());
        $targetConnection->setSourceNode($node);
        $targetConnection->setTargetNode($currentNode);
        $targetConnection->setIsOpen(false);
        $this->entityManager->persist($targetConnection);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have added a new node to the system for %s credits'),
            self::RAW_NODE_COST
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] added a new node to the system'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew(
            $currentNode,
            $message,
            GameClientResponse::CLASS_MUTED,
            $profile,
            $profile->getId()
        );
        return $this->gameClientResponse;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function claimCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($serverSetting->getWildernessSystemId() == $currentSystem->getId()) {
            $message = $this->translate('Claiming wilderspace nodes is not implemented yet');
            $class = GameClientResponse::CLASS_DANGER;
        }
        else {
            if (!$currentSystem->getProfile() && !$currentSystem->getGroup() && !$currentSystem->getFaction()) {
                $message = $this->translate('This system can not be claimed');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            if ($currentSystem->getNoclaim()) {
                $message = $this->translate('This system can not be claimed');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            if ($currentSystem->getProfile() && $currentSystem->getProfile() === $profile) {
                $message = $this->translate('You can not claim your own systems');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            if ($currentSystem->getGroup() && $currentSystem->getGroup() === $profile->getGroup()) {
                $message = $this->translate('This system already belongs to your group');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            if ($currentSystem->getFaction() && $currentSystem->getFaction() === $profile->getFaction()) {
                $message = $this->translate('This system already belongs to your faction');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            if ($currentNode->getNodeType()->getId() != NodeType::ID_CPU) {
                $message = $this->translate('You must be in a CPU node to claim a system');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            if ($currentSystem->getIntegrity() >= 1) {
                $message = $this->translate('System integrity is not critical - unable to claim');
                return $this->gameClientResponse->addMessage($message)->send();
            }
            $claimed = false;
            $claimerName = false;
            // base checks are done, now we need to check who will get the system
            if ($currentSystem->getProfile()) {
                // the system is owned by a profile and will be given to the claiming profile
                $this->claimProfileSystem($currentSystem, $profile);
                $claimerName = $profile->getUser()->getUsername();
                $claimed = true;
            }
            if (!$claimed && $currentSystem->getGroup()) {
                // the system is owned by a group, check if the claiming profile belongs to a group too
                if ($profile->getGroup()) {
                    $this->claimGroupSystem($currentSystem, $profile->getGroup());
                    $claimerName = $profile->getGroup()->getName();
                    $claimed = true;
                }
                elseif ($profile->getFaction()) {
                    $this->claimFactionSystem($currentSystem, $profile->getFaction(), $currentSystem->getGroup());
                    $claimerName = $profile->getFaction()->getName();
                    $claimed = true;
                }
                else {
                    $message = $this->translate('Group systems can only be claimed by other groups and factions');
                    return $this->gameClientResponse->addMessage($message)->send();
                }
            }
            if (!$claimed && $currentSystem->getFaction()) {
                // the system is owned by a faction, check if the claiming profile belongs to a faction too
                if ($profile->getGroup()) {
                    $this->claimGroupSystem($currentSystem, $profile->getGroup());
                    $claimerName = $profile->getGroup()->getName();
                    $claimed = true;
                }
                elseif ($profile->getFaction()) {
                    $this->claimFactionSystem($currentSystem, $profile->getFaction());
                    $claimerName = $profile->getFaction()->getName();
                    $claimed = true;
                }
                else {
                    $message = $this->translate(
                        'Faction systems can only be claimed by other factions and groups'
                    );
                    return $this->gameClientResponse->addMessage($message)->send();
                }
            }
            if ($claimed) {
                $message = $this->translate('You have claimed the system!');
                $class = GameClientResponse::CLASS_SUCCESS;
                $broadcastMessage = sprintf(
                    'system [%s] was claimed by [%s]',
                    $currentSystem->getName(),
                    $claimerName
                );
                $broadcastClass = GameClientResponse::CLASS_INFO;
                $this->broadcastMessage($broadcastMessage, $broadcastClass);
                $currentSystem->setIntegrity(20);
                $this->entityManager->flush();
            }
            else {
                $message = $this->translate('Something went wrong while trying to claim the system');
                $class = GameClientResponse::CLASS_DANGER;
            }
        }
        return $this->gameClientResponse->addMessage($message, $class)->send();
    }

    /**
     * @param System $system
     * @param Profile $profile
     */
    private function claimProfileSystem(System $system, Profile $profile)
    {
        $oldOwner = $system->getProfile();
        $system->setProfile($profile);
        $files = $this->fileRepo->findBy([
            'system' => $system
        ]);
        /** @var File $file */
        foreach ($files as $file) {
            if ($file->getProfile() === $oldOwner) {
                $file->setProfile($profile);
            }
        }
        $npcs = $this->npcInstanceRepo->findBy([
            'system' => $system
        ]);
        /** @var NpcInstance $npc */
        foreach ($npcs as $npc) {
            if ($npc->getProfile() === $oldOwner) {
                $npc->setProfile($profile);
            }
        }
    }

    /**
     * @param System $system
     * @param Group $group
     */
    private function claimGroupSystem(System $system, Group $group)
    {
        $oldOwner = $system->getGroup();
        $oldFaction = $group->getFaction();
        $system->setGroup($group);
        if ($oldFaction) $system->setFaction(null);
        $npcs = $this->npcInstanceRepo->findBy([
            'system' => $system
        ]);
        /** @var NpcInstance $npc */
        foreach ($npcs as $npc) {
            if ($npc->getGroup() === $oldOwner) {
                $npc->setGroup($group);
            }
            if ($npc->getFaction() === $oldFaction) {
                $npc->setFaction($group->getFaction());
            }
        }
    }

    /**
     * @param System $system
     * @param Faction $faction
     * @param Group|null $oldGroup
     */
    private function claimFactionSystem(System $system, Faction $faction, Group $oldGroup = null)
    {
        $oldOwner = $system->getFaction();
        $system->setFaction($faction);
        if ($oldGroup) $system->setGroup(null);
        $npcs = $this->npcInstanceRepo->findBy([
            'system' => $system
        ]);
        /** @var NpcInstance $npc */
        foreach ($npcs as $npc) {
            if ($npc->getGroup()) {
                if ($oldGroup && $npc->getGroup() == $oldGroup) {
                    $npc->setGroup(null);
                    $npc->setFaction($faction);
                }
            }
            if ($npc->getFaction() === $oldOwner) {
                $npc->setFaction($faction);
            }
        }
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function exploreCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($currentSystem->getId() != $this->getServerSetting(self::SETTING_WILDERNESS_SYSTEM_ID)) {
            $message = $this->translate('You must be in Wilderspace to explore');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they can explore here - node might be claimed by another player
        if ($currentNode->getProfile() && $currentNode->getProfile() != $profile) {
            $message = $this->translate('Unable to explore in nodes that other users have claimed');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they can explore - node might already be at max level (dead-end) - level 10 nodes in wilderspace are the homes of true AI
        if ($currentNode->getLevel() >= 10) {
            $message = $this->translate('Unable to explore in max level nodes');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all checks passed, we can explore */
        // exploration is difficult, uses advanced coding and advanced networking for its skill check
        $advancedCoding = $this->getSkillRating($profile, Skill::ID_ADVANCED_CODING);
        $advancedNetworking = $this->getSkillRating($profile, Skill::ID_ADVANCED_NETWORKING);
        $chance = floor(($advancedCoding + $advancedNetworking) / 2);
        // node level makes it harder
        $chance -= ($currentNode->getLevel() * 10);
        if (mt_rand(1, 100) > $chance) {
            return $this->gameClientResponse
                ->addMessage($this->translate('You fail to find any hidden connections'))
                ->send(); // TODO make this take time
        }
        else {
            // player has found a hidden connection
            $excludedNodeTypes = [NodeType::ID_CPU, NodeType::ID_HOME, NodeType::ID_IO, NodeType::ID_PUBLICIO,
                NodeType::ID_RECRUITMENT];
            $exploredNodeType = $this->getRandomNodeType($excludedNodeTypes);
            $exploredNode =  new Node();
            $exploredNode->setSystem($currentSystem);
            $exploredNode->setProfile(NULL);
            $exploredNode->setCreated(new \DateTime());
            $exploredNode->setDescription($exploredNodeType->getDescription());
            $newLevel = (mt_rand(1, 100) > 90) ? $currentNode->getLevel() + 1 : $currentNode->getLevel();
            $exploredNode->setLevel($newLevel);
            $exploredNode->setName($exploredNodeType->getName());
            $exploredNode->setNodeType($exploredNodeType);
            $exploredNode->setNomob(false);
            $exploredNode->setNopvp(false);
            $this->entityManager->persist($exploredNode);
            $sourceConnection = new Connection();
            $sourceConnection->setType(Connection::TYPE_NORMAL);
            $sourceConnection->setLevel($newLevel);
            $sourceConnection->setCreated(new \DateTime());
            $sourceConnection->setSourceNode($currentNode);
            $sourceConnection->setTargetNode($exploredNode);
            $sourceConnection->setIsOpen(false);
            $this->entityManager->persist($sourceConnection);
            $targetConnection = new Connection();
            $targetConnection->setType(Connection::TYPE_NORMAL);
            $targetConnection->setLevel(1);
            $targetConnection->setCreated(new \DateTime());
            $targetConnection->setSourceNode($exploredNode);
            $targetConnection->setTargetNode($currentNode);
            $targetConnection->setIsOpen(false);
            $this->entityManager->persist($targetConnection);
            $this->entityManager->flush();
            $this->gameClientResponse->addMessage(
                $this->translate('You have found a hidden service'), GameClientResponse::CLASS_SUCCESS
            );
            $this->updateMap($resourceId);
        }
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is searching for a hidden service connection'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param array $excludedNodeTypes
     * @return null|NodeType
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function getRandomNodeType($excludedNodeTypes = [])
    {
        $nodeTypeId = mt_rand(1, 18);
        while (in_array($nodeTypeId, $excludedNodeTypes)) {
            $nodeTypeId = mt_rand(1, 18);
        }
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', $nodeTypeId);
        /** @var NodeType $nodeType */
        return $nodeType;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function changeNodeName($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        if (!$parameter) {
            return $this->gameClientResponse
                ->addMessage($this->translate('Please specify a new name for the node (alpha-numeric-only, 32-chars-max)'))
                ->send();
        }
        // check if they can change the name
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) {
            return $checker;
        }
        // check if only alphanumeric
        $checkResult = $this->stringChecker($parameter);
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        // turn spaces in name to underscores
        $name = str_replace(' ', '_', $parameter);
        $currentNode->setName($name);
        $this->entityManager->flush($currentNode);
        $message = sprintf(
            $this->translate('Node name changed to %s'),
            $name
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has changed the node name to [%s]</pre>'),
            $this->user->getUsername(),
            $name
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function changeNodeType($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* node types can be given by name or number, so we need to handle both */
        $checkResult = $this->changeNodeTypeChecks($contentArray);
        if (!$checkResult instanceof NodeType) {
            if ($checkResult instanceof GameClientResponse) {
                return $checkResult->send();
            }
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        // TODO a lot more stuff needs to be done depending on the existing node-type
        $currentCredits = $profile->getCredits();
        $profile->setCredits($currentCredits - $checkResult->getCost());
        $currentNode->setNodeType($checkResult);
        $currentNode->setLevel(1);
        $currentNode->setName($checkResult->getShortName());
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $connection->setLevel(1);
        }
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('Node type changed to %s'),
            $checkResult->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        return $this->gameClientResponse;
    }

    /**
     * @param $contentArray
     * @return NodeType|null|object|string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    private function changeNodeTypeChecks($contentArray)
    {
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentNodeType = $currentNode->getNodeType();
        $currentSystem = $currentNode->getSystem();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        if (!$parameter) {
            $gameResponse = new GameClientResponse($profile->getCurrentResourceId());
            $nodeTypes = $this->entityManager->getRepository('Netrunners\Entity\NodeType')->findAll();
            $returnMessage = $this->translate('Please choose a node type:');
            $gameResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
            foreach ($nodeTypes as $nodeType) {
                /** @var NodeType $nodeType */
                if ($nodeType->getId() == NodeType::ID_RAW) continue;
                $returnMessage = sprintf(
                    '%-2s|%-18s|%sc',
                    $nodeType->getId(),
                    $nodeType->getName(),
                    $nodeType->getCost()
                );
                $gameResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
            }
            return $gameResponse;
        }
        // check if they can change the type
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) {
            return $checker;
        }
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        if ($searchByNumber) {
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', $parameter);
        }
        else {
            $nodeType = $this->entityManager->getRepository('Netrunners\Entity\NodeType')->findOneBy([
                'name' => $parameter
            ]);
        }
        if (!$nodeType) {
            return $this->translate('No such node type');
        }
        // check a few combinations that are not valid
        if ($nodeType->getId() == NodeType::ID_HOME) {
            return $this->translate('Unable to change a node into a home node - please create a new node for the home');
        }
        // check if this is a market
        if ($currentNodeType->getId() == NodeType::ID_MARKET) {
            $auctionRepo = $this->entityManager->getRepository('Netrunners\Entity\Auction');
            /** @var AuctionRepository $auctionRepo */
            if ($auctionRepo->countByNode($currentNode) >= 1) {
                return $this->translate('Active markets can not be changed yet');
            }
        }
        // check if this is an io-node
        if ($currentNodeType->getId() == NodeType::ID_IO || $currentNodeType->getId() == NodeType::ID_PUBLICIO) {
            return $this->translate('Unable to remove I/O nodes');
        }
        // check if they have enough credits
        if ($profile->getCredits() < $nodeType->getCost()) {
            return sprintf(
                $this->translate('You need %s credits to add a node to the system'),
                $nodeType->getCost()
            );
        }
        // check if it is a recruitment node but not a faction or group system
        if (
            $nodeType->getId() == NodeType::ID_RECRUITMENT &&
            (!$currentSystem->getGroup() && !$currentSystem->getFaction())
        )
        {
            return $this->translate('Recruitment nodes can only be created in group or faction systems');
        }
        // check if this is a cpu node and the last one...
        $cpuCount = $this->nodeRepo->countBySystemAndType($currentSystem, $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU));
        if (
            $currentNodeType->getId() == NodeType::ID_CPU &&
            (int)$cpuCount < 2
        )
        {
            return $this->translate('Unable to remove the last CPU node of this system');
        }
        // check if this is a storage node and if the removal would still support all programs
        if ($currentNodeType->getId() == NodeType::ID_STORAGE) {
            $newMaxStorage = $this->getTotalStorage($profile) -
                ($currentNode->getLevel() * SystemService::BASE_STORAGE_VALUE);
            if ($this->getUsedStorage($profile) > $newMaxStorage) {
                return $this->translate('You could not store all of your programs after removing this node');
            }
        }
        // check if this is a memory node and if the removal would still support all programs
        if ($currentNodeType->getId() == NodeType::ID_MEMORY) {
            $newMaxMemoty = $this->getTotalMemory($profile) -
                ($currentNode->getLevel() * SystemService::BASE_MEMORY_VALUE);
            if ($this->getUsedMemory($profile) > $newMaxMemoty) {
                return $this->translate('You could not run all of your programs after removing this node');
            }
        }
        // check if this is a cpu node and if the removal would still support all nodes in the system
        if ($currentNodeType->getId() == NodeType::ID_CPU) {
            $maxNodes = $this->getCurrentNodeMaximumForSystem($currentSystem) -
                ($currentNode->getLevel() * self::MAX_NODES_MULTIPLIER);
            $nodeamount = $this->nodeRepo->countBySystem($currentSystem);
            if ($nodeamount > $maxNodes) {
                return $this->translate('Too many nodes depend on this cpu');
            }
        }
        if ($this->fileRepo->countByNode($currentNode) >= 1) {
            return $this->translate('Unable to change node that has programs');
        }
        if ($this->npcInstanceRepo->findOneByHomeNode($currentNode)) {
            return $this->translate('Unable to evict entity that originated from this node');
        }
        return $nodeType;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function editNodeDescription($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // only allow owner of system to add nodes
        $checker = $this->checkSystemPermission($profile, $currentNode->getSystem());
        if ($checker !== false) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        /* checks passed, we can now edit the node */
        $view = new ViewModel();
        $view->setTemplate('netrunners/node/edit-description.phtml');
        $description = $currentNode->getDescription();
        $processedDescription = '';
        if ($description) {
            $processedDescription = htmLawed($description, array('safe'=>1, 'elements'=>'strong, em, strike, u'));
        }
        $view->setVariable('description', $processedDescription);
        $view->setVariable('entityId', $currentNode->getId());
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is editing the node'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $content
     * @param $entityId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveNodeDescription($resourceId, $content, $entityId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $this->nodeRepo->find($entityId);
        // only allow owner of system to add nodes
        $checker = $this->checkSystemPermission($profile, $currentNode->getSystem());
        if ($checker !== false) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        /* checks passed, we can now edit the node */
        $content = htmLawed($content, ['safe'=>1,'elements'=>'strong,i,p,br']);
        $currentNode->setDescription($content);
        $this->entityManager->flush($currentNode);
        return $this->gameClientResponse
            ->addMessage($this->translate('Node description saved'), GameClientResponse::CLASS_SUCCESS)
            ->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function ninfoCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) { // TODO add check for wilderspace claimed nodes
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        $nodeData = $this->getNodeData($currentNode, true);
        $this->gameClientResponse->addMessage($this->translate('NODE PROPERTIES:'), GameClientResponse::CLASS_SYSMSG);
        if ($this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            $this->gameClientResponse->addMessage($currentNode->getId(), GameClientResponse::CLASS_ADDON);
        }
        foreach ($nodeData as $label => $value) {
            $response = sprintf(
                '%-12s: <span class="text-%s">%s</span>',
                $label,
                ($value) ? 'success' : 'danger',
                ($value) ? $this->translate('on') : $this->translate('off')
            );
            $this->gameClientResponse->addMessage($response, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function nset($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $currentNode = $profile->getCurrentNode();
        $nodeType = $currentNode->getNodeType();
        $currentSystem = $currentNode->getSystem();
        list($contentArray, $nodeProperty) = $this->getNextParameter($contentArray, true, false, false, true);
        $propertyValue = $this->getNextParameter($contentArray, false, false, false, true);
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) { // TODO add check for wilderspace claimed nodes
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        if (!$nodeProperty) {
            $message = $this->listNodePropertiesByNodeType($nodeType->getId());
            return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE)->send();
        }
        if ($nodeProperty && !$propertyValue) {
            $message = $this->translate('Please specify the property value (on/off)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all set, we can set the property
        $message = $this->setNodeProperty($currentNode, $nodeProperty, $propertyValue);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE)->send();
    }

    /**
     * @param $nodeTypeId
     * @return string
     */
    private function listNodePropertiesByNodeType($nodeTypeId)
    {
        return sprintf(
            '<span class="text-sysmsg">%s</span> %s',
            $this->translate('Possible properties for this node-type:'),
            wordwrap(implode(' ', $this->getNodePropertiesByType($nodeTypeId)), 120)
        );
    }

    /**
     * @param int $nodeTypeId
     * @return array
     */
    private function getNodePropertiesByType($nodeTypeId)
    {
        switch ($nodeTypeId) {
            default:
                $result = [];
                break;
            case NodeType::ID_FIREWALL:
            case NodeType::ID_TERMINAL:
            case NodeType::ID_DATABASE:
            case NodeType::ID_CPU:
                $result = [
                    'roaming',
                    'aggressive',
                    'codegates'
                ];
                break;
        }
        return $result;
    }

    /**
     * @param Node $node
     * @param $property
     * @param $valueString
     * @return string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function setNodeProperty(Node $node, $property, $valueString)
    {
        $nodeType = $node->getNodeType();
        $possibleNodeProperties = $this->getNodePropertiesByType($nodeType->getId());
        if (!in_array($property, $possibleNodeProperties)) {
            return $this->listNodePropertiesByNodeType($nodeType->getId());
        }
        switch ($valueString) {
            default:
                $value = 0;
                $valueString = 'off';
                break;
            case 'on':
                $value = 1;
                break;
        }
        $nodeData = $this->getNodeData($node);
        $nodeData->$property = $value;
        $node->setData(json_encode($nodeData));
        $this->entityManager->flush($node);
        return sprintf(
            $this->translate('[%s] set to [%s]'),
            $property,
            $valueString
        );
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function removeNode($resourceId)
    {
        // TODO needs some work - as a lot of stuff needs to happen on node removal
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentNodeType = $currentNode->getNodeType();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are allowed to remove nodes
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) { // TODO add check for wilderspace claimed nodes
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // check if there are still connections to this node
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
        if (count($connections) > 1) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to remove node with more than one connection'))->send();
        }
        // check if there are still files in this node
        $fileCount = $this->fileRepo->countByNode($currentNode);
        if ($fileCount > 0) {
            $message = $this->translate('Unable to remove node which still contains files');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if there are still npcs in this node
        $npcCount = $this->npcInstanceRepo->countByNode($currentNode);
        if ($npcCount > 0) {
            $message = $this->translate('Unable to remove node which still contains entities');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if there are still other profiles in this node
        $profileCount = $this->profileRepo->countByCurrentNode($currentNode);
        if ($profileCount > 1) {
            return $this->gameClientResponse->addMessage(
                $this->translate('Unable to remove node which still contains other users')
            )->send();
        }
        // check if this is the home node of someone
        $homeProfiles = $this->profileRepo->findBy([
            'homeNode' => $currentNode
        ]);
        if (count($homeProfiles) > 0) {
            return $this->gameClientResponse->addMessage(
                $this->translate('Unable to remove a node which is another user\'s home node')
            )->send();
        }
        // check if this is the home node of some npc
        $homeNpcs = $this->npcInstanceRepo->findOneByHomeNode($currentNode);
        if ($homeNpcs) {
            return $this->gameClientResponse->addMessage(
                $this->translate('Unable to remove a node which is still an entity\'s home node')
            )->send();
        }
        // check if this is a cpu node and the last one...
        $cpuCount = $this->nodeRepo->countBySystemAndType(
            $currentSystem,
            $this->entityManager->find(NodeType::class, NodeType::ID_CPU)
        );
        if (
            $currentNodeType->getId() == NodeType::ID_CPU &&
            (int)$cpuCount < 2
        )
        {
            return $this->gameClientResponse->addMessage(
                $this->translate('Unable to remove the last CPU node of this system')
            )->send();
        }
        // check if this is an io-node
        if (
            $currentNodeType->getId() == NodeType::ID_IO || $currentNode->getNodeType()->getId() == NodeType::ID_PUBLICIO
        )
        {
            return $this->gameClientResponse->addMessage($this->translate('Unable to remove I/O nodes'))->send();
        }
        // check if this is a storage node and if the removal would still support all programs
        if ($currentNodeType->getId() == NodeType::ID_STORAGE) {
            $newMaxStorage = $this->getTotalStorage($profile) -
                ($currentNode->getLevel() * SystemService::BASE_STORAGE_VALUE);
            if ($this->getUsedStorage($profile) > $newMaxStorage) {
                return $this->translate('You could not store all of your programs after removing this node');
            }
        }
        // check if this is a memory node and if the removal would still support all programs
        if ($currentNodeType->getId() == NodeType::ID_MEMORY) {
            $newMaxMemoty = $this->getTotalMemory($profile) - ($currentNode->getLevel() * SystemService::BASE_MEMORY_VALUE);
            if ($this->getUsedMemory($profile) > $newMaxMemoty) {
                return $this->translate('You could not run all of your programs after removing this node');
            }
        }
        // check if this is a cpu node and if the removal would still support all nodes in the system
        if ($currentNodeType->getId() == NodeType::ID_CPU) {
            $maxNodes = $this->getCurrentNodeMaximumForSystem($currentSystem) - 10;
            $nodeamount = $this->nodeRepo->countBySystem($currentSystem);
            if ($nodeamount > $maxNodes) {
                return $this->translate('Too many nodes depend on this cpu');
            }
        }
        // TODO adjust sanity checks for faction and group systems - programs in general need to only occupy storage/memory of the system they are used in
        /* all checks passed, we can now remove the node */
        $newCurrentNode = NULL;
        $connection = array_shift($connections);
        /** @var Connection $connection */
        $newCurrentNode = $connection->getTargetNode();
        $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($newCurrentNode, $currentNode);
        /** @var Connection $targetConnection */
        $this->entityManager->remove($targetConnection);
        $this->entityManager->remove($connection);
        $this->movePlayerToTargetNodeNew($resourceId, $profile, NULL, $currentNode, $newCurrentNode);
        $currentNodeName = $currentNode->getName();
        $this->entityManager->remove($currentNode);
        $this->entityManager->flush();
        $message = $this->translate('The node has been removed');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('The adjacent node [%s] has been removed'),
            $currentNodeName
        );
        $this->messageEveryoneInNodeNew(
            $newCurrentNode,
            $message,
            GameClientResponse::CLASS_MUTED,
            $profile,
            $profile->getId()
        );
        $this->connectionsChecked = [];
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function surveyNode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $returnMessage = $this->getSurveyText($currentNode);
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_DIRECTORY);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is looking around'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew(
            $currentNode,
            $message,
            GameClientResponse::CLASS_MUTED,
            $profile,
            $profile->getId()
        );
        return $this->gameClientResponse->send();
    }

    /**
     * Recursive function that checks if the given node is still connected to a node of the given node type.
     * @param Node $node
     * @param Connection|NULL $ignoredConnection
     * @param array $nodeTypeIds
     * @return bool
     */
    public function nodeStillConnectedToNodeType(
        Node $node,
        Connection $ignoredConnection = NULL,
        $nodeTypeIds = []
    )
    {
        $nodeTypeFound = false;
        foreach ($this->connectionRepo->findBySourceNode($node) as $connection) {
            /** @var Connection $connection */
            if ($connection == $ignoredConnection) {
                continue;
            }
            if (in_array($connection->getId(), $this->connectionsChecked)) {
                continue;
            }
            $this->connectionsChecked[] = $connection->getId();
            $targetNode = $connection->getTargetNode();
            if (in_array($targetNode->getNodeType()->getId(), $nodeTypeIds)) {
                $nodeTypeFound = true;
            }
            if ($nodeTypeFound) {
                break;
            }
            else {
                $targetConnection = $this->connectionRepo->findOneBy([
                    'sourceNode' => $targetNode,
                    'targetNode' => $node
                ]);
                $nodeTypeFound = $this->nodeStillConnectedToNodeType($targetNode, $targetConnection, $nodeTypeIds);
            }
        }
        return $nodeTypeFound;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listNodes($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they can list nodes
        if (!$this->canAccess($profile, $currentSystem)) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        $returnMessage = sprintf(
            '%-11s|%-20s|%-3s|%s',
            $this->translate('ID'),
            $this->translate('TYPE'),
            $this->translate('LVL'),
            $this->translate('NAME')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        $nodes = $this->nodeRepo->findBySystem($currentSystem);
        foreach ($nodes as $node) {
            /** @var Node $node */
            $returnMessage = sprintf(
                '%-11s|%-20s|%-3s|%s',
                $node->getId(),
                $node->getNodeType()->getName(),
                $node->getLevel(),
                $node->getName()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function systemConnect($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are in an io-node
        if ($currentNode->getNodeType()->getId() != NodeType::ID_PUBLICIO &&
            $currentNode->getNodeType()->getId() != NodeType::ID_IO
        ) {
            return $this->gameClientResponse->addMessage(
                $this->translate('You must be in an I/O node to connect to another system')
            )->send();
        }
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        if (!$parameter) {
            $publicIoNodes = $this->nodeRepo->findForConnectCommand($profile);
            $returnMessage = sprintf(
                '%-32s|%-40s|%-12s|%-20s',
                $this->translate('SYSTEM'),
                $this->translate('ADDRESS'),
                $this->translate('ID'),
                $this->translate('NAME')
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
            /** @var Node $publicIoNode */
            foreach ($publicIoNodes as $publicIoNode) {
                $returnMessage = sprintf(
                    '%-32s|%-40s|%-12s|%-20s',
                    $publicIoNode->getSystem()->getName(),
                    $publicIoNode->getSystem()->getAddy(),
                    $publicIoNode->getId(),
                    $publicIoNode->getName()
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
            }
            return $this->gameClientResponse->send();
        }
        $addy = $parameter;
        // check if the target system exists
        /** @var System $targetSystem */
        $targetSystem = $this->systemRepo->findByAddy($addy);
        if (!$targetSystem) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid system address'))->send();
        }
        $targetNode = NULL;
        // now check if the node id exists
        $targetNodeId = $this->getNextParameter($contentArray, false, true);
        if (!$targetNodeId) {
            $publicIoNodes = $this->nodeRepo->findForConnectCommand($profile);
            $returnMessage = sprintf(
                '%-32s|%-40s|%-12s|%-20s',
                $this->translate('SYSTEM'),
                $this->translate('ADDRESS'),
                $this->translate('ID'),
                $this->translate('NAME')
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
            /** @var Node $publicIoNode */
            foreach ($publicIoNodes as $publicIoNode) {
                if ($publicIoNode->getSystem() === $targetSystem) {
                    $returnMessage = sprintf(
                        '%-32s|%-40s|%-12s|%-20s',
                        $publicIoNode->getSystem()->getName(),
                        $publicIoNode->getSystem()->getAddy(),
                        $publicIoNode->getId(),
                        $publicIoNode->getName()
                    );
                    $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
                }
            }
            return $this->gameClientResponse->send();
        }
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
        if (!$targetNode) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid target node id'))->send();
        }
        if ($targetNode->getNodeType()->getId() != NodeType::ID_PUBLICIO &&
            $targetNode->getNodeType()->getId() != NodeType::ID_IO) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid node id'))->send();
        }
        if (
            $targetNode->getNodeType()->getId() == NodeType::ID_IO &&
            !$this->canAccess($profile, $targetSystem)
        ) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid node id'))->send();
        }
        if ($targetNode == $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('You are already there'))->send();
        }
        /** @var Node $targetNode */
        $this->movePlayerToTargetNodeNew(NULL, $profile, NULL, $currentNode, $targetNode);
        $this->gameClientResponse->addMessage(
            $this->translate('You have connected to the target system'),
            GameClientResponse::CLASS_SUCCESS
        );
        $this->updateMap($resourceId);
        $flytoResponse = new GameClientResponse($resourceId);
        $flytoResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
        $flytoResponse->addOption(
            GameClientResponse::OPT_CONTENT, explode(',',$targetNode->getSystem()->getGeocoords())
        );
        $flytoResponse->send();
        $this->gameClientResponse->setSilent(true)->send();
        return $this->showNodeInfoNew($resourceId, NULL, true);
    }

}
