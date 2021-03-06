<?php

/**
 * Base Service.
 * The service supplies a base for all complex services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Effect;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FileCategory;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\FileType;
use Netrunners\Entity\GameOption;
use Netrunners\Entity\Group;
use Netrunners\Entity\GroupRole;
use Netrunners\Entity\Invitation;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\Mission;
use Netrunners\Entity\MissionArchetype;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileEffect;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Entity\SystemLog;
use Netrunners\Entity\SystemRole;
use Netrunners\Entity\SystemRoleInstance;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\KnownNodeRepository;
use Netrunners\Repository\MissionRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\ProfileEffectRepository;
use Netrunners\Repository\ProfileFactionRatingRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SystemRepository;
use Netrunners\Repository\SystemRoleInstanceRepository;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\I18n\Validator\Alnum;
use Zend\Log\Logger;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class BaseService extends BaseUtilityService
{

    /**
     * @var PhpRenderer
     */
    protected $viewRenderer;

    /**
     * @var string
     */
    protected $profileLocale = 'en_US';

    /**
     * @var object
     */
    protected $clientData;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var array|false
     */
    protected $response = false;

    /**
     * @var bool|GameClientResponse
     */
    protected $gameClientResponse = false;

    /**
     * @var array
     */
    protected $updatedSockets = [];

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * BaseService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $entityGenerator);
        $this->translator = $translator;
        $this->viewRenderer = $viewRenderer;
    }

    /**
     * @param Profile $profile
     * @param int $amount
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function raiseProfileSecurityRating(Profile $profile, $amount = 0)
    {
        $currentRating = $profile->getSecurityRating();
        $profile->setSecurityRating($currentRating + $amount);
        $newRating = $profile->getSecurityRating();
        $currentNode = $profile->getCurrentNode();
        if ($newRating >= Profile::SECURITY_RATING_MAX) {
            $newRating = Profile::SECURITY_RATING_MAX;
            $profile->setSecurityRating(Profile::SECURITY_RATING_MAX);
        }
        if ($newRating >= Profile::SECURITY_RATING_NETWATCH_THRESHOLD) {
            $npcType = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_NETWATCH_INVESTIGATOR);
            if ($profile->getSecurityRating() >= 90) {
                $npcType = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_NETWATCH_AGENT);
            }
            /** @var Npc $npcType */
            $npcInstance = $this->spawnNpcInstance(
                $npcType,
                $currentNode,
                NULL,
                NULL,
                NULL,
                NULL,
                ceil(round($newRating/10)),
                true
            );
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has connected to this node from out of nowhere looking for [%s]</pre>'),
                $npcType->getName(),
                $profile->getUser()->getUsername()
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile);
            $this->forceAttack($npcInstance, $profile);
        }
        $this->entityManager->flush($profile);
    }

    /**
     * @param Profile|NpcInstance $attacker
     * @param Profile|NpcInstance $defender
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function forceAttack($attacker, $defender)
    {
        $ws = $this->getWebsocketServer();
        $attackerName = ($attacker instanceof Profile) ? $attacker->getUser()->getUsername() : $attacker->getName();
        $defenderName = ($defender instanceof Profile) ? $defender->getUser()->getUsername() : $defender->getName();
        $currentNode = ($attacker instanceof Profile) ? $attacker->getCurrentNode() : $attacker->getNode();
        if ($attacker instanceof Profile) {
            if ($defender instanceof Profile) {
                $ws->addCombatant($attacker, $defender, $attacker->getCurrentResourceId(), $defender->getCurrentResourceId());
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker, $defender->getCurrentResourceId(), $attacker->getCurrentResourceId());
            }
            if ($defender instanceof NpcInstance) {
                $ws->addCombatant($attacker, $defender, $attacker->getCurrentResourceId());
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker, NULL, $attacker->getCurrentResourceId());
            }
        }
        if ($attacker instanceof NpcInstance) {
            if ($defender instanceof Profile) {
                $ws->addCombatant($attacker, $defender, NULL, $defender->getCurrentResourceId());
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker, $defender->getCurrentResourceId());
            }
            if ($defender instanceof NpcInstance) {
                $ws->addCombatant($attacker, $defender);
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker);
            }
        }
        // inform players in node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
            $attackerName,
            $defenderName
        );
        $this->messageEveryoneInNode($currentNode, $message);
    }

    /**
     * @param System $system
     * @param string $subject
     * @param string $severity
     * @param null $details
     * @param File|NULL $file
     * @param Node|NULL $node
     * @param Profile|NULL $profile
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function writeSystemLogEntry(
        System $system,
        $subject = '',
        $severity = Notification::SEVERITY_INFO,
        $details = NULL,
        File $file = NULL,
        Node $node = NULL,
        Profile $profile = NULL
    )
    {
        $log = new SystemLog();
        $log->setAdded(new \DateTime());
        $log->setSystem($system);
        $log->setSubject($subject);
        $log->setSeverity($severity);
        $log->setDetails($details);
        $log->setFile($file);
        $log->setNode($node);
        $log->setProfile($profile);
        $this->entityManager->persist($log);
        $this->entityManager->flush($log);
        /** @var NodeRepository $nodeRepo */
        $nodeRepo = $this->entityManager->getRepository(Node::class);
        $monitoringNodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_MONITORING);
        foreach ($monitoringNodes as $monitoringNode) {
            $this->messageEveryoneInNodeNew(
                $monitoringNode,
                sprintf(
                    $this->translate("[LOG][%s]: %s"),
                    $log->getAdded()->format('Y-m-d H:i:s'),
                    $subject
                ),
                $severity
            );
        }
    }

    /**
     * @param Profile $profile
     * @param System $system
     * @return bool|string
     */
    protected function checkSystemPermission(Profile $profile, System $system)
    {
        if ($system->getProfile() && $system->getProfile() !== $profile) {
            return $this->translate('Permission denied');
        }
        if ($system->getFaction()) {
            return $this->translate('Permission denied'); // TODO change this once players can create factions
        }
        if ($system->getGroup() && $system->getGroup() !== $profile->getGroup()) {
            return $this->translate('Permission denied');
        }
        if ($system->getGroup() && !$this->memberRoleIsAllowed($profile, $system->getGroup(), GroupRole::$allowedBuilding)) {
            return $this->translate('Permission denied');
        }
        return false;
    }

    /**
     * @param Profile $profile
     * @param System $currentSytem
     * @param bool $checkRoles
     * @param string|bool $permission
     * @return bool
     */
    protected function canAccess(Profile $profile, System $currentSytem, $checkRoles = false, $permission = false)
    {
        // check for roles
        if ($checkRoles) {
            $canAccess = false;
            /** @var SystemRoleInstanceRepository $profileSystemRolesRepo */
            $profileSystemRolesRepo = $this->entityManager->getRepository(SystemRoleInstance::class);
            $systemRoles = $profileSystemRolesRepo->getProfileSystemRoles($profile, $currentSytem);
            switch ($permission) {
                default:
                    break;
                case SystemRole::ALLOWED_CONNECT:
                    /** @var SystemRoleInstance $systemRole */
                    foreach ($systemRoles as $systemRole) {
                        if (in_array($systemRole->getSystemRole()->getId(), SystemRole::$allowedConnect)) {
                            $canAccess = true;
                            break;
                        }
                    }
                    break;
                case SystemRole::ALLOWED_FREE_MOVEMENT:
                    /** @var SystemRoleInstance $systemRole */
                    foreach ($systemRoles as $systemRole) {
                        if (in_array($systemRole->getSystemRole()->getId(), SystemRole::$allowedFreeMovement)) {
                            $canAccess = true;
                            break;
                        }
                    }
                    break;
                case SystemRole::ALLOWED_BUILDING:
                    /** @var SystemRoleInstance $systemRole */
                    foreach ($systemRoles as $systemRole) {
                        if (in_array($systemRole->getSystemRole()->getId(), SystemRole::$allowedBuilding)) {
                            $canAccess = true;
                            break;
                        }
                    }
                    break;
                case SystemRole::ALLOWED_HARVESTING:
                    /** @var SystemRoleInstance $systemRole */
                    foreach ($systemRoles as $systemRole) {
                        if (in_array($systemRole->getSystemRole()->getId(), SystemRole::$allowedHarvesting)) {
                            $canAccess = true;
                            break;
                        }
                    }
                    break;
            }
        }
        else {
            $systemProfile = $currentSytem->getProfile();
            $systemGroup = $currentSytem->getGroup();
            $systemFaction = $currentSytem->getFaction();
            // set default
            $canAccess = true;
            if ($systemProfile && $systemProfile !== $profile) $canAccess = false;
            if ($systemFaction && $systemFaction !== $profile->getFaction()) $canAccess = false;
            if ($systemGroup && $systemGroup !== $profile->getGroup()) $canAccess = false;
        }
        if ($this->hasRole($profile->getUser(), Role::ROLE_ID_ADMIN)) {
            $canAccess = true;
        }
        return $canAccess;
    }

    /**
     * @param User|NULL $user
     * @param $roleId
     * @param bool $checkParents
     * @return bool
     */
    public function hasRole(User $user = NULL, $roleId, $checkParents = true)
    {
        $hasRole = false;
        $neededRole = $this->entityManager->getRepository('TmoAuth\Entity\Role')->findOneBy([
            'roleId' => $roleId
        ]);
        if (!$neededRole) return $hasRole;
        /** @var Role $neededRole */
        $roles = ($user) ? $user->getRoles() : $this->user->getRoles();
        foreach ($roles as $xRole) {
            /** @var Role $xRole */
            if ($this->checkParentRoleSatisfy($xRole, $neededRole, $checkParents) === true) {
                $hasRole = true;
                break;
            }
        }
        return $hasRole;
    }

    /**
     * @param Role $checkRole
     * @param Role $neededRole
     * @param bool $checkParents
     * @return bool
     */
    private function checkParentRoleSatisfy(
        Role $checkRole,
        Role $neededRole,
        $checkParents = true
    )
    {
        $hasRole = false;
        if ($checkRole->getRoleId() == $neededRole->getRoleId()) {
            $hasRole = true;
        }
        if (!$hasRole && $checkParents && $checkRole->getParent()) {
            $hasRole = $this->checkParentRoleSatisfy($checkRole->getParent(), $neededRole, $checkParents);
        }
        return $hasRole;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showCyberspaceMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        // TODO make admin+ only
        if (!$this->response) {
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
            /** @var NodeRepository $nodeRepo */
            $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
            /** @var ConnectionRepository $connectionRepo */
            $systems = $this->entityManager->getRepository('Netrunners\Entity\System')->findAll();
            foreach ($systems as $currentSystem) {
                /** @var System $currentSystem */
                $nodes = $nodeRepo->findBySystem($currentSystem);
                foreach ($nodes as $node) {
                    /** @var Node $node */
                    $group = $currentSystem->getId();
                    $mapArray['nodes'][] = [
                        'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                        'type' => $group
                    ];
                    $connections = $connectionRepo->findBySourceNode($node);
                    foreach ($connections as $connection) {
                        /** @var Connection $connection */
                        $typeValue = 'A';
                        if ($connection->getType() == Connection::TYPE_CODEGATE) {
                            if ($connection->getisOpen()) {
                                $typeValue = 'Y';
                            }
                            else {
                                $typeValue = 'E';
                            }
                        }
                        $mapArray['links'][] = [
                            'source' => (string)$connection->getSourceNode()->getId() . '_' .
                                $connection->getSourceNode()->getNodeType()->getShortName() . '_' .
                                $connection->getSourceNode()->getName(),
                            'target' => (string)$connection->getTargetNode()->getId() . '_' .
                                $connection->getTargetNode()->getNodeType()->getShortName() . '_' .
                                $connection->getTargetNode()->getName(),
                            'value' => 2,
                            'type' => $typeValue
                        ];
                    }
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showpanel',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showAreaMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
//        if ($this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
//            return $this->showSystemMap($resourceId);
//        }
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
            /** @var ConnectionRepository $connectionRepo */
            $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
            /** @var FileRepository $fileRepo */
            $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
            /** @var NpcInstanceRepository $npcInstanceRepo */
            $knRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
            /** @var KnownNodeRepository $knRepo */
            $profile = $this->user->getProfile();
            $currentNode = $profile->getCurrentNode();
            // if the profile or its faction or group owns this system, show them the full map
            $currentSystem = $currentNode->getSystem();
            if (
                $profile === $currentSystem->getProfile() ||
                ($profile->getFaction() && $profile->getFaction() == $currentSystem->getFaction()) ||
                ($profile->getGroup() && $profile->getGroup() == $currentSystem->getGroup())
            ) {
                return $this->showSystemMap($resourceId);
            }
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodes = [];
            $nodes[] = $currentNode;
            $connections = $connectionRepo->findBySourceNode($currentNode);
            foreach ($connections as $xconnection) {
                /** @var Connection $xconnection */
                $nodes[] = $xconnection->getTargetNode();
            }
            $knownNodes = $knRepo->findByProfileAndSystem($profile, $currentSystem);
            foreach ($knownNodes as $knownNode) {
                /** @var KnownNode $knownNode */
                $knownNodeNode = $knownNode->getNode();
                if (in_array($knownNodeNode, $nodes)) continue;
                $kconnections = $connectionRepo->findBySourceNode($knownNodeNode);
                foreach ($kconnections as $kconnection) {
                    /** @var Connection $kconnection */
                    $typeValue = 'A';
                    if ($kconnection->getType() == Connection::TYPE_CODEGATE) {
                        if ($kconnection->getisOpen()) {
                            $typeValue = 'Y';
                        }
                        else {
                            $typeValue = 'E';
                        }
                    }
                    if (in_array($kconnection->getTargetNode(), $nodes)) {
                        $mapArray['links'][] = [
                            'source' => (string)$kconnection->getSourceNode()->getId() . '_' . $kconnection->getSourceNode()->getNodeType()->getShortName() . '_' . $kconnection->getSourceNode()->getName(),
                            'target' => (string)$kconnection->getTargetNode()->getId() . '_' . $kconnection->getTargetNode()->getNodeType()->getShortName() . '_' . $kconnection->getTargetNode()->getName(),
                            'value' => 2,
                            'type' => $typeValue
                        ];
                    }
                }
                $nodes[] = $knownNodeNode;
            }
            $counter = true;
            foreach ($nodes as $node) {
                /** @var Node $node */
                $nodeType = $node->getNodeType();
                $fileNodes = [];
                $npcNodes = [];
                if ($node == $profile->getCurrentNode()) {
                    $group = 99;
                    $files = $fileRepo->findByNode($node);
                    $npcs = $npcInstanceRepo->findByNode($node);
                    foreach ($files as $file) {
                        $fileNodes[] = $file;
                    }
                    foreach ($npcs as $npc) {
                        $npcNodes[] = $npc;
                    }
                }
                else {
                    $group = $nodeType->getId();
                }
                $mapArray['nodes'][] = [
                    'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                    'type' => $group,
                    'shapetype' => 'circle'
                ];
                foreach ($fileNodes as $fileNode) {
                    /** @var File $fileNode */
                    $fileType = $fileNode->getFileType();
                    $mapArray['nodes'][] = [
                        'name' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                        'type' => $fileType->getId(),
                        'shapetype' => 'rect'
                    ];
                    $mapArray['links'][] = [
                        'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                        'target' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                        'value' => 2,
                        'type' => 'W'
                    ];
                }
                // add npcs to map
                foreach ($npcNodes as $npcNode) {
                    /** @var NpcInstance $npcNode */
                    $npcType = $npcNode->getNpc();
                    $mapArray['nodes'][] = [
                        'name' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                        'type' => $npcType->getId(),
                        'shapetype' => 'triangle'
                    ];
                    $mapArray['links'][] = [
                        'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                        'target' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                        'value' => 2,
                        'type' => 'Z'
                    ];
                }
                if ($counter) {
                    $connections = $connectionRepo->findBySourceNode($node);
                    foreach ($connections as $connection) {
                        /** @var Connection $connection */
                        $typeValue = 'A';
                        if ($connection->getType() == Connection::TYPE_CODEGATE) {
                            if ($connection->getisOpen()) {
                                $typeValue = 'Y';
                            }
                            else {
                                $typeValue = 'E';
                            }
                        }
                        $mapArray['links'][] = [
                            'source' => (string)$connection->getSourceNode()->getId() . '_' . $connection->getSourceNode()->getNodeType()->getShortName() . '_' . $connection->getSourceNode()->getName(),
                            'target' => (string)$connection->getTargetNode()->getId() . '_' . $connection->getTargetNode()->getNodeType()->getShortName() . '_' . $connection->getTargetNode()->getName(),
                            'value' => 2,
                            'type' => $typeValue
                        ];
                        $this->checkKnownNode($profile, $connection->getTargetNode());
                    }
                    $counter = false;
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showmap',
                'content' => $this->viewRenderer->render($view),
                'silent' => true
            );
        }
        return $this->response;
    }

    /**
     * Checks if the player is blocked from performing another action.
     * Returns true if the action is blocked, false if it is not blocked.
     * @param $resourceId
     * @param bool $checkForFullBlock
     * @param File|NULL $file
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function isActionBlocked($resourceId, $checkForFullBlock = false, File $file = NULL)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $isBlocked = false;
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
            $this->translate('You are currently busy with something else')
        );
        /* combat block check follows - combat never fully blocks */
        if (!$checkForFullBlock) {
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            $profile = $user->getProfile();
            $isBlocked = $this->isInCombat($profile);
            $fileUnblock = false;
            if ($isBlocked) {
                // if a file was given, we check if it a combat file and unblock if needed
                if ($file) {
                    $unblockingFileTypeIds = [FileType::ID_KICKER];
                    $isBlocked = (in_array($file->getFileType()->getId(), $unblockingFileTypeIds)) ? false : true;
                    // set fileunblock tracker to true if this unblocked them
                    if (!$isBlocked) $fileUnblock = true;
                }
                if ($isBlocked) {
                    $message = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You are currently busy fighting')
                    );
                }
            }
            // now check if they are under effects - like stunned - and only if they werent unblocked by the current file type
            if (!$isBlocked && !$fileUnblock && $this->isUnderEffect($profile, Effect::ID_STUNNED)) {
                $isBlocked = true;
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You are currently stunned')
                );
            }
        }
        /* action block check follows */
        if (!empty($clientData->action) && !$isBlocked) {
            $actionData = (object)$clientData->action;
            $isBlocked = false;
            if ($checkForFullBlock) {
                if ($actionData->fullblock) $isBlocked = true;
            }
            if (!$isBlocked) {
                if ($actionData->blocking) $isBlocked = true;
            }
        }
        if ($isBlocked) {
            $isBlocked = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $isBlocked;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showSystemMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $currentSystem = $profile->getCurrentNode()->getSystem();
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
            /** @var NodeRepository $nodeRepo */
            $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
            /** @var ConnectionRepository $connectionRepo */
            $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
            /** @var FileRepository $fileRepo */
            $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
            /** @var NpcInstanceRepository $npcInstanceRepo */
            $nodes = $nodeRepo->findBySystem($currentSystem);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $nodeType = $node->getNodeType();
                $fileNodes = [];
                $npcNodes = [];
                if ($node == $profile->getCurrentNode()) {
                    $group = 99;
                    $files = $fileRepo->findByNode($node);
                    foreach ($files as $file) {
                        $fileNodes[] = $file;
                    }
                    $npcs = $npcInstanceRepo->findByNode($node);
                    foreach ($npcs as $npc) {
                        $npcNodes[] = $npc;
                    }
                }
                else {
                    $group = $nodeType->getId();
                }
                $mapArray['nodes'][] = [
                    'name' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                    'type' => $group,
                    'shapetype' => 'circle'
                ];
                // add files to map
                foreach ($fileNodes as $fileNode) {
                    /** @var File $fileNode */
                    $fileType = $fileNode->getFileType();
                    $mapArray['nodes'][] = [
                        'name' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                        'type' => $fileType->getId(),
                        'shapetype' => 'rect'
                    ];
                    $mapArray['links'][] = [
                        'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                        'target' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                        'value' => 2,
                        'type' => 'W'
                    ];
                }
                // add npcs to map
                foreach ($npcNodes as $npcNode) {
                    /** @var NpcInstance $npcNode */
                    $npcType = $npcNode->getNpc();
                    $mapArray['nodes'][] = [
                        'name' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                        'type' => $npcType->getId(),
                        'shapetype' => 'triangle'
                    ];
                    $mapArray['links'][] = [
                        'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                        'target' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                        'value' => 2,
                        'type' => 'Z'
                    ];
                }
                $connections = $connectionRepo->findBySourceNode($node);
                foreach ($connections as $connection) {
                    /** @var Connection $connection */
                    $typeValue = 'A';
                    if ($connection->getType() == Connection::TYPE_CODEGATE) {
                        if ($connection->getisOpen()) {
                            $typeValue = 'Y';
                        }
                        else {
                            $typeValue = 'E';
                        }
                    }
                    $mapArray['links'][] = [
                        'source' => (string)$connection->getSourceNode()->getId() . '_' .
                            $connection->getSourceNode()->getNodeType()->getShortName() . '_' .
                            $connection->getSourceNode()->getName(),
                        'target' => (string)$connection->getTargetNode()->getId() . '_' .
                            $connection->getTargetNode()->getNodeType()->getShortName() . '_' .
                            $connection->getTargetNode()->getName(),
                        'value' => 2,
                        'type' => $typeValue
                    ];
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showmap',
                'content' => $this->viewRenderer->render($view),
                'silent' => true
            );
        }
        return $this->response;
    }

    /**
     * @param string $command
     * @param bool $content
     * @param bool $silent
     * @param null $response
     * @return bool|null|array
     */
    protected function addAdditionalCommand(
        $command = 'map',
        $content = false,
        $silent = true,
        $response = NULL
    )
    {
        if ($response) {
            if (!array_key_exists('additionalCommands', $response)) $response['additionalCommands'] = [];
            $response['additionalCommands'][] = [
                'command' => $command,
                'content' => $content,
                'silent' => $silent
            ];
            return $response;
        }
        $this->response['additionalCommands'][] = [
            'command' => $command,
            'content' => $content,
            'silent' => $silent
        ];
        return true;
    }

    /**
     * @param File $targetFile
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function generateFileInfo(File $targetFile)
    {
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Name"),
            $targetFile->getName()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Coder"),
            ($targetFile->getCoder()) ?
                $targetFile->getCoder()->getUser()->getUsername() :
                $this->translate('<span class="text-muted">system-generated</span>')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %smu',
            $this->translate("Size"),
            $targetFile->getSize()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Level"), $targetFile->getLevel()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Version"),
            $targetFile->getVersion()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Type"),
            $targetFile->getFileType()->getName()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s/%s',
            $this->translate("Integrity"),
            $targetFile->getIntegrity(),
            $targetFile->getMaxIntegrity()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Slots"),
            $targetFile->getSlots()
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Birth"),
            $targetFile->getCreated()->format('Y/m/d H:i:s')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $returnMessage = sprintf(
            '%-12s: %s',
            $this->translate("Modified"),
            ($targetFile->getModified()) ? $targetFile->getModified()->format('Y/m/d H:i:s') : $this->translate("---")
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        $categories = '';
        foreach ($targetFile->getFileType()->getFileCategories() as $fileCategory) {
            /** @var FileCategory $fileCategory */
            $categories .= $fileCategory->getName() . ' ';
        }
        $returnMessage = sprintf(
            '%s: %s',
            $this->translate("Categories"),
            $categories
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
        switch ($targetFile->getFileType()->getId()) {
            default:
                break;
            case FileType::ID_COINMINER:
                $fileData = json_decode($targetFile->getData());
                $returnMessage = sprintf(
                    '%-12s: %s',
                    $this->translate("Collected credits"),
                    (isset($fileData->value)) ? $fileData->value : 0
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
                break;
            case FileType::ID_DATAMINER:
                $fileData = json_decode($targetFile->getData());
                $returnMessage = sprintf(
                    '%-12s: %s',
                    $this->translate("Collected snippets"),
                    (isset($fileData->value)) ? $fileData->value : 0
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
                break;
            case FileType::ID_TEXT:
            case FileType::ID_PASSKEY:
                $fileData = $targetFile->getContent();
                $returnMessage = sprintf(
                    '%s<br/><span class="text-muted">%s</span>',
                    $this->translate("File content:"),
                    ($fileData) ? wordwrap($fileData, 120) : $this->translate('[CONTENT IS EMPTY]')
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
                break;
            case FileType::ID_CUSTOM_IDE:
                $returnMessage = sprintf(
                    '%s <span class="text-muted">%s</span>',
                    $this->translate("Effective skill boost:"),
                    $this->getBonusForFileLevel($targetFile)
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
                break;
        }
        // now show its file-mods
        /** @var FileModInstanceRepository $fileModInstanceRepo */
        $fileModInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FileModInstance');
        $fileModsCount = $fileModInstanceRepo->countByFile($targetFile);
        if ($fileModsCount >= 1) {
            $fileMods = $fileModInstanceRepo->findByFile($targetFile);
            $installedModsString = '';
            foreach ($fileMods as $fileMod) {
                /** @var FileModInstance $fileMod */
                $installedModsString .= $fileMod->getFileMod()->getName() . '|' . $fileMod->getLevel() . ' ';
            }
            $returnMessage = sprintf(
                '%s %s',
                $this->translate("Installed mods:"),
                wordwrap($installedModsString, 120)
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
        }
    }

    /**
     * @param int|string $parameter
     * @return NpcInstance|null
     */
    protected function findNpcByNameOrNumberInCurrentNode($parameter)
    {
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $npcs = $npcInstanceRepo->findBy([
            'node' => $this->user->getProfile()->getCurrentNode()
        ]);
        $npc = NULL;
        if ($searchByNumber) {
            $arrayKey = $parameter - 1;
            if (isset($npcs[$arrayKey])) {
                $npc = $npcs[$arrayKey];
            }
        }
        else {
            foreach ($npcs as $xnpc) {
                /** @var NpcInstance $xnpc */
                if (mb_strrpos($xnpc->getName(), $parameter) !== false) {
                    $npc = $xnpc;
                    break;
                }
            }
        }
        return $npc;
    }

    /**
     * @param int|string $parameter
     * @param Node $node
     * @return NpcInstance|null
     */
    protected function findNpcByNameOrNumberInNode($parameter, Node $node)
    {
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $npcs = $npcInstanceRepo->findBy([
            'node' => $node
        ]);
        $npc = NULL;
        if ($searchByNumber) {
            $arrayKey = $parameter - 1;
            if (isset($npcs[$arrayKey])) {
                $npc = $npcs[$arrayKey];
            }
        }
        else {
            foreach ($npcs as $xnpc) {
                /** @var NpcInstance $xnpc */
                if (mb_strrpos($xnpc->getName(), $parameter) !== false) {
                    $npc = $xnpc;
                    break;
                }
            }
        }
        return $npc;
    }

    /**
     * @param $parameter
     * @return Profile|null
     */
    protected function findProfileByNameOrNumberInCurrentNode($parameter)
    {
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $userProfile = $this->user->getProfile();
        $profiles = $profileRepo->findByNodeOrderedByResourceId($userProfile->getCurrentNode(), $userProfile);
        $profile = NULL;
        // search by number
        if ($searchByNumber) {
            $arrayKey = $parameter - 1;
            if (isset($profiles[$arrayKey])) {
                $profile = $profiles[$arrayKey];
                /** @var Profile $profile */
            }
        }
        else {
            // search by name
            foreach ($profiles as $xprofile) {
                /** @var Profile $xprofile */
                if (mb_strrpos($xprofile->getUser()->getUsername(), $parameter) !== false) {
                    $profile = $xprofile;
                    break;
                }
            }
        }
        return $profile;
    }

    /**
     * @param $parameter
     * @param Node $node
     * @return Profile|null
     */
    protected function findProfileByNameOrNumberInNode($parameter, Node $node)
    {
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $userProfile = $this->user->getProfile();
        $profiles = $profileRepo->findByNodeOrderedByResourceId($node, $userProfile);
        $profile = NULL;
        // search by number
        if ($searchByNumber) {
            $arrayKey = $parameter - 1;
            if (isset($profiles[$arrayKey])) {
                $profile = $profiles[$arrayKey];
                /** @var Profile $profile */
            }
        }
        else {
            // search by name
            foreach ($profiles as $xprofile) {
                /** @var Profile $xprofile */
                if (mb_strrpos($xprofile->getUser()->getUsername(), $parameter) !== false) {
                    $profile = $xprofile;
                    break;
                }
            }
        }
        return $profile;
    }

    /**
     * Moves the profile to the node specified by the connection or the target-node.
     * If no connection is given then source- and target-node must be given. This also messages all profiles
     * in the source- and target-node. If no resourceId is given, this will move the profile but not
     * generate a response message for the moved profile. If a resourceId is given, you can specify if the generated
     * node-info will be sent silently (prepend) by setting the prepend property.
     * @param int|NULL $resourceId
     * @param Profile $profile
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function movePlayerToTargetNodeNew(
        $resourceId = NULL,
        Profile $profile,
        Connection $connection = NULL,
        Node $sourceNode = NULL,
        Node $targetNode = NULL
    )
    {
        if ($connection) {
            $sourceNode = $connection->getSourceNode();
            $targetNode = $connection->getTargetNode();
        }
        $toString = ($connection) ? $targetNode->getName() : $this->translate('somewhere unknown');
        // message everyone in source node
        $messageText = sprintf(
            $this->translate('%s has used the connection to %s'),
            $profile->getUser()->getUsername(),
            $toString
        );
        $this->messageEveryoneInNodeNew($sourceNode, $messageText, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        $profile->setCurrentNode($targetNode);
        $this->entityManager->flush($profile);
        $fromString = ($connection) ? $sourceNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('%s has connected to this node from %s'),
            $profile->getUser()->getUsername(),
            $fromString
        );
        $this->messageEveryoneInNodeNew($targetNode, $messageText, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        $this->checkNpcAggro($profile, $resourceId); // TODO solve aggro in a different way
        $this->checkKnownNode($profile);
        $this->checkMoveFileTriggers($profile, $sourceNode);
    }

    /**
     * @param $string
     * @return string
     */
    protected function translate($string)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->translator->getTranslator()->setLocale($this->profileLocale);
        return $this->translator->translate($string);
    }

    /**
     * Sends the given message to everyone in the given node, optionally excluding the source of the message.
     * An actor can be given, if it is, the method will check if the current subject can see the actor.
     * If profiles is given, those profiles will be excluded as they will be considered to be the source of the message.
     * @param Node $node
     * @param $message
     * @param string $textClass
     * @param null|Profile|NpcInstance $actor
     * @param array $ignoredProfileIds
     * @param bool $updateMap
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function messageEveryoneInNodeNew(
        Node $node,
        $message,
        $textClass = GameClientResponse::CLASS_MUTED,
        $actor = NULL,
        $ignoredProfileIds = [],
        $updateMap = false
    )
    {
        /** @var ProfileRepository $profileRepo */
        $profileRepo = $this->entityManager->getRepository(Profile::class);
        $profiles = $profileRepo->findByCurrentNode($node, $actor, true);
        $response = new GameClientResponse(NULL, GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $response->addMessage($message, $textClass);
        foreach ($profiles as $xprofile) {
            /** @var Profile $xprofile */
            if (!is_array($ignoredProfileIds)) $ignoredProfileIds = [$ignoredProfileIds];
            if (in_array($xprofile->getId(), $ignoredProfileIds)) continue;
            if (!$xprofile->getCurrentResourceId()) continue;
            if ($xprofile !== $actor && !$this->canSee($xprofile, $actor)) continue;
            if ($updateMap) $this->updateMap($xprofile->getCurrentResourceId(), $xprofile);
            $response->setResourceId($xprofile->getCurrentResourceId())->send();
        }
    }

    /**
     * @param int $partyId
     * @param $message
     * @param string $textClass
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function messageEveryoneInParty(int $partyId, $message, $textClass = GameClientResponse::CLASS_MUTED)
    {
        $party = $this->getWebsocketServer()->getParty($partyId);
        if ($party) {
            $response = new GameClientResponse(NULL, GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
            $response->addMessage($message, $textClass);
            foreach ($party['members'] as $memberProfileId => $memberData) {
                /** @var Profile $memberProfile */
                $memberProfile = $this->entityManager->find('Netrunners\Entity\Profile', $memberProfileId);
                if ($memberProfile) {
                    $memberResourceId = $memberProfile->getCurrentResourceId();
                    if (!$memberResourceId) continue;
                    $response->setResourceId($memberResourceId)->send();
                }
            }
        }
    }

    /**
     * @param Profile|NpcInstance|File $detector
     * @param Profile|NpcInstance|File $stealther
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function canSee($detector, $stealther)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        // init vars
        $canSee = true;
        $detectorSkillRating = 0;
        $stealtherSkillRating = 0;
        $stealthing = false;
        $stealtherName = '';
        $detectorName = '';
        $currentNode = NULL;
        // get values depending on instance
        if ($stealther instanceof Profile) {
            $stealthing = $stealther->getStealthing();
            if (!$stealthing) return $canSee;
            $currentNode = $stealther->getCurrentNode();
            $stealtherName = $stealther->getUser()->getUsername();
            $stealtherSkillRating = $this->getSkillRating($stealther, Skill::ID_STEALTH);
            // add cloaks
            $cloaks = $fileRepo->findRunningByProfileAndType($stealther, FileType::ID_CLOAK);
            foreach ($cloaks as $cloak) {
                /** @var File $cloak */
                $stealtherSkillRating += $this->getBonusForFileLevel($cloak);
                $this->lowerIntegrityOfFile($cloak, 50, 1, true);
            }
        }
        if ($stealther instanceof NpcInstance) {
            $stealthing = $stealther->getStealthing();
            if (!$stealthing) return $canSee;
            $currentNode = $stealther->getNode();
            $stealtherName = $stealther->getName();
            $stealtherSkillRating = $this->getSkillRating($stealther, Skill::ID_STEALTH);
            // if detector is owner then they can always see their instances
            if ($detector instanceof Profile) {
                if ($detector == $stealther->getProfile()) $stealthing = false;
            }
            // TODO add programs that modify ratings
        }
        if ($stealther instanceof File) {
            $stealthing = $stealther->getFileType()->getStealthing();
            if (!$stealthing) return $canSee;
            $currentNode = $stealther->getNode();
            $stealtherName = $stealther->getName();
            $skillRating = ceil(($stealther->getIntegrity() + $stealther->getLevel()) / 2);
            $stealtherSkillRating = $skillRating;
            // if detector is owner then they can always see their files
            if ($detector instanceof Profile) {
                if ($detector == $stealther->getProfile()) $stealthing = false;
            }
            // TODO add mods that modify ratings
        }
        if ($detector instanceof Profile) {
            $detectorSkillRating = $this->getSkillRating($detector, Skill::ID_DETECTION);
            $detectorName = ($detector->getStealthing()) ? 'something' : $detector->getUser()->getUsername();
        }
        if ($detector instanceof NpcInstance) {
            $detectorSkillRating = $this->getSkillRating($detector, Skill::ID_DETECTION);
            $detectorName = ($detector->getStealthing()) ? 'something' : $detector->getName();
        }
        if ($detector instanceof File) {
            $detectorSkillRating = $detector->getLevel();
            $detectorName = ($detector->getFileType()->getStealthing()) ? 'something' : $detector->getName();
        }
        // only check if they are actively stealthing
        if ($stealthing) {
            $chance = 50 + $detectorSkillRating - $stealtherSkillRating;
            if ($this->makePercentRollAgainstTarget($chance)) $canSee = false;
            // check for skill gain
            if ($canSee) {
                if ($detector instanceof Profile) $this->learnFromSuccess($detector, ['skills' => ['detection']], -50);
                if ($stealther instanceof Profile) {
                    $this->learnFromFailure($stealther, ['skills' => ['stealth']], -50);
                    $stealther->setStealthing(false);
                    $this->entityManager->flush($stealther);
                }
                // message everyone in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-attention">[%s] has detected [%s]</pre>'),
                    $detectorName,
                    $stealtherName
                );
                $this->messageEveryoneInNode($currentNode, $message);
            }
            else {
                if ($detector instanceof Profile) $this->learnFromFailure($detector, ['skills' => ['detection']], -50);
                if ($stealther instanceof Profile) $this->learnFromSuccess($stealther, ['skills' => ['stealth']], -50);
            }
        }
        // return result
        return $canSee;
    }

    /**
     * @param File|NULL $file
     * @return float|int
     */
    protected function getBonusForFileLevel(File $file = NULL)
    {
        $bonus = 0;
        if ($file) {
            $level = $file->getLevel();
            $integrity = $file->getIntegrity();
            $bonus = ($level/100) * $integrity;
        }
        return ceil(round($bonus));
    }

    /**
     * @param File $file
     * @param int $chance
     * @param int $integrityLoss
     * @param bool $flush
     * @param null $targetFile
     * @param null $targetNpc
     * @param null $targetNode
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function lowerIntegrityOfFile(
        File $file,
        $chance = 100,
        $integrityLoss = 1,
        $flush = false,
        $targetFile = NULL,
        $targetNpc = NULL,
        $targetNode = NULL
    )
    {
        if ($chance == 100 || $this->makePercentRollAgainstTarget($chance)) {
            $currentIntegrity = $file->getIntegrity();
            $newIntegrity = $this->checkValueMinMax($currentIntegrity - $integrityLoss, 0);
            $file->setIntegrity($newIntegrity);
            if ($newIntegrity < 1) {
                $profile = $file->getProfile();
                if ($file->getMaxIntegrity() <= 1) {
                    if ($profile) {
                        $message = sprintf(
                            $this->translate("[%s][%s] has lost all of its integrity and needs to be updated"),
                            $file->getName(),
                            $file->getId()
                        );
                        $this->removeFileFromProfile($file);
                        $this->storeNotification($file->getProfile(), $message, Notification::SEVERITY_WARNING);
                    }
                    // destroy the file
                    $this->destroyFile($file);
                }
                else {
                    if ($profile) {
                        $this->removeFileFromProfile($file);
                        $message = sprintf(
                            $this->translate("[%s][%s] has lost all of its integrity and needs to be updated"),
                            $file->getName(),
                            $file->getId()
                        );
                        $this->storeNotification($file->getProfile(), $message, Notification::SEVERITY_WARNING);
                    }
                    $file->setRunning(false);
                }
            }
            if ($flush) $this->entityManager->flush($file);
        }
    }

    /**
     * @param File $file
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function removeFileFromProfile(File $file)
    {
        // check if this is equipment
        $profile = $file->getProfile();
        if ($profile) {
            if ($file->getFileType()->getId() == FileType::ID_CODEBLADE) {
                $profile->setBlade(null);
                $this->entityManager->flush($profile);
            }
            if ($file->getFileType()->getId() == FileType::ID_CODEBLASTER) {
                $profile->setBlaster(null);
                $this->entityManager->flush($profile);
            }
            if ($file->getFileType()->getId() == FileType::ID_CODESHIELD) {
                $profile->setShield(null);
                $this->entityManager->flush($profile);
            }
            if ($file->getFileType()->getId() == FileType::ID_CODEARMOR) {
                $fileData = json_decode($file->getData());
                switch ($fileData->subtype) {
                    default:
                        break;
                    case FileType::SUBTYPE_ARMOR_HEAD:
                        $profile->setHeadArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_SHOULDERS:
                        $profile->setShoulderArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_UPPER_ARM:
                        $profile->setUpperArmArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_LOWER_ARM:
                        $profile->setLowerArmArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_HANDS:
                        $profile->setHandArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_TORSO:
                        $profile->setTorsoArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_LEGS:
                        $profile->setLegArmor(null);
                        break;
                    case FileType::SUBTYPE_ARMOR_SHOES:
                        $profile->setShoesArmor(null);
                        break;
                }
                $this->entityManager->flush($profile);
            }
        }
    }

    /**
     * @param Profile $profile
     * @param string $subject
     * @param string $severity
     * @return bool
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function storeNotification(Profile $profile, $subject = 'INVALID', $severity = Notification::SEVERITY_DANGER)
    {
        $notification = new Notification();
        $notification->setProfile($profile);
        $notification->setSentDateTime(new \DateTime());
        $notification->setSubject($subject);
        $notification->setSeverity($severity);
        $this->entityManager->persist($notification);
        $this->entityManager->flush($notification);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @param int $modifier
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function learnFromSuccess(Profile $profile, $jobData, $modifier = 0)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $this->reverseSkillNameModification($skillName)
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill->getId());
            // return true if already at max
            if ($skillRating > 99) return true;
            // calculate chance
            $chance = 100 - $skillRating + $modifier;
            // return true if chance smaller than one
            if ($chance < 1) return true;
            // roll
            if ($this->makePercentRollAgainstTarget($chance)) {
                // calc new skill-rating, set and message
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
                $message = sprintf(
                    $this->translate('You have gained a level in %s'),
                    $skill->getName()
                );
                $this->messageProfileNew($profile, $message, GameClientResponse::CLASS_ATTENTION);
                // check if the should receive skillpoints for reaching a milestone
                if ($newSkillRating%10 == 0) {
                    // skillrating divisible by 10, gain skillpoints
                    $this->gainSkillpoints($profile, floor(round($newSkillRating / 10)));
                    // check if they just mastered the skill and give them an invitation as a reward
                    if ($newSkillRating >= 100) {
                        $this->gainInvitation($profile);
                    }
                }
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param $skillName
     * @return mixed
     */
    protected function reverseSkillNameModification($skillName)
    {
        return str_replace('-', ' ', $skillName);
    }

    /**
     * @param Profile $profile
     * @param Skill $skill
     * @param $newSkillRating
     * @return bool
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function setSkillRating(Profile $profile, Skill $skill, $newSkillRating)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $skillRatingObject = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
        /** @var SkillRating $skillRatingObject */
        $skillRatingObject->setRating($newSkillRating);
        $this->entityManager->flush($skillRatingObject);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $message
     * @param string $textClass
     * @return GameClientResponse
     * @throws \Exception
     */
    protected function messageProfileNew(Profile $profile, $message, $textClass = GameClientResponse::CLASS_MUTED)
    {
        $clientResponse = new GameClientResponse($profile->getCurrentResourceId());
        $clientResponse->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $clientResponse->addMessage($message, $textClass);
        return $clientResponse->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return File
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function triggerProgramExecutionReaction(File $file, Node $node)
    {
        $fileType = $file->getFileType();
        switch ($fileType->getId()) {
            default:
                break;
            case FileType::ID_PORTSCANNER:
            case FileType::ID_JACKHAMMER:
            case FileType::ID_WORMER:
            case FileType::ID_SIPHON:
                /** @var FileRepository $fileRepo */
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                $reactingPrograms = $fileRepo->findRunningInNodeByMod($node, FileMod::ID_BACKSLASH);
                foreach ($reactingPrograms as $reactingProgram) {
                    /** @var File $reactingProgram */
                    if ($this->makePercentRollAgainstTarget($reactingProgram->getLevel())) {
                        $integrityDamage = $reactingProgram->getIntegrity();
                        $file->setIntegrity($file->getIntegrity() - $integrityDamage);
                        if ($file->getIntegrity()<0) $file->setIntegrity(0);
                        $this->lowerIntegrityOfFile($reactingProgram, 100, 1, true);
                    }
                }
                break;
        }
        return $file;
    }

    /**
     * @param Profile $profile
     * @param $amount
     * @param bool $flush
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    protected function gainSkillpoints(Profile $profile, $amount, $flush = false)
    {
        $profile->setSkillPoints($profile->getSkillPoints() + $amount);
        $message = sprintf(
            $this->translate('You have received %s skillpoints'),
            $amount
        );
        $this->messageProfileNew($profile, $message, GameClientResponse::CLASS_ATTENTION);
        if ($flush) $this->entityManager->flush($profile);
    }

    /**
     * @param Profile $profile
     * @param null|int $special
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    protected function gainInvitation(Profile $profile, $special = NULL)
    {
        $given = new \DateTime();
        $code = md5($given->format('Y/m/d-H:i:s') . '-' . $profile->getId());
        $invitation = new Invitation();
        $invitation->setCode($code);
        $invitation->setGiven($given);
        $invitation->setUsed(NULL);
        $invitation->setGivenTo($profile);
        $invitation->setUsedBy(NULL);
        $invitation->setSpecial($special);
        $this->entityManager->persist($invitation);
        $this->entityManager->flush($invitation);
        $message = sprintf(
            '%s',
            ($special) ?
                $this->translate('You have gained a special invitation (see "invitations" for a list)') :
                $this->translate('You have gained an invitation (see "invitations" for a list)')
        );
        $this->messageProfileNew($profile, $message, GameClientResponse::CLASS_ATTENTION);
    }

    /**
     * Players can learn from failure, but not a lot.
     * @param Profile $profile
     * @param $jobData
     * @param int $modifier
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function learnFromFailure(Profile $profile, $jobData, $modifier = 0)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $this->reverseSkillNameModification($skillName)
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill->getId());
            if ($skillRating >= SkillRating::MAX_SKILL_RATING_FAIL_LEARN) continue;
            $chance = 100 - $skillRating + $modifier;
            if ($chance < 1) return true;
            if ($this->makePercentRollAgainstTarget($chance)) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
                $message = sprintf(
                    $this->translate('You have gained a level in %s'),
                    $skill->getName()
                );
                $this->messageProfileNew($profile, $message, GameClientResponse::CLASS_ATTENTION);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * Sends the given message to everyone in the given node, optionally excluding the source of the message.
     * An actor can be given, if it is, the method will check if the current subject can see the actor.
     * If profiles is given, those profiles will be excluded as they will be considered to be the source of the message.
     * @param Node $node
     * @param $message
     * @param Profile|NpcInstance|null $actor
     * @param mixed $ignoredProfileIds
     * @param bool $updateMap
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function messageEveryoneInNode(Node $node, $message, $actor = NULL, $ignoredProfileIds = [], $updateMap = false)
    {
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
        $wsClients = $this->getWebsocketServer()->getClients();
        $wsClientsData = $this->getWebsocketServer()->getClientsData();
        $profiles = $profileRepo->findByCurrentNode($node, $actor, true);
        foreach ($profiles as $xprofile) {
            /** @var Profile $xprofile */
            if (!is_array($ignoredProfileIds)) $ignoredProfileIds = [$ignoredProfileIds];
            if (in_array($xprofile->getId(), $ignoredProfileIds)) continue;
            if ($xprofile !== $actor && !$this->canSee($xprofile, $actor)) continue;
            // new
//            if ($updateMap && !array_key_exists($xprofile->getId(), $this->updatedSockets)) {
//                $this->updatedSockets[$xprofile->getId()] = $xprofile->getId();
//            }
//            else {
//                $this->messageProfile($xprofile, $message);
//            }
            // old
            foreach ($wsClients as $wsClient) {
                if (
                    isset($wsClientsData[$wsClient->resourceId]) &&
                    $wsClientsData[$wsClient->resourceId]['hash'] &&
                    $wsClientsData[$wsClient->resourceId]['profileId'] == $xprofile->getId()
                ) {
                    if (!is_array($message)) {
                        $message = [
                            'command' => 'showmessageprepend',
                            'message' => $message
                        ];
                        if ($updateMap && !array_key_exists($wsClient->resourceId, $this->updatedSockets)) {
                            $this->updatedSockets[$wsClient->resourceId] = $wsClient;
                        }
                        else {
                            $this->messageProfile($xprofile, $message);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Profile $profile
     * @param null|string|array $message
     * @param bool $noPrepend
     * @param bool $sendMessage
     * @param bool|string $wrap
     * @return array|bool
     */
    protected function messageProfile(Profile $profile, $message = NULL, $noPrepend = false, $sendMessage = true, $wrap = false)
    {
        $wsClient = $this->getWsClientByProfile($profile);
        if ($wsClient) {
            if (!$message) {
                $message = 'INVALID SYSTEM-MESSAGE RECEIVED';
            }
            if ($wrap) {
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-%s">%s</pre>',
                    $wrap
                );
            }
            if (is_array($message)) {
                $command = ($noPrepend) ? 'showoutput' : 'showoutputprepend';
            }
            else {
                $command = ($noPrepend) ? 'showmessage' : 'showmessageprepend';
            }
            $response = [
                'command' => $command,
                'message' => $message
            ];
            if ($sendMessage) {
                $wsClient->send(json_encode($response));
            }
            else {
                return $response;
            }
        }
        return true;
    }

    /**
     * @param $resourceId
     * @param Profile|NULL $profile
     * @param bool $silent
     * @return GameClientResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function updateMap($resourceId, Profile $profile = NULL, $silent = true)
    {
        $updatedMapPackage = new GameClientResponse($resourceId, GameClientResponse::COMMAND_SHOWMAP, [], $silent);
        $updatedMapPackage->addOption(GameClientResponse::OPT_CONTENT, $this->generateAreaMap($profile));
        return $updatedMapPackage->send();
    }

    /**
     * @param Profile|NULL $xprofile
     * @return string
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function generateAreaMap(Profile $xprofile = NULL)
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $knRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
        /** @var KnownNodeRepository $knRepo */
        $profile = ($xprofile) ? $xprofile : $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // if the profile or its faction or group owns this system, show them the full map
        $currentSystem = $currentNode->getSystem();
        if (
            $profile === $currentSystem->getProfile() ||
            ($profile->getFaction() && $profile->getFaction() == $currentSystem->getFaction()) ||
            ($profile->getGroup() && $profile->getGroup() == $currentSystem->getGroup())
        ) {
            return $this->generateSystemMap($xprofile);
        }
        $mapArray = [
            'nodes' => [],
            'links' => []
        ];
        $nodes = [];
        $nodes[] = $currentNode;
        $connections = $connectionRepo->findBySourceNode($currentNode);
        foreach ($connections as $xconnection) {
            /** @var Connection $xconnection */
            $nodes[] = $xconnection->getTargetNode();
        }
        $knownNodes = $knRepo->findByProfileAndSystem($profile, $currentSystem);
        foreach ($knownNodes as $knownNode) {
            /** @var KnownNode $knownNode */
            $knownNodeNode = $knownNode->getNode();
            if (in_array($knownNodeNode, $nodes)) continue;
            $kconnections = $connectionRepo->findBySourceNode($knownNodeNode);
            foreach ($kconnections as $kconnection) {
                /** @var Connection $kconnection */
                $typeValue = 'A';
                if ($kconnection->getType() == Connection::TYPE_CODEGATE) {
                    if ($kconnection->getisOpen()) {
                        $typeValue = 'Y';
                    }
                    else {
                        $typeValue = 'E';
                    }
                }
                if (in_array($kconnection->getTargetNode(), $nodes)) {
                    $mapArray['links'][] = [
                        'source' => (string)$kconnection->getSourceNode()->getId() . '_' . $kconnection->getSourceNode()->getNodeType()->getShortName() . '_' . $kconnection->getSourceNode()->getName(),
                        'target' => (string)$kconnection->getTargetNode()->getId() . '_' . $kconnection->getTargetNode()->getNodeType()->getShortName() . '_' . $kconnection->getTargetNode()->getName(),
                        'value' => 2,
                        'type' => $typeValue
                    ];
                }
            }
            $nodes[] = $knownNodeNode;
        }
        $counter = true;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $nodeType = $node->getNodeType();
            $fileNodes = [];
            $npcNodes = [];
            if ($node == $profile->getCurrentNode()) {
                $group = 99;
                $files = $fileRepo->findByNode($node);
                $npcs = $npcInstanceRepo->findByNode($node);
                foreach ($files as $file) {
                    $fileNodes[] = $file;
                }
                foreach ($npcs as $npc) {
                    $npcNodes[] = $npc;
                }
            }
            else {
                $group = $nodeType->getId();
            }
            $mapArray['nodes'][] = [
                'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                'type' => $group,
                'shapetype' => 'circle'
            ];
            foreach ($fileNodes as $fileNode) {
                /** @var File $fileNode */
                $fileType = $fileNode->getFileType();
                $mapArray['nodes'][] = [
                    'name' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                    'type' => $fileType->getId(),
                    'shapetype' => 'rect'
                ];
                $mapArray['links'][] = [
                    'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                    'target' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                    'value' => 2,
                    'type' => 'W'
                ];
            }
            // add npcs to map
            foreach ($npcNodes as $npcNode) {
                /** @var NpcInstance $npcNode */
                $npcType = $npcNode->getNpc();
                $mapArray['nodes'][] = [
                    'name' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                    'type' => $npcType->getId(),
                    'shapetype' => 'triangle'
                ];
                $mapArray['links'][] = [
                    'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                    'target' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                    'value' => 2,
                    'type' => 'Z'
                ];
            }
            if ($counter) {
                $connections = $connectionRepo->findBySourceNode($node);
                foreach ($connections as $connection) {
                    /** @var Connection $connection */
                    $typeValue = 'A';
                    if ($connection->getType() == Connection::TYPE_CODEGATE) {
                        if ($connection->getisOpen()) {
                            $typeValue = 'Y';
                        }
                        else {
                            $typeValue = 'E';
                        }
                    }
                    $mapArray['links'][] = [
                        'source' => (string)$connection->getSourceNode()->getId() . '_' . $connection->getSourceNode()->getNodeType()->getShortName() . '_' . $connection->getSourceNode()->getName(),
                        'target' => (string)$connection->getTargetNode()->getId() . '_' . $connection->getTargetNode()->getNodeType()->getShortName() . '_' . $connection->getTargetNode()->getName(),
                        'value' => 2,
                        'type' => $typeValue
                    ];
                    $this->checkKnownNode($profile, $connection->getTargetNode());
                }
                $counter = false;
            }
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/partials/map.phtml');
        $view->setVariable('json', json_encode($mapArray));
        return $this->viewRenderer->render($view);
    }

    /**
     * @param Profile|NULL $xprofile
     * @return string
     */
    public function generateSystemMap(Profile $xprofile = NULL)
    {
        $profile = ($xprofile) ? $xprofile : $this->user->getProfile();
        $currentSystem = $profile->getCurrentNode()->getSystem();
        $mapArray = [
            'nodes' => [],
            'links' => []
        ];
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $nodes = $nodeRepo->findBySystem($currentSystem);
        foreach ($nodes as $node) {
            /** @var Node $node */
            $nodeType = $node->getNodeType();
            $fileNodes = [];
            $npcNodes = [];
            if ($node == $profile->getCurrentNode()) {
                $group = 99;
                $files = $fileRepo->findByNode($node);
                foreach ($files as $file) {
                    $fileNodes[] = $file;
                }
                $npcs = $npcInstanceRepo->findByNode($node);
                foreach ($npcs as $npc) {
                    $npcNodes[] = $npc;
                }
            }
            else {
                $group = $nodeType->getId();
            }
            $mapArray['nodes'][] = [
                'name' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                'type' => $group,
                'shapetype' => 'circle'
            ];
            // add files to map
            foreach ($fileNodes as $fileNode) {
                /** @var File $fileNode */
                $fileType = $fileNode->getFileType();
                $mapArray['nodes'][] = [
                    'name' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                    'type' => $fileType->getId(),
                    'shapetype' => 'rect'
                ];
                $mapArray['links'][] = [
                    'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                    'target' => (string)$fileNode->getId() . '_' . $fileType->getName() . '_' . $fileNode->getName(),
                    'value' => 2,
                    'type' => 'W'
                ];
            }
            // add npcs to map
            foreach ($npcNodes as $npcNode) {
                /** @var NpcInstance $npcNode */
                $npcType = $npcNode->getNpc();
                $mapArray['nodes'][] = [
                    'name' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                    'type' => $npcType->getId(),
                    'shapetype' => 'triangle'
                ];
                $mapArray['links'][] = [
                    'source' => (string)$node->getId() . '_' . $nodeType->getShortName() . '_' . $node->getName(),
                    'target' => (string)$npcNode->getId() . '_' . $npcType->getName() . '_' . $npcNode->getName(),
                    'value' => 2,
                    'type' => 'Z'
                ];
            }
            $connections = $connectionRepo->findBySourceNode($node);
            foreach ($connections as $connection) {
                /** @var Connection $connection */
                $typeValue = 'A';
                if ($connection->getType() == Connection::TYPE_CODEGATE) {
                    if ($connection->getisOpen()) {
                        $typeValue = 'Y';
                    }
                    else {
                        $typeValue = 'E';
                    }
                }
                $mapArray['links'][] = [
                    'source' => (string)$connection->getSourceNode()->getId() . '_' .
                        $connection->getSourceNode()->getNodeType()->getShortName() . '_' .
                        $connection->getSourceNode()->getName(),
                    'target' => (string)$connection->getTargetNode()->getId() . '_' .
                        $connection->getTargetNode()->getNodeType()->getShortName() . '_' .
                        $connection->getTargetNode()->getName(),
                    'value' => 2,
                    'type' => $typeValue
                ];
            }
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/partials/map.phtml');
        $view->setVariable('json', json_encode($mapArray));
        return $this->viewRenderer->render($view);
    }

    /**
     * @param NpcInstance|Profile $target
     * @param null|int $resourceId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function checkNpcAggro($target, $resourceId = NULL)
    {
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $currentNode = ($target instanceof Profile) ? $target->getCurrentNode() : $target->getNode();
        $npcInstances = $npcInstanceRepo->findByNode($currentNode);
        foreach ($npcInstances as $npcInstance) {
            /** @var NpcInstance $npcInstance */
            if ($npcInstance === $target) continue;
            if (!$npcInstance->getAggressive()) continue;
            if ($this->isInCombat($npcInstance)) continue;
            if (!$this->canSee($npcInstance, $target)) continue;
            if ($target instanceof Profile) {
                if ($npcInstance->getProfile() === $target) continue;
                if ($target->getGroup() && $npcInstance->getGroup() == $target->getGroup()) continue;
                if ($target->getFaction() && $npcInstance->getFaction() == $target->getFaction()) continue;
                // set combatants
                $this->getWebsocketServer()->addCombatant($npcInstance, $target, NULL, $resourceId);
                if (!$this->isInCombat($target)) $this->getWebsocketServer()->addCombatant($target, $npcInstance, $resourceId);
                if ($resourceId && $this->isInAction($resourceId)) $this->cancelAction($resourceId);
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                    $npcInstance->getName(),
                    $target->getUser()->getUsername()
                );
                $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
            }
            if ($target instanceof NpcInstance) {
                if ($npcInstance->getProfile() === $target->getProfile()) continue;
                if ($target->getGroup() && $npcInstance->getGroup() == $target->getGroup()) continue;
                if ($target->getFaction() && $npcInstance->getFaction() == $target->getFaction()) continue;
                if ($target->getProfile() == NULL && $target->getFaction() == NULL && $target->getGroup() == NULL && $npcInstance->getProfile() == NULL && $npcInstance->getFaction() == NULL && $npcInstance->getGroup() == NULL) continue;
                // set combatants
                $this->getWebsocketServer()->addCombatant($npcInstance, $target);
                if (!$this->isInCombat($target)) $this->getWebsocketServer()->addCombatant($target, $npcInstance);
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                    $npcInstance->getName(),
                    $target->getName()
                );
                $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
            }
        }
    }

    /**
     * @param $resourceId
     * @param bool $messageSocket
     * @param bool $asActiveCommand
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function cancelAction($resourceId, $messageSocket = false, $asActiveCommand = false)
    {
        $ws = $this->getWebsocketServer();
        $ws->clearClientActionData($resourceId);
        $clientData = $ws->getClientData($resourceId);
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $clientData->profileId);
        if ($asActiveCommand) $ws->removeCombatant($profile, false);
        if ($messageSocket) {
            foreach ($ws->getClients() as $wsClient) {
                /** @noinspection PhpUndefinedFieldInspection */
                if ($wsClient->resourceId == $resourceId) {
                    $response = new GameClientResponse($resourceId);
                    $message = $this->translate('Your current action has been cancelled');
                    $response->addMessage($message, GameClientResponse::CLASS_ATTENTION);
                    $response->addOption(GameClientResponse::OPT_CLEARDEADLINE, true);
                    if ($asActiveCommand) {
                        return $response->send();
                    }
                    else {
                        return $response;
                    }
                    break;
                }
            }
        }
        return false;
    }

    /**
     * @param Profile $profile
     * @param Node|NULL $previousNode
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    private function checkMoveFileTriggers(Profile $profile, Node $previousNode = NULL)
    {
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $files = $fileRepo->findRunningInNode($profile->getCurrentNode());
        foreach ($files as $file) {
            /** @var File $file */
            switch ($file->getFileType()->getId()) {
                default:
                    break;
                case FileType::ID_BEARTRAP:
                    if ($profile === $currentSystem->getProfile()) continue;
                    if ($profile === $currentNode->getProfile()) continue;
                    if ($profile->getGroup() && $profile->getGroup() === $currentSystem->getGroup()) continue;
                    if ($profile->getFaction() && $profile->getFaction() === $currentSystem->getFaction()) continue;
                    if (!$this->canSee($file, $profile)) continue;
                    // beartrap damages profile
                    // TODO add stun effect
                    $damage = ceil(round($file->getIntegrity()/10));
                    $messageText = sprintf(
                        $this->translate('[%s] hits you for %s points of damage'),
                        $file->getName(),
                        $damage
                    );
                    $otherMessageText = sprintf(
                        $this->translate('[%s] hits [%s] for %s points of damage'),
                        $file->getName(),
                        $profile->getUser()->getUsername(),
                        $damage
                    );
                    $this->messageProfileNew($profile, $messageText, GameClientResponse::CLASS_DANGER);
                    $this->messageEveryoneInNodeNew($currentNode, $otherMessageText, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
                    $this->damageProfile($profile, $damage);
                    break;
                case FileType::ID_IO_TRACER:
                    // tracers will not work on their owner or stuff that they are unable to see
                    $fileProfile = $file->getProfile();
                    if ($profile === $fileProfile) continue;
                    if (!$this->canSee($file, $profile)) continue;
                    $system = $previousNode->getSystem();
                    if ($system == $file->getSystem()) continue;
                    $rating = ceil(round(($file->getLevel() + $this->getBonusForFileLevel($file))/2));
                    $difficulty = $this->getSkillRating($profile, Skill::ID_STEALTH);
                    // check for obfuscator
                    $obfuscators = $fileRepo->findRunningByProfileAndType($profile, FileType::ID_OBFUSCATOR);
                    foreach ($obfuscators as $obfuscator) {
                        $difficulty += $this->getBonusForFileLevel($obfuscator);
                        $this->lowerIntegrityOfFile($obfuscator, 50, 1, true);
                    }
                    // roll
                    if ($this->makePercentRollAgainstTarget($rating - $difficulty)) {
                        $messageText = sprintf(
                            $this->translate('[%s] has detected [%s] connecting from %s'),
                            $file->getName(),
                            $profile->getUser()->getUsername(),
                            $system->getAddy()
                        );
                        $this->storeNotification(
                            $fileProfile,
                            $messageText,
                            Notification::SEVERITY_INFO
                        );
                    }
                    $this->lowerIntegrityOfFile($file, 50, 1, true);
                    break;
            }
        }
    }

    /**
     * @param Profile $profile
     * @param int $damage
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function damageProfile(Profile $profile, $damage = 0)
    {
        $currentHealth = $profile->getEeg();
        $newHealth = $currentHealth - $damage;
        if ($newHealth < 1) {
            $this->flatlineProfile($profile);
        }
        else {
            $profile->setEeg($newHealth);
            $this->entityManager->flush($profile);
        }
    }

    /**
     * @param Profile $profile
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function flatlineProfile(Profile $profile)
    {
        $profile->setEeg(10);
        $profile->setSecurityRating(0);
        $this->entityManager->flush($profile);
        $currentNode = $profile->getCurrentNode();
        $homeNode = $profile->getHomeNode();
        $this->movePlayerToTargetNodeNew(NULL, $profile , NULL, $currentNode, $homeNode);
        $this->updateMap($profile->getCurrentResourceId(), $profile);
        if ($currentNode->getSystem() !== $homeNode->getSystem()) {
            $flytoResponse = new GameClientResponse($profile->getCurrentResourceId());
            $flytoResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
            $flytoResponse->addOption(GameClientResponse::OPT_CONTENT, explode(',',$homeNode->getSystem()->getGeocoords()));
            $flytoResponse->send();
        }
    }

    /**
     * Moves the profile to the node specified by the connection or the target-node.
     * If no connection is given then source- and target-node must be given. This also messages all profiles
     * in the source- and target-node. If no resourceId is given, this will move the profile but not
     * generate a response message for the moved profile. If a resourceId is given, you can specify if the generated
     * node-info will be sent silently (prepend) by setting the prepend property.
     * @param int|NULL $resourceId
     * @param Profile $profile
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function movePlayerToTargetNode(
        $resourceId = NULL,
        Profile $profile,
        Connection $connection = NULL,
        Node $sourceNode = NULL,
        Node $targetNode = NULL
    )
    {
        if ($connection) {
            $sourceNode = $connection->getSourceNode();
            $targetNode = $connection->getTargetNode();
        }
        $toString = ($connection) ? $targetNode->getName() : $this->translate('somewhere unknown');
        // message everyone in source node
        $messageText = sprintf(
            $this->translate('[%s] has used the connection to [%s]'),
            $profile->getUser()->getUsername(),
            $toString
        );
        $this->messageEveryoneInNodeNew($sourceNode, $messageText, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        $profile->setCurrentNode($targetNode);
        $fromString = ($connection) ? $sourceNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('%s has connected to this node from %s'),
            $profile->getUser()->getUsername(),
            $fromString
        );
        $this->messageEveryoneInNodeNew($targetNode, $messageText, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        $this->entityManager->flush($profile);
        $this->checkNpcAggro($profile, $resourceId); // TODO solve aggro in a different way
        $this->checkKnownNode($profile);
        $this->checkMoveFileTriggers($profile, $sourceNode);
        return ($resourceId) ? $this->showNodeInfoNew($resourceId, NULL, true) : false;
    }

    /**
     * @param $resourceId
     * @param Node|NULL $node
     * @param bool $sendNow
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showNodeInfoNew($resourceId, Node $node = NULL, $sendNow = false)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isAdmin = $this->hasRole(NULL, Role::ROLE_ID_ADMIN);
        $profile = $this->user->getProfile();
        $currentNode = ($node) ? $node : $profile->getCurrentNode();
        // add survey text if option is turned on
        if ($this->getProfileGameOption($profile, GameOption::ID_SURVEY)) {
            $this->gameClientResponse->addMessage($this->getSurveyText($currentNode), GameClientResponse::CLASS_MUTED);
        }
        // get connections and show them if there are any
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $connections = $connectionRepo->findBySourceNode($currentNode);
        if (count($connections) > 0) {
            $this->gameClientResponse->addMessage($this->translate('connections:'), GameClientResponse::CLASS_SURVEY);
        }
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $addonString = '';
            if ($connection->getType() == Connection::TYPE_CODEGATE) {
                $addonString = ($connection->getisOpen()) ?
                    $this->translate('<span class="text-muted">(codegate) (open)</span>') :
                    $this->translate('<span class="text-addon">(codegate) (closed)</span>');
            }
            $returnMessage = sprintf(
                '%-12s: <span class="contextmenu-connection" data-id="%s">%s</span> %s',
                $counter,
                $counter,
                $connection->getTargetNode()->getName(),
                $addonString
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SURVEY);
        }
        // get files and show them if there are any
        $files = [];
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        foreach ($fileRepo->findByNode($currentNode) as $fileInstance) {
            /** @var File $fileInstance */
            if (!$fileInstance->getFileType()->getStealthing()) {
                $files[] = $fileInstance;
            }
            else {
                if ($this->canSee($profile, $fileInstance)) $files[] = $fileInstance;
            }
        }
        if (count($files) > 0) {
            $this->gameClientResponse->addMessage($this->translate('files:'), GameClientResponse::CLASS_EXECUTABLE);
        }
        $counter = 0;
        foreach ($files as $file) {
            /** @var File $file */
            $counter++;
            $returnMessage = sprintf(
                '%-12s: <span class="contextmenu-file" data-id="%s">%s%s</span> %s',
                $counter,
                $file->getName(),
                $file->getName(),
                ($file->getIntegrity() < 1) ? $this->translate(' <span class="text-danger">(defunct)</span>') : '',
                ($isAdmin) ? sprintf($this->translate('<span class="text-addon">[%s]</span>'), $file->getId()) : ''
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_EXECUTABLE);
        }
        // get profiles and show them if there are any
        $profiles = [];
        foreach ($this->getWebsocketServer()->getClientsData() as $clientId => $xClientData) {
            $requestedProfile = $this->entityManager->find('Netrunners\Entity\Profile', $xClientData['profileId']);
            /** @var Profile $requestedProfile */
            if(
                $requestedProfile &&
                $requestedProfile !== $profile &&
                $requestedProfile->getCurrentNode() == $currentNode
            )
            {
                if (!$requestedProfile->getStealthing()) {
                    $profiles[] = $requestedProfile;
                }
                else {
                    if ($this->canSee($profile, $requestedProfile)) $profiles[] = $requestedProfile;
                }
            }
        }
        if (count($profiles) > 0) {
            $this->gameClientResponse->addMessage($this->translate('users:'), GameClientResponse::CLASS_USERS);
        }
        $counter = 0;
        foreach ($profiles as $pprofile) {
            /** @var Profile $pprofile */
            $counter++;
            $returnMessage = sprintf(
                '%-12s: %s %s %s %s %s',
                $counter,
                $pprofile->getUser()->getUsername(),
                ($pprofile->getStealthing()) ? $this->translate('<span class="text-info">[stealthing]</span>') : '',
                ($pprofile->getFaction()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $pprofile->getFaction()->getName()) : '',
                ($pprofile->getGroup()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $pprofile->getGroup()->getName()) : '',
                ($isAdmin) ? sprintf($this->translate('<span class="text-addon">[%s]</span>'), $pprofile->getUser()->getId()) : ''
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_USERS);
        }
        // get npcs and show them if there are any
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $npcInstances = $npcInstanceRepo->findByNode($currentNode);
        $npcs = [];
        foreach ($npcInstances as $npcInstance) {
            /** @var NpcInstance $npcInstance */
            if (!$npcInstance->getStealthing()) {
                $npcs[] = $npcInstance;
            }
            else {
                if ($this->canSee($profile, $npcInstance)) $npcs[] = $npcInstance;
            }
        }
        if (count($npcs) > 0) {
            $this->gameClientResponse->addMessage($this->translate('entities:'), GameClientResponse::CLASS_NPCS);
        }
        $counter = 0;
        foreach ($npcs as $npcInstance) {
            /** @var NpcInstance $npcInstance */
            $counter++;
            $returnMessage = sprintf(
                '%-12s: <span class="contextmenu-entity" data-id="%s">%s</span> %s %s %s %s %s',
                $counter,
                $counter,
                $npcInstance->getName(),
                ($npcInstance->getStealthing()) ? $this->translate('<span class="text-info">[stealthing]</span>') : '',
                ($npcInstance->getProfile()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $npcInstance->getProfile()->getUser()->getUsername()) : '',
                ($npcInstance->getFaction()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $npcInstance->getFaction()->getName()) : '',
                ($npcInstance->getGroup()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $npcInstance->getGroup()->getName()) : '',
                ($isAdmin) ? sprintf($this->translate('<span class="text-addon">[%s]</span>'), $npcInstance->getId()) : ''
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_NPCS);
        }
        // prepare and return response
        $this->gameClientResponse->addOption(GameClientResponse::OPT_MOVED, true);
        return ($sendNow) ? $this->gameClientResponse->send() : $this->gameClientResponse;
    }

    /**
     * @param $resourceId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function initService($resourceId)
    {
        $this->setClientData($resourceId);
        $this->setUser();
        $this->setProfileLocale();
        $this->setResponse(false);
        $this->initResponse($resourceId);
    }

    /**
     * @param $resourceId
     */
    private function setClientData($resourceId)
    {
        $this->clientData = $this->getWebsocketServer()->getClientData($resourceId);
    }

    /**
     * Sets the user from the client data.
     * @param User|NULL $user
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function setUser(User $user = NULL)
    {
        $this->user = ($user) ? $user : $this->entityManager->find('TmoAuth\Entity\User', $this->clientData->userId);
    }

    private function setProfileLocale()
    {
        if ($this->user && $this->user->getProfile()) $this->profileLocale = $this->user->getProfile()->getLocale();
    }

    /**
     * @param $response
     */
    protected function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @param $resourceId
     * @throws \Exception
     */
    private function initResponse($resourceId)
    {
        $this->gameClientResponse = new GameClientResponse($resourceId);
    }

    /**
     * @param Node $node
     * @return string
     */
    protected function getSurveyText(Node $node)
    {
        if (!$node->getDescription()) {
            $text = wordwrap(
                $this->translate('This is a raw Cyberspace node, white walls, white ceiling, white floor - no efforts have been made to customize it.'),
                120);
        }
        else {
            $text = sprintf(
                wordwrap($node->getDescription(), 120)
            );
        }
        return $text;
    }

    /**
     * @param NpcInstance $npc
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function moveNpcToTargetNode(
        NpcInstance $npc,
        Connection $connection = NULL,
        Node $sourceNode = NULL,
        Node $targetNode = NULL
    )
    {
        // set source- and target-node if a connection was given
        if ($connection) {
            $sourceNode = $connection->getSourceNode();
            $targetNode = $connection->getTargetNode();
        }
        // message everyone in source node
        $toString = ($connection) ? $targetNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('%s has used the connection to %s'),
            $npc->getName(),
            $toString
        );
        $this->messageEveryoneInNodeNew($sourceNode, $messageText, GameClientResponse::CLASS_MUTED, $npc, [], true);
        $npc->setNode($targetNode);
        $fromString = ($connection) ? $sourceNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('%s has connected to this node from %s'),
            $npc->getName(),
            $fromString
        );
        $this->messageEveryoneInNodeNew($targetNode, $messageText, GameClientResponse::CLASS_MUTED, $npc, [], true);
        $this->checkNpcAggro($npc);
        $this->checkAggro($npc);
        if (!$this->isInCombat($npc)) $this->checkNpcTriggers($npc);
        $this->entityManager->flush($npc);
        return true;
    }

    /**
     * @param NpcInstance $actor
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function checkAggro(NpcInstance $actor)
    {
        if ($actor->getAggressive() && !$this->isInCombat($actor)) {
            $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
            /** @var NpcInstanceRepository $npcInstanceRepo */
            $currentNode = $actor->getNode();
            $npcInstances = $npcInstanceRepo->findByNode($currentNode);
            foreach ($npcInstances as $npcInstance) {
                /** @var NpcInstance $npcInstance */
                if ($npcInstance === $actor) continue;
                if (!$this->canSee($actor, $npcInstance)) continue;
                if ($npcInstance->getProfile() === $actor->getProfile()) continue;
                if ($actor->getGroup() && $npcInstance->getGroup() == $actor->getGroup()) continue;
                if ($actor->getFaction() && $npcInstance->getFaction() == $actor->getFaction()) continue;
                if ($actor->getProfile() == NULL && $actor->getFaction() == NULL && $actor->getGroup() == NULL && $npcInstance->getProfile() == NULL && $npcInstance->getFaction() == NULL && $npcInstance->getGroup() == NULL) continue;
                // set combatants
                $this->getWebsocketServer()->addCombatant($actor, $npcInstance);
                if (!$this->isInCombat($npcInstance)) $this->getWebsocketServer()->addCombatant($npcInstance, $actor);
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                    $actor->getName(),
                    $npcInstance->getName()
                );
                $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
                break;
            }
            if (!$this->isInCombat($actor)) {
                $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
                /** @var ProfileRepository $profileRepo */
                $profiles = $profileRepo->findByCurrentNode($currentNode, NULL, true);
                foreach ($profiles as $profile) {
                    /** @var Profile $profile */
                    if ($profile->getCurrentResourceId()) {
                        if ($actor->getProfile() === $profile) continue;
                        if ($profile->getGroup() && $actor->getGroup() == $profile->getGroup()) continue;
                        if ($profile->getFaction() && $actor->getFaction() == $profile->getFaction()) continue;
                        // set combatants
                        $this->getWebsocketServer()->addCombatant($actor, $profile, NULL, $profile->getCurrentResourceId());
                        if (!$this->isInCombat($profile)) $this->getWebsocketServer()->addCombatant($profile, $actor, $profile->getCurrentResourceId());
                        // inform other players in node
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                            $actor->getName(),
                            $profile->getUser()->getUsername()
                        );
                        $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @param NpcInstance $npc
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function checkNpcTriggers(NpcInstance $npc)
    {
        switch ($npc->getNpc()->getId()) {
            default:
                break;
            case Npc::ID_WORKER_PROGRAM:
                $this->checkWorkerTriggers($npc);
                break;
            case Npc::ID_DEBUGGER_PROGRAM:
                $this->checkDebuggerTriggers($npc);
                break;
            case Npc::ID_SCANNER_PROGRAM:
                $this->checkScannerTriggers($npc);
                break;
        }
    }

    /**
     * @param NpcInstance $npc
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function checkWorkerTriggers(NpcInstance $npc)
    {
        $currentNode = $npc->getNode();
        switch ($currentNode->getNodeType()->getId()) {
            default:
                break;
            case NodeType::ID_DATABASE:
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $miners = $fileRepo->findOneForHarvesting($npc, FileType::ID_DATAMINER);
                $highestAmount = 0;
                $miner = NULL;
                $minerData = NULL;
                foreach ($miners as $xMiner) {
                    /** @var File $xMiner */
                    $xMinerData = json_decode($xMiner->getData());
                    if (isset($xMinerData->value)) {
                        if ($xMinerData->value > $highestAmount) {
                            $highestAmount = $xMinerData->value;
                            $miner = $xMiner;
                            $minerData = $xMinerData;
                        }
                    }
                }
                if ($miner && $minerData) {
                    $availableAmount = $minerData->value;
                    $amount = ($npc->getLevel() > $availableAmount) ? $availableAmount : $npc->getLevel();
                    $minerData->value -= $amount;
                    $npc->setSnippets($npc->getSnippets()+$amount);
                    $miner->setData(json_encode($minerData));
                    $this->entityManager->flush($miner);
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has collected some snippets from [%s]</pre>'),
                        $npc->getName(),
                        $miner->getName()
                    );
                    $this->messageEveryoneInNode($currentNode, $message);
                }
                break;
            case NodeType::ID_TERMINAL:
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $miners = $fileRepo->findOneForHarvesting($npc, FileType::ID_COINMINER);
                $highestAmount = 0;
                $miner = NULL;
                $minerData = NULL;
                foreach ($miners as $xMiner) {
                    /** @var File $xMiner */
                    $xMinerData = json_decode($xMiner->getData());
                    if (isset($xMinerData->value)) {
                        if ($xMinerData->value > $highestAmount) {
                            $highestAmount = $xMinerData->value;
                            $miner = $xMiner;
                            $minerData = $xMinerData;
                        }
                    }
                }
                if ($miner && $minerData) {
                    $availableAmount = $minerData->value;
                    $amount = ($npc->getLevel() > $availableAmount) ? $availableAmount : $npc->getLevel();
                    $minerData->value -= $amount;
                    $npc->setCredits($npc->getCredits()+$amount);
                    $miner->setData(json_encode($minerData));
                    $this->entityManager->flush($miner);
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has collected some credits from [%s]</pre>'),
                        $npc->getName(),
                        $miner->getName()
                    );
                    $this->messageEveryoneInNode($currentNode, $message);
                }
                break;
            case NodeType::ID_BANK:
                $npcProfile = $npc->getProfile();
                if ($npcProfile) {
                    $flush = false;
                    if ($npc->getCredits() >= 1) {
                        $npcProfile->setBankBalance($npcProfile->getBankBalance() + $npc->getCredits());
                        $npc->setCredits(0);
                        $flush = true;
                    }
                    if ($npc->getSnippets() >= 1) {
                        $npcProfile->setSnippets($npcProfile->getSnippets() + $npc->getSnippets());
                        $npc->setSnippets(0);
                        $flush = true;
                    }
                    if ($flush) $this->entityManager->flush($npcProfile);
                }
                $npcGroup = $npc->getGroup();
                if ($npcGroup) {
                    /** @var Group $npcGroup */
                    $flush = false;
                    if ($npc->getCredits() >= 1) {
                        $npcGroup->setCredits($npcGroup->getCredits() + $npc->getCredits());
                        $npc->setCredits(0);
                        $flush = true;
                    }
                    if ($npc->getSnippets() >= 1) {
                        $npcGroup->setSnippets($npcGroup->getSnippets() + $npc->getSnippets());
                        $npc->setSnippets(0);
                        $flush = true;
                    }
                    if ($flush) $this->entityManager->flush($npcGroup);
                }
                $npcFaction = $npc->getFaction();
                if ($npcFaction) {
                    /** @var Faction $npcFaction */
                    $flush = false;
                    if ($npc->getCredits() >= 1) {
                        $npcFaction->setCredits($npcFaction->getCredits() + $npc->getCredits());
                        $npc->setCredits(0);
                        $flush = true;
                    }
                    if ($npc->getSnippets() >= 1) {
                        $npcFaction->setSnippets($npcFaction->getSnippets() + $npc->getSnippets());
                        $npc->setSnippets(0);
                        $flush = true;
                    }
                    if ($flush) $this->entityManager->flush($npcFaction);
                }
                break;
        }
    }

    /**
     * @param NpcInstance $npc
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function checkDebuggerTriggers(NpcInstance $npc)
    {
        $currentNode = $npc->getNode();
        switch ($currentNode->getNodeType()->getId()) {
            default:
                break;
            case NodeType::ID_DATABASE:
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $miners = $fileRepo->findOneForRepair($npc, FileType::ID_DATAMINER);
                $lowestAmount = 100;
                $miner = NULL;
                foreach ($miners as $xMiner) {
                    /** @var File $xMiner */
                    if ($xMiner->getIntegrity() < $xMiner->getMaxIntegrity() && $xMiner->getIntegrity() < $lowestAmount) {
                        $lowestAmount = $xMiner->getIntegrity();
                        $miner = $xMiner;
                    }
                }
                if ($miner) {
                    $neededRepairAmount = $miner->getMaxIntegrity() - $miner->getIntegrity();
                    $amount = ($npc->getLevel() > $neededRepairAmount) ? $neededRepairAmount : $npc->getLevel();
                    $amount = ($npc->getCurrentEeg() > $amount) ? $amount : $npc->getCurrentEeg();
                    $miner->setIntegrity($miner->getIntegrity()+$amount);
                    if ($amount >= $npc->getCurrentEeg()) {
                        $this->entityManager->remove($npc);
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has repaired some integrity on [%s] and then despawns</pre>'),
                            $npc->getName(),
                            $miner->getName()
                        );
                    }
                    else {
                        $npc->setCurrentEeg($npc->getCurrentEeg()-$amount);
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has repaired some integrity on [%s]</pre>'),
                            $npc->getName(),
                            $miner->getName()
                        );
                    }
                    $this->entityManager->flush($miner);
                    $this->messageEveryoneInNode($currentNode, $message);
                }
                break;
            case NodeType::ID_TERMINAL:
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $miners = $fileRepo->findOneForRepair($npc, FileType::ID_COINMINER);
                $lowestAmount = 100;
                $miner = NULL;
                foreach ($miners as $xMiner) {
                    /** @var File $xMiner */
                    if ($xMiner->getIntegrity() < $xMiner->getMaxIntegrity() && $xMiner->getIntegrity() < $lowestAmount) {
                        $lowestAmount = $xMiner->getIntegrity();
                        $miner = $xMiner;
                    }
                }
                if ($miner) {
                    $neededRepairAmount = $miner->getMaxIntegrity() - $miner->getIntegrity();
                    $amount = ($npc->getLevel() > $neededRepairAmount) ? $neededRepairAmount : $npc->getLevel();
                    $amount = ($npc->getCurrentEeg() > $amount) ? $amount : $npc->getCurrentEeg();
                    $miner->setIntegrity($miner->getIntegrity()+$amount);
                    if ($amount >= $npc->getCurrentEeg()) {
                        $this->getWebsocketServer()->log(Logger::ALERT, 'debugger despawning');
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has repaired some integrity on [%s] and then despawns</pre>'),
                            $npc->getName(),
                            $miner->getName()
                        );
                        $this->entityManager->remove($npc);
                    }
                    else {
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has repaired some integrity on [%s]</pre>'),
                            $npc->getName(),
                            $miner->getName()
                        );
                        $npc->setCurrentEeg($npc->getCurrentEeg()-$amount);
                    }
                    $this->entityManager->flush($miner);
                    $this->messageEveryoneInNode($currentNode, $message);
                }
                break;
        }
    }

    /**
     * @param NpcInstance $npc
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function checkScannerTriggers(NpcInstance $npc)
    {
        $profile = $npc->getProfile();
        $newNode = $npc->getNode();
        $this->addKnownNode($profile, $newNode);
        /** @var ConnectionRepository $connectionRepo */
        $connectionRepo = $this->entityManager->getRepository(Connection::class);
        $connections = $connectionRepo->findBySourceNode($newNode);
        /** @var Connection $connection */
        foreach ($connections as $connection) {
            $this->addKnownNode($profile, $connection->getTargetNode());
        }
        $currentResourceId = $profile->getCurrentResourceId();
        // only update client map if they are in the same system
        if ($currentResourceId && $profile->getCurrentNode()->getSystem() === $newNode->getSystem()) {
            $this->updateMap($currentResourceId, $profile);
        }
    }

    /**
     * Checks if the player is blocked from performing another action.
     * Returns true if the action is blocked, false if it is not blocked.
     * @param $resourceId
     * @param bool $checkForFullBlock
     * @param File|NULL $file
     * @return bool|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function isActionBlockedNew($resourceId, $checkForFullBlock = false, File $file = NULL)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $isBlocked = false;
        $message = $this->translate('You are currently busy with something else');
        /* combat block check follows - combat never fully blocks */
        if (!$checkForFullBlock) {
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            $profile = $user->getProfile();
            $isBlocked = $this->isInCombat($profile);
            $fileUnblock = false;
            if ($isBlocked) {
                // if a file was given, we check if it a combat file and unblock if needed
                if ($file) {
                    $unblockingFileTypeIds = [FileType::ID_KICKER];
                    $isBlocked = (in_array($file->getFileType()->getId(), $unblockingFileTypeIds)) ? false : true;
                    // set fileunblock tracker to true if this unblocked them
                    if (!$isBlocked) $fileUnblock = true;
                }
                if ($isBlocked) {
                    $message = $this->translate('You are currently busy fighting');
                }
            }
            // now check if they are under effects - like stunned - and only if they werent unblocked by the current file type
            if (!$isBlocked && !$fileUnblock && $this->isUnderEffect($profile, Effect::ID_STUNNED)) {
                $isBlocked = true;
                $message = $this->translate('You are currently stunned');
            }
        }
        /* action block check follows */
        if (!empty($clientData->action) && !$isBlocked) {
            $actionData = (object)$clientData->action;
            $isBlocked = false;
            if ($checkForFullBlock) {
                if ($actionData->fullblock) $isBlocked = true;
            }
            if (!$isBlocked) {
                if ($actionData->blocking) $isBlocked = true;
            }
        }
        if ($isBlocked) {
            return $message;
        }
        return $isBlocked;
    }

    /**
     * Returns the total rating for the given profile and faction.
     * @param Profile $profile
     * @param Faction $faction
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getProfileFactionRating(Profile $profile, Faction $faction)
    {
        $profileFactionRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFactionRating');
        /** @var ProfileFactionRatingRepository $profileFactionRatingRepo */
        return $profileFactionRatingRepo->getProfileFactionRating($profile, $faction);
    }

    /**
     * @param string $string
     * @param int $maxLength
     * @param int $minLength
     * @return bool|string
     */
    protected function stringChecker($string = '', $maxLength = 32, $minLength = 1)
    {
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$validator->isValid($string)) {
            return $this->translate('Invalid string (alpha-numeric only)');
        }
        // check for max characters
        if (mb_strlen($string) > $maxLength) {
            return sprintf(
                $this->translate('Invalid string (%s-characters-max)'),
                $maxLength
            );
        }
        // check for min characters
        if (mb_strlen($string) < $minLength) {
            return sprintf(
                $this->translate('Invalid string (%s-characters-min)'),
                $minLength
            );
        }
        return false;
    }

    /**
     * @param File $file
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function executeMissionFile(File $file)
    {
        $profile = $this->user->getProfile();
        $missionRepo = $this->entityManager->getRepository('Netrunners\Entity\Mission');
        /** @var MissionRepository $missionRepo */
        $mission = $missionRepo->findByTargetFile($file);
        if (!$mission) {
            $message = sprintf(
                $this->translate('[%s] can not be executed'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message);
        }
        /** @var Mission $mission */
        switch ($mission->getMission()->getId()) {
            default:
                $message = sprintf(
                    $this->translate('[%s] can not be executed'),
                    $file->getName()
                );
                return $this->gameClientResponse->addMessage($message);
            case MissionArchetype::ID_PLANT_BACKDOOR:
                if ($mission->getProfile() !== $profile) {
                    $message = sprintf(
                        $this->translate('[%s] can not be executed'),
                        $file->getName()
                    );
                    return $this->gameClientResponse->addMessage($message);
                }
                if ($mission->getTargetNode() != $profile->getCurrentNode()) {
                    $message = sprintf(
                        $this->translate('[%s] does not seem to have any effect in this node'),
                        $file->getName()
                    );
                    return $this->gameClientResponse->addMessage($message);
                }
                break;
            case MissionArchetype::ID_UPLOAD_FILE:
                if ($mission->getTargetNode() != $profile->getCurrentNode()) {
                    $message = sprintf(
                        $this->translate('[%s] does not seem to have any effect in this node'),
                        $file->getName()
                    );
                    return $this->gameClientResponse->addMessage($message);
                }
                break;
            case MissionArchetype::ID_STEAL_FILE:
            case MissionArchetype::ID_DELETE_FILE:
                if ($mission->getProfile() !== $profile) {
                    $message = sprintf(
                        $this->translate('[%s] can not be interacted with'),
                        $file->getName()
                    );
                    return $this->gameClientResponse->addMessage($message);
                }
                break;
        }
        $result = $this->completeMission();
        if ($result instanceof GameClientResponse) {
            $result->send();
        }
        return false;
    }

    /**
     * @param Profile|null $profile
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function completeMission(Profile $profile = null)
    {
        if (!$profile) $profile = $this->user->getProfile();
        $mission = $profile->getCurrentMission();
        if ($mission) {
            $targetFile = $mission->getTargetFile();
            if ($targetFile) {
                $mission->setTargetFile(null);
                $this->entityManager->flush($mission);
                $this->entityManager->remove($targetFile);
            }
            $mission->setCompleted(new \DateTime());
            $level = $mission->getLevel();
            $reward = $level * MissionService::CREDITS_MULTIPLIER;
            $profile->setCredits($profile->getCredits()+$reward);
            $profile->setCompletedMissions($profile->getCompletedMissions()+1);
            $this->createProfileFactionRating(
                $profile,
                NULL,
                $mission,
                NULL,
                ProfileFactionRating::SOURCE_ID_MISSION,
                $level,
                $level * -1,
                $mission->getSourceFaction(),
                $mission->getTargetFaction()
            );
            $profile->setCurrentMission(null);
            $this->getWebsocketServer()
                ->setClientData($profile->getCurrentResourceId(), 'currentMission', null);
            $this->entityManager->flush();
            $message = sprintf(
                $this->translate('Mission accomplished - you received %s credits'),
                $reward
            );
            return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        }
        return false;
    }

    /**
     * @param NpcInstance $npcInstance
     * @param NpcInstance|Profile $attacker
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function flatlineNpcInstance(NpcInstance $npcInstance, $attacker)
    {
        /** @var FileRepository $fileRepo */
        foreach ($npcInstance->getFiles() as $file) {
            /** @var File $file */
            $npcInstance->removeFile($file);
            $file->setRunning(false);
            $file->setNode($npcInstance->getNode());
            $file->setSystem($npcInstance->getNode()->getSystem());
        }
        // check if we need to set spawner amount
        $spawner = $npcInstance->getSpawner();
        if ($spawner) {
            $fileData = $this->getFileData($spawner);
            $fileData->npcid = 0;
            $spawner->setData(json_encode($fileData));
        }
        $npcInstance->setBlasterModule(NULL);
        $npcInstance->setBladeModule(NULL);
        $npcInstance->setShieldModule(NULL);
        $npcInstance->setSpawner(NULL);
        $this->entityManager->flush();
        // give snippets and credits to attacker
        $attacker->setSnippets($attacker->getSnippets()+$npcInstance->getSnippets());
        $attacker->setCredits($attacker->getCredits()+$npcInstance->getCredits());
        // check for combat mission targets
        if ($attacker instanceof Profile) {
            $currentMission = $attacker->getCurrentMission();
            if ($currentMission && in_array($currentMission->getMission()->getId(), MissionService::$combatMissions)) {
                $missionData = json_decode($currentMission->getData());
                if (isset($missionData->amount)) {
                    $missionData->amount -= 1;
                    if ($missionData->amount < 1) {
                        $result = $this->completeMission($attacker);
                        if ($result instanceof GameClientResponse) {
                            $result->send();
                        }
                    }
                    else {
                        $currentMission->setData(json_encode($missionData));
                        $this->entityManager->flush($currentMission);
                    }
                }
            }
        }
        // remove the npc instance
        $npcType = $npcInstance->getNpc();
        if ($npcType->getType() == Npc::TYPE_HELPER) {
            $this->systemIntegrityChange($npcInstance->getSystem(), -1);
        }
        if ($npcType->getType() == Npc::TYPE_VIRUS) {
            $this->systemIntegrityChange($npcInstance->getSystem(), 1);
        }
        $this->entityManager->remove($npcInstance);
        $this->entityManager->flush($npcInstance);
    }

    /**
     * @param System $system
     * @param $message
     * @param string $textClass
     * @param array $ignoredProfileIds
     * @param bool $updateMap
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function messageEveryoneInSystem(
        System $system,
        $message,
        $textClass = GameClientResponse::CLASS_MUTED,
        $ignoredProfileIds = [],
        $updateMap = false
    )
    {
        /** @var ProfileRepository $profileRepo */
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        $profiles = $profileRepo->findByCurrentSystem($system);
        $response = new GameClientResponse(NULL, GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $response->addMessage($message, $textClass);
        foreach ($profiles as $xprofile) {
            /** @var Profile $xprofile */
            if (!is_array($ignoredProfileIds)) $ignoredProfileIds = [$ignoredProfileIds];
            if (in_array($xprofile->getId(), $ignoredProfileIds)) continue;
            if (!$xprofile->getCurrentResourceId()) continue;
            if ($updateMap) $this->updateMap($xprofile->getCurrentResourceId(), $xprofile);
            $response->setResourceId($xprofile->getCurrentResourceId())->send();
        }
    }

    /**
     * @param File $file
     * @param array $data
     * @return bool|float|mixed
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function checkFileTriggers(File $file, $data = [])
    {
        $profile = $this->user->getProfile();
        $fileType = $file->getFileType();
        switch ($fileType->getId()) {
            default:
                break;
            case FileType::ID_SKIMMER:
                if ($file->getProfile() == $profile) return false;
                if (!$this->canSee($file, $profile)) return false;
                $fileNode = $file->getNode();
                if (!$fileNode) return false;
                $depositAmount = $data['value'];
                if ($depositAmount < 100) return false;
                $rating = $this->getBonusForFileLevel($file);
                $difficulty = $fileNode->getLevel() * 10;
                // get blockchainers and add to difficulty
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $blockchainers = $fileRepo->findRunningInNodeByType($fileNode, FileType::ID_BLOCKCHAINER);
                foreach ($blockchainers as $blockchainer) {
                    /** @var File $blockchainer */
                    if (!$this->canSee($blockchainer, $file)) continue;
                    $difficulty += $this->getBonusForFileLevel($blockchainer);
                }
                // roll the dice
                if ($this->makePercentRollAgainstTarget($rating - $difficulty)) {
                    // success
                    $skimAmount = ceil(round($depositAmount/100));
                    $fileProfile = $file->getProfile();
                    $fileProfile->setBankBalance($fileProfile->getBankBalance()+$skimAmount);
                    $this->entityManager->flush($fileProfile);
                    $this->lowerIntegrityOfFile($file, 100, 1, true);
                    return $skimAmount;
                }
                break;
        }
        return false;
    }

    /**
     * @param Profile|NULL $profile
     * @param NpcInstance|NULL $npc
     * @param Profile|NpcInstance|null $actor
     * @param int|null $effectId
     * @param int|null $duration
     * @param mixed|null $rating
     * @return array|bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function addEffect(
        Profile $profile = NULL,
        NpcInstance $npc = NULL,
        $actor = NULL,
        $effectId = NULL,
        $duration = NULL,
        $rating = NULL
    )
    {
        $actorMessage = false;
        $profileMessage = false;
        if (!$effectId) return [$actorMessage, $profileMessage];
        $effect = $this->entityManager->find('Netrunners\Entity\Effect', $effectId);
        if ($effect) {
            /** @var Effect $effect */
            $now = new \DateTime();
            $peRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileEffect');
            /** @var ProfileEffectRepository $peRepo */
            $effectInstance = NULL;
            if ($profile) $effectInstance = $peRepo->findOneByProfileAndEffect($profile, $effectId);
            if ($npc) $effectInstance = $peRepo->findOneByNpcAndEffect($npc, $effectId);
            // now we can start the effect logic
            $immune = false;
            $diminishing = false;
            // if we could find an instance, we need to determine if there are diminishing-returns or immunity
            if ($effectInstance) {
                /** @var ProfileEffect $effectInstance */
                if ($effectInstance->getExpires() > $now) {
                    if ($actor instanceof Profile) {
                        if ($actor == $profile) {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">You are still under the effect of [%s]</span>'),
                                $effect->getName()
                            );
                        }
                        else {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">[%s] is still under the effect of [%s]</span>'),
                                ($profile) ? $profile->getUser()->getUsername() : $npc->getName(),
                                $effect->getName()
                            );
                        }
                    }
                    $immune = true;
                }
                if (!$immune && $effectInstance->getDimishUntil() > $now) {
                    if ($actor instanceof Profile) {
                        if ($actor == $profile) {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">You have recently been under the effect of [%s] - receiving dimishing returns</span>'),
                                $effect->getName()
                            );
                        }
                        else {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">[%s] has recently been under the effect of [%s] - receiving dimishing returns</span>'),
                                ($profile) ? $profile->getUser()->getUsername() : $npc->getName(),
                                $effect->getName()
                            );
                        }
                    }
                    $diminishing = true;
                }
                if (!$immune && !$diminishing && $effectInstance->getImmuneUntil() > $now) {
                    if ($actor instanceof Profile) {
                        if ($actor == $profile) {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">You have recently been under the effect of [%s] and are currently immune</span>'),
                                $effect->getName()
                            );
                        }
                        else {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">[%s] has recently been under the effect of [%s] and is currently immune</span>'),
                                ($profile) ? $profile->getUser()->getUsername() : $npc->getName(),
                                $effect->getName()
                            );
                        }
                    }
                    $immune = true;
                }
                if (!$immune) {
                    if ($diminishing) {
                        if (!$rating && $effect->getDefaultRating()) {
                            $rating = $effect->getDefaultRating() / $effect->getDiminishValue();
                        }
                        else {
                            $rating = $rating / $effect->getDiminishValue();
                        }
                        $rating = ceil(round($rating));
                        $completionDate = $effectInstance->getDimishUntil();
                        $dimDate = $effectInstance->getDimishUntil();
                        if ($effect->getImmuneTimer()) {
                            if ($effectInstance->getImmuneUntil()) {
                                $immDate = $effectInstance->getImmuneUntil();
                            }
                            else {
                                $immDate = new \DateTime();
                                $immSeconds = $effect->getImmuneTimer();
                                $immDate->add(new \DateInterval('PT' . $immSeconds . 'S'));
                            }
                        }
                        else {
                            $immDate = NULL;
                        }
                        if ($profile) {
                            $profileMessage = sprintf(
                                $this->translate('<span class="text-attention">You are now under the effect of [%s] with diminishing returns</span>'),
                                $effect->getName()
                            );
                        }
                    }
                    else {
                        $completionDate = new \DateTime();
                        $completionSeconds = ($duration) ? $duration : $effect->getExpireTimer();
                        $completionDate->add(new \DateInterval('PT' . $completionSeconds . 'S'));
                        if ($effect->getDimishTimer()) {
                            $dimDate = new \DateTime();
                            $dimSeconds = $effect->getDimishTimer();
                            $dimDate->add(new \DateInterval('PT' . $dimSeconds . 'S'));
                        }
                        else {
                            $dimDate = NULL;
                        }
                        if ($effect->getImmuneTimer()) {
                            $immDate = new \DateTime();
                            $immSeconds = $effect->getImmuneTimer();
                            $immDate->add(new \DateInterval('PT' . $immSeconds . 'S'));
                        }
                        else {
                            $immDate = NULL;
                        }
                        if ($profile) {
                            $profileMessage = sprintf(
                                $this->translate('<span class="text-attention">You are now under the effect of [%s]</span>'),
                                $effect->getName()
                            );
                        }
                        if ($actor instanceof Profile) {
                            if ($actor == $profile) {
                                $actorMessage = sprintf(
                                    $this->translate('<span class="text-attention">You are now under the effect of [%s]</span>'),
                                    $effect->getName()
                                );
                            }
                            else {
                                $actorMessage = sprintf(
                                    $this->translate('<span class="text-attention">[%s] is now under the effect of [%s]</span>'),
                                    ($profile) ? $profile->getUser()->getUsername() : $npc->getName(),
                                    $effect->getName()
                                );
                            }
                        }
                    }
                    $effectInstance->setProfile($profile);
                    $effectInstance->setNpcInstance($npc);
                    $effectInstance->setEffect($effect);
                    $effectInstance->setRating(($rating) ? $rating : $effect->getDefaultRating());
                    $effectInstance->setExpires($completionDate);
                    $effectInstance->setDimishUntil($dimDate);
                    $effectInstance->setImmuneUntil($immDate);
                }
                else {
                    if ($actor instanceof Profile) {
                        if ($actor == $profile) {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">You are currently immune to [%s]</span>'),
                                $effect->getName()
                            );
                        }
                        else {
                            $actorMessage = sprintf(
                                $this->translate('<span class="text-attention">[%s] is currently immune to [%s]</span>'),
                                ($profile) ? $profile->getUser()->getUsername() : $npc->getName(),
                                $effect->getName()
                            );
                        }
                    }
                }
            }
            else {
                $completionDate = new \DateTime();
                $completionSeconds = ($duration) ? $duration : $effect->getExpireTimer();
                $completionDate->add(new \DateInterval('PT' . $completionSeconds . 'S'));
                if ($effect->getDimishTimer()) {
                    $dimDate = new \DateTime();
                    $dimSeconds = $effect->getDimishTimer();
                    $dimDate->add(new \DateInterval('PT' . $dimSeconds . 'S'));
                }
                else {
                    $dimDate = NULL;
                }
                if ($effect->getImmuneTimer()) {
                    $immDate = new \DateTime();
                    $immSeconds = $effect->getImmuneTimer();
                    $immDate->add(new \DateInterval('PT' . $immSeconds . 'S'));
                }
                else {
                    $immDate = NULL;
                }
                $effectInstance = new ProfileEffect();
                $effectInstance->setProfile($profile);
                $effectInstance->setNpcInstance($npc);
                $effectInstance->setEffect($effect);
                $effectInstance->setRating(($rating) ? $rating : $effect->getDefaultRating());
                $effectInstance->setExpires($completionDate);
                $effectInstance->setDimishUntil($dimDate);
                $effectInstance->setImmuneUntil($immDate);
                $this->entityManager->persist($effectInstance);
                if ($actor instanceof Profile) {
                    if ($actor == $profile) {
                        $actorMessage = sprintf(
                            $this->translate('<span class="text-attention">You are now under the effect of [%s]</span>'),
                            $effect->getName()
                        );
                    }
                    else {
                        $actorMessage = sprintf(
                            $this->translate('<span class="text-attention">[%s] is now under the effect of [%s]</span>'),
                            ($profile) ? $profile->getUser()->getUsername() : $npc->getName(),
                            $effect->getName()
                        );
                    }
                }
                if ($profile) {
                    $profileMessage = sprintf(
                        $this->translate('<span class="text-attention">You are now under the effect of [%s]</span>'),
                        $effect->getName()
                    );
                }
            }
            $this->entityManager->flush($effectInstance);
        }
        return [$actorMessage, $profileMessage];
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
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentNodeType = $currentNode->getNodeType();
        // check if they are in an io-node
        if ($currentNodeType->getId() != NodeType::ID_PUBLICIO &&
            $currentNodeType->getId() != NodeType::ID_IO
        ) {
            return $this->gameClientResponse->addMessage(
                $this->translate('You must be in an I/O node to connect to another system')
            )->send();
        }
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        /** @var NodeRepository $nodeRepo */
        $nodeRepo = $this->entityManager->getRepository(Node::class);
        if (!$parameter) {
            $publicIoNodes = $nodeRepo->findForConnectCommand($profile);
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
        /** @var SystemRepository $systemRepo */
        $systemRepo = $this->entityManager->getRepository(System::class);
        /** @var System $targetSystem */
        $targetSystem = $systemRepo->findByAddy($addy);
        if (!$targetSystem) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid system address'))->send();
        }
        $targetNode = NULL;
        // now check if the node id exists
        $targetNodeId = $this->getNextParameter($contentArray, false, true);
        if (!$targetNodeId) {
            $publicIoNodes = $nodeRepo->findForConnectCommand($profile);
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
        /** @var Node $targetNode */
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
        if (!$targetNode) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid target node id'))->send();
        }
        $targetNodeType = $targetNode->getNodeType();
        if ($targetNodeType->getId() != NodeType::ID_PUBLICIO &&
            $targetNodeType->getId() != NodeType::ID_IO) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid node id'))->send();
        }
        if (
            $targetNodeType->getId() == NodeType::ID_IO &&
            !$this->canAccess($profile, $targetSystem, true, SystemRole::ALLOWED_CONNECT)
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
            GameClientResponse::OPT_CONTENT, explode(',', $targetSystem->getGeocoords())
        );
        $flytoResponse->send();
        $this->gameClientResponse->setSilent(true)->send();
        return $this->showNodeInfoNew($resourceId, NULL, true);
    }

}
