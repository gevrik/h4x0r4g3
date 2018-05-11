<?php

/**
 * GroupService.
 * This service resolves logic around the player groups (guilds).
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Group;
use Netrunners\Entity\GroupRole;
use Netrunners\Entity\GroupRoleInstance;
use Netrunners\Entity\Mission;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\GroupRepository;
use Netrunners\Repository\GroupRoleInstanceRepository;
use Netrunners\Repository\GroupRoleRepository;
use Netrunners\Repository\MissionRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

final class GroupService extends BaseService
{

    const GROUP_CREATION_COST = 100000;

    /**
     * @var GroupRepository
     */
    protected $groupRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;

    /**
     * @var SystemRepository
     */
    protected $systemRepo;

    /**
     * @var GroupRoleInstanceRepository
     */
    protected $groupRoleInstanceRepo;

    /**
     * @var GroupRoleRepository
     */
    protected $groupRoleRepo;

    /**
     * @var MissionRepository
     */
    protected $missionRepo;

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * GroupService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->groupRepo = $this->entityManager->getRepository(Group::class);
        $this->profileRepo = $this->entityManager->getRepository(Profile::class);
        $this->systemRepo = $this->entityManager->getRepository(System::class);
        $this->groupRoleInstanceRepo = $this->entityManager->getRepository(GroupRoleInstance::class);
        $this->groupRoleRepo = $this->entityManager->getRepository(GroupRole::class);
        $this->missionRepo = $this->entityManager->getRepository(Mission::class);
        $this->npcInstanceRepo = $this->entityManager->getRepository(NpcInstance::class);
        $this->fileRepo = $this->entityManager->getRepository(File::class);
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
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $this->getWebsocketServer()->setConfirm($resourceId, $command, $contentArray);
        switch ($command) {
            default:
                break;
            case 'creategroup':
                $checkResult = $this->createGroupChecks($profile, $currentSystem);
                if ($checkResult instanceof GameClientResponse) {
                    return $checkResult->send();
                }
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                $message = sprintf(
                    $this->translate('Are you sure that you want to create a group for %s credits - please confirm this action:'),
                    $this->numberFormat(self::GROUP_CREATION_COST)
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
            case 'leavegroup':
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                $message = $this->translate('Are you sure that you want to leave your group - please confirm this action:');
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
            case 'groupdisband':
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                $message = $this->translate('Are you sure that you want to disband your group - please confirm this action:');
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
            case 'joingroup':
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                $message = sprintf(
                    $this->translate('Are you sure that you want to join %s - please confirm this action:'),
                    $this->numberFormat(self::GROUP_CREATION_COST)
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param Profile $profile
     * @param System $currentSystem
     * @return bool|GameClientResponse
     */
    private function createGroupChecks(Profile $profile, System $currentSystem)
    {
        // check if they are already in a group
        if ($this->isProfileInGroup($profile)) {
            $message = $this->translate('You are already a member of a group');
            return $this->gameClientResponse->addMessage($message);
        }
        // check if they are in a faction system
        if (!$currentSystem->getFaction()) {
            $message = $this->translate('You must be in a faction system to create a group');
            return $this->gameClientResponse->addMessage($message);
        }
        // check if they are in a faction
        if (!$this->isProfileInFaction($profile)) {
            $message = $this->translate('You must be a member of a faction to create a group');
            return $this->gameClientResponse->addMessage($message);
        }
        // check if they are in a fitting faction system
        if ($profile->getFaction() != $currentSystem->getFaction()) {
            $message = $this->translate('You must be in a system of your faction to create a group');
            return $this->gameClientResponse->addMessage($message);
        }
        // check if they have enough credits
        if ($profile->getCredits() < self::GROUP_CREATION_COST) {
            $message = sprintf(
                $this->translate('You need %s credits to create a group'),
                self::GROUP_CREATION_COST
            );
            return $this->gameClientResponse->addMessage($message);
        }
        return true;
    }

    /**
     * @param Profile $profile
     * @param Group|null $group
     * @return bool
     */
    private function isProfileInGroup(Profile $profile, Group $group = null)
    {
        if ($group) {
            return ($profile->getGroup() === $group) ? true : false;
        }
        return ($profile->getGroup()) ? true : false;
    }

    /**
     * @param Profile $profile
     * @param Faction|null $faction
     * @return bool
     */
    private function isProfileInFaction(Profile $profile, Faction $faction = null)
    {
        if ($faction) {
            return ($profile->getFaction() === $faction) ? true : false;
        }
        return ($profile->getFaction()) ? true : false;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createGroup($resourceId, $contentArray)
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
        $checkResult = $this->createGroupChecks($profile, $currentSystem);
        if ($checkResult instanceof GameClientResponse) {
            return $checkResult->send();
        }
        // now get the new name
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$newName) {
            $message = $this->translate('Please specify a name for the group (alpha-numeric only, 3-chars-min, 19-chars-max)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $checkResult = $this->stringChecker($newName, 19, 3);
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        $utilityService = $this->getWebsocketServer()->getUtilityService();
        // create a new addy
        $addy = $utilityService->getRandomAddress(32);
        $maxTries = 100;
        $tries = 0;
        while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
            $addy = $utilityService->getRandomAddress(32);
            $tries++;
            if ($tries >= $maxTries) {
                $message = $this->translate('Unable to initialize the group system! Please contact an administrator!');
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        $newName = str_replace(' ', '_', $newName);
        $faction = $currentSystem->getFaction();
        $group = new Group();
        $group->setCredits(ceil(round(self::GROUP_CREATION_COST / 2)));
        $group->setSnippets(0);
        $group->setAdded(new \DateTime());
        $group->setDescription('this group does not have a description');
        $group->setFaction($faction);
        $group->setName($newName);
        $group->setOpenRecruitment(false);
        $this->entityManager->persist($group);
        // founder and leader role
        $this->addGroupRole($profile, $group, GroupRole::ROLE_FOUNDER_ID);
        $this->addGroupRole($profile, $group, GroupRole::ROLE_LEADER_ID);
        $systemName = $newName . '_headquarters';
        $system = $this->createBaseSystem($systemName, $addy, null, null, $group, false, 5, System::GROUP_MAX_SYSTEM_SIZE);
        $profile->setGroup($group);
        $this->entityManager->flush();
        $message = sprintf(
            'group [%s] has been created - group system [%s] [%s]',
            $newName,
            $system->getName(),
            $addy
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param Profile $profile
     * @param Group $group
     * @param $groupRoleId
     */
    private function addGroupRole(Profile $profile, Group $group, $groupRoleId)
    {
        $groupRole = $this->groupRoleRepo->find($groupRoleId);
        $gri = new GroupRoleInstance();
        $gri->setGroup($group);
        $gri->setAdded(new \DateTime());
        $gri->setChanger(null);
        $gri->setGroupRole($groupRole);
        $gri->setMember($profile);
        $this->entityManager->persist($gri);
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function joinGroup($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $checkResult = $this->joinGroupChecks($profile);
        if ($checkResult instanceof GameClientResponse) {
            return $checkResult->send();
        }
        /* checks passed, we can join the group */
        $group = $profile->getGroup();
        $profile->setGroup($group);
        $this->addGroupRole($profile, $group, GroupRole::ROLE_NEWBIE_ID);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have joined [%s]'),
            $group->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has joined [%s]'),
            $this->user->getUsername(),
            $group->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param Profile $profile
     * @return bool|GameClientResponse
     */
    private function joinGroupChecks(Profile $profile)
    {
        // check if they are already in a group
        if ($this->isProfileInGroup($profile)) {
            $message = $this->translate('You are already a member of a group - you need to leave that group before you can join another one');
            return $this->gameClientResponse->addMessage($message);
        }
        $currentNode = $profile->getCurrentNode();
        $group = null;
        // check if they are in a recruitment node
        if ($currentNode->getNodeType()->getId() != NodeType::ID_RECRUITMENT) {
            $message = $this->translate('You must be in a recruitment node of the group that you want to join');
            return $this->gameClientResponse->addMessage($message);
        }
        // check if they are currently blocked from joining a group
        if ($profile->getGroupJoinBlockDate() > new \DateTime()) {
            $message = sprintf(
                $this->translate('You must wait until [%s] before you can join another group - use "time" to get the current server time'),
                $profile->getGroupJoinBlockDate()->format('Y/m/d H:i:s')
            );
            return $this->gameClientResponse->addMessage($message);
        }
        $currentSystem = $currentNode->getSystem();
        $group = $currentSystem->getGroup();
        if (!$group) {
            $message = $this->translate('You must be in a recruitment node of the group that you want to join');
            return $this->gameClientResponse->addMessage($message);
        }
        if (!$this->isProfileInFaction($profile)) {
            $message = $this->translate('You must join a faction before you can join a group');
            return $this->gameClientResponse->addMessage($message);
        }
        if ($group->getFaction() !== $profile->getFaction()) {
            $message = $this->translate('You can not join a group that belongs to another faction');
            return $this->gameClientResponse->addMessage($message);
        }
        // check if it is open recruitment or if they need to write an application
        if (!$group->getOpenRecruitment()) {
            // check if they have an invitation
            $invitation = $this->getWebsocketServer()->getGroupInvitation($group->getId(), $profile->getId());
            if ($invitation) {
                $this->getWebsocketServer()->removeGroupInvitation($group->getId(), $profile->getId());
            } else {
                // no invitation - reject
                $message = $this->translate('You can not join this group without an invitation');
                return $this->gameClientResponse->addMessage($message);
            }
        }
        return true;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function manageGroupCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $group = $profile->getGroup();
        if (!$group) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/group/manage-group.phtml');
        // gather important data
        $members = $this->profileRepo->findBy(['group' => $group]);
        $systems = $this->systemRepo->findBy(['group' => $group]);
        $allInvited = $this->getWebsocketServer()->getGroupInvitations();
        $invitations = (array_key_exists($group->getId(), $allInvited)) ? $allInvited[$group->getId()] : [];
        $view->setVariables([
            'group' => $group,
            'members' => $members,
            'systems' => $systems,
            'invitations' => $invitations
        ]);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOW_GROUP_PANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is managing their group'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function toggleRecruitment($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $group = $profile->getGroup();
        if (!$group) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$this->memberRoleIsAllowed($profile, $group, GroupRole::$allowedToggleRecruitment)) {
            $message = $this->translate('You are not allowed to toggle recruitment options');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($group->getOpenRecruitment()) {
            $newValue = false;
            $stringValue = $this->translate('no');
        } else {
            $newValue = true;
            $stringValue = $this->translate('yes');
        }
        $group->setOpenRecruitment($newValue);
        $this->entityManager->flush($group);
        $this->updateInterfaceElement($resourceId, '#toggle-open-recruitment', $stringValue);
        return $this->gameClientResponse
            ->addMessage($this->translate('recruitment option toggled'), GameClientResponse::CLASS_SUCCESS)
            ->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function groupInvitation($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $ws = $this->getWebsocketServer();
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $group = $profile->getGroup();
        // check if they are already in a group
        if (!$group) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are allowed to recruit
        if (!$this->memberRoleIsAllowed($profile, $group, GroupRole::$allowedToggleRecruitment)) {
            $message = $this->translate('You not allowed to recruit for your group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the new member
        $recruitName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$recruitName) {
            $message = $this->translate('Please specify the name of the new recruit');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $recruit = $this->profileRepo->findLikeName($recruitName);
        if (!$recruit) {
            $message = $this->translate('Invalid recruit');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($recruit->getGroup()) {
            $message = $this->translate('That user is already in another group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$recruit->getCurrentResourceId()) {
            $message = $this->translate('That user is not online');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$recruit->getFaction()) {
            $message = $this->translate('That user is not in a faction');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($recruit->getFaction() !== $group->getFaction()) {
            $message = $this->translate('That user is not in your faction');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($ws->getGroupInvitation($group->getId(), $profile->getId())) {
            $message = $this->translate('That user has already been invited');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all seems good, add invite
        $ws->setGroupInvitation($group->getId(), $recruit->getId(), [
            'recruiter' => $profile->getId(),
            'recruitername' => $profile->getUser()->getUsername(),
            'added' => new \DateTime(),
            'recruitname' => $recruit->getUser()->getUsername()
        ]);
        $xMessage = sprintf(
            $this->translate('[%s] has invited you to join their group [%s]'),
            $profile->getUser()->getUsername(),
            $group->getName()
        );
        $this->messageProfileNew($recruit, $xMessage, GameClientResponse::CLASS_INFO);
        $message = sprintf(
            $this->translate('You have invited [%s] to join your group'),
            $recruit->getUser()->getUsername()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function leaveGroup($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        // check if they are already in a group
        $checkResult = $this->leaveGroupChecks($profile);
        if ($checkResult instanceof GameClientResponse) {
            return $checkResult->send();
        }
        $group = $profile->getGroup();
        $this->cleanUpForMemberRemoval($profile);
        $profile->setGroup(null);
        $blockDate = new \DateTime();
        $blockDate->add(new \DateInterval('PT1D'));
        $profile->setGroupJoinBlockDate($blockDate);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have left [%s]'),
            $group->getName()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param Profile $profile
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function leaveGroupChecks(Profile $profile)
    {
        if (!$this->isProfileInGroup($profile)) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message);
        }
        $group = $profile->getGroup();
        $groupMembers = $this->profileRepo->countByGroup($group);
        if ($groupMembers <= 1) {
            $message = $this->translate('You are the only member of the group, you must disband the group instead');
            return $this->gameClientResponse->addMessage($message);
        }
        /** @var GroupRole $leaderRole */
        $leaderRole = $this->entityManager->find(GroupRole::class, GroupRole::ROLE_LEADER_ID);
        $isLeader = $this->groupRoleInstanceRepo->findOneBy([
            'groupRole' => $leaderRole,
            'member' => $profile,
            'group' => $group
        ]);
        if ($isLeader) {
            $message = $this->translate('You must appoint another leader before you can leave the group');
            return $this->gameClientResponse->addMessage($message);
        }
        return true;
    }

    /**
     * @param Profile $profile
     */
    private function cleanUpForMemberRemoval(Profile $profile)
    {
        $group = $profile->getGroup();
        $systems = $this->systemRepo->findBy([
            'group' => $group
        ]);
        /** @var System $system */
        foreach ($systems as $system) {
            $files = $this->fileRepo->findBy([
                'system' => $system,
                'profile' => $profile
            ]);
            /** @var File $file */
            foreach ($files as $file) {
                $file->setProfile(null);
            }
        }
        $roles = $this->groupRoleInstanceRepo->findBy([
            'member' => $profile,
            'group' => $group
        ]);
        /** @var GroupRoleInstance $role */
        foreach ($roles as $role) {
            $this->entityManager->remove($role);
        }
        $missions = $this->missionRepo->findBy([
            'sourceGroup' => $group,
            'profile' => $profile
        ]);
        /** @var Mission $mission */
        foreach ($missions as $mission) {
            $mission
                ->setSourceFaction($group->getFaction())
                ->setTargetFaction($mission->getTargetGroup()->getFaction())
                ->setSourceGroup(null)
                ->setTargetGroup(null);
        }
        $npcInstances = $this->npcInstanceRepo->findBy([
            'group' => $group,
            'profile' => $profile
        ]);
        /** @var NpcInstance $npcInstance */
        foreach ($npcInstances as $npcInstance) {
            $npcInstance->setProfile(null);
        }
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function disbandGroup($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $group = $profile->getGroup();
        $faction = $group->getFaction();
        /** @var GroupRole $leaderRole */
        $leaderRole = $this->entityManager->find(GroupRole::class, GroupRole::ROLE_LEADER_ID);
        $isLeader = $this->groupRoleInstanceRepo->findOneBy([
            'groupRole' => $leaderRole,
            'member' => $profile,
            'group' => $group
        ]);
        if (!$isLeader) {
            $message = $this->translate('You must be the leader of the group to disband it');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $systems = $this->systemRepo->findBy([
            'group' => $group
        ]);
        /** @var System $system */
        foreach ($systems as $system) {
            $system->setGroup(null)->setFaction($faction);
            $files = $this->fileRepo->findBy([
                'system' => $system
            ]);
            /** @var File $file */
            foreach ($files as $file) {
                $fileProfile = $file->getProfile();
                if ($fileProfile && $fileProfile->getGroup() === $group) {
                    $file->setProfile(null)->setRunning(false);
                }
            }
        }
        $groupRoleInstances = $this->groupRoleInstanceRepo->findBy([
            'group' => $group
        ]);
        foreach ($groupRoleInstances as $groupRoleInstance) {
            $this->entityManager->remove($groupRoleInstance);
        }
        $missions = $this->missionRepo->findBy([
            'sourceGroup' => $group
        ]);
        /** @var Mission $mission */
        foreach ($missions as $mission) {
            $mission
                ->setSourceFaction($faction)
                ->setTargetFaction($mission->getTargetGroup()->getFaction())
                ->setSourceGroup(null)
                ->setTargetGroup(null);
        }
        $npcInstances = $this->npcInstanceRepo->findBy([
            'group' => $group
        ]);
        /** @var NpcInstance $npcInstance */
        foreach ($npcInstances as $npcInstance) {
            $npcInstance->setFaction($faction)->setGroup(null)->setProfile(null);
        }
        $profiles = $this->profileRepo->findBy([
            'group' => $group
        ]);
        /** @var Profile $profile */
        foreach ($profiles as $profile) {
            $profile->setGroup(null);
        }
        $message = sprintf(
            $this->translate('You have disbanded [%s]'),
            $group->getName()
        );
        $this->entityManager->remove($group);
        $this->entityManager->flush();
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function removeProfileFromGroup($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are already in a group
        if (!$this->isProfileInGroup($profile)) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $group = $profile->getGroup();
        // check if they are allowed to recruit
        if (!$this->memberRoleIsAllowed($profile, $group, GroupRole::$allowedToggleRecruitment)) {
            $message = $this->translate('You not allowed to recruit for your group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the new member
        $recruitName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$recruitName) {
            $message = $this->translate('Please specify the name of the new recruit');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $recruit = $this->profileRepo->findLikeName($recruitName);
        if (!$recruit) {
            $message = $this->translate('Invalid recruit');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$this->isProfileInGroup($recruit, $group)) {
            $message = $this->translate('That user is not in your group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$this->isProfileInFaction($recruit)) {
            $message = $this->translate('That user is not in a faction');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$this->isProfileInFaction($recruit, $profile->getFaction())) {
            $message = $this->translate('That user is not in your faction');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all seems good, remove user from group
        $this->cleanUpForMemberRemoval($profile);
        $recruit->setGroup(null);
        $this->entityManager->flush();
        $xMessage = sprintf(
            $this->translate('[%s] has removed you from [%s]'),
            $profile->getUser()->getUsername(),
            $group->getName()
        );
        $this->messageProfileNew($recruit, $xMessage, GameClientResponse::CLASS_INFO);
        $message = sprintf(
            $this->translate('You have removed [%s] from your group'),
            $recruit->getUser()->getUsername()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function groupDepositCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are already in a group
        if (!$this->isProfileInGroup($profile)) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($currentNode->getNodeType()->getId() != NodeType::ID_BANK) {
            $message = $this->translate('You need to be in a banking node to deposit credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $group = $profile->getGroup();
        // now get the amount
        $depositAmount = $this->getNextParameter($contentArray, false, true);
        if (!$depositAmount) {
            $message = $this->translate('Please specify the deposit amount');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $depositAmount = $this->checkValueMinMax($depositAmount, 1);
        // check if they have that much
        if ($profile->getCredits() < $depositAmount) {
            $message = $this->translate('You do not have that many credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all seems good, deposit */
        // check for skimmer
        $skimmerFiles = $this->fileRepo->findRunningInNodeByType(
            $profile->getCurrentNode(),
            FileType::ID_SKIMMER
        );
        $remainingAmount = $depositAmount;
        $triggerData = ['value' => $remainingAmount];
        foreach ($skimmerFiles as $skimmerFile) {
            /** @var File $skimmerFile */
            $skimAmount = $this->checkFileTriggers($skimmerFile, $triggerData);
            if ($skimAmount === false) continue;
            $remainingAmount -= $skimAmount;
            $triggerData['value'] = $remainingAmount;
        }
        // now add/substract
        $profile->setCredits($profile->getCredits() - $depositAmount);
        $group->setCredits($group->getCredits() + $depositAmount);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have deposited %s credits into your group\'s bank account'),
            $depositAmount
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has deposited some credits'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew(
            $profile->getCurrentNode(),
            $message,
            GameClientResponse::CLASS_MUTED,
            $profile,
            $profile->getId()
        );
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
    public function groupWithdrawCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are already in a group
        if (!$this->isProfileInGroup($profile)) {
            $message = $this->translate('You are not a member of any group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($currentNode->getNodeType()->getId() != NodeType::ID_BANK) {
            $message = $this->translate('You need to be in a banking node to withdraw credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $group = $profile->getGroup();
        if (!$this->memberRoleIsAllowed($profile, $group, GroupRole::$allowedWithdraw)) {
            $message = $this->translate('You not allowed to withdraw credits from your group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the amount
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$amount) {
            $message = $this->translate('Please specify the withdrawal amount');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $amount = $this->checkValueMinMax($amount, 1);
        // check if they have that much
        if ($group->getCredits() < $amount) {
            $message = $this->translate('The group does not have that many credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all seems good, withdraw */
        // now add/substract
        $profile->setCredits($profile->getCredits() + $amount);
        $group->setCredits($group->getCredits() - $amount);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have withdrawn %s credits from your group\'s account'),
            $amount
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has withdrawn some credits'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew(
            $profile->getCurrentNode(),
            $message,
            GameClientResponse::CLASS_MUTED,
            $profile,
            $profile->getId()
        );
        return $this->gameClientResponse->send();
    }

}
