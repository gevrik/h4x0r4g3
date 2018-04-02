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
use Netrunners\Entity\Group;
use Netrunners\Entity\GroupRole;
use Netrunners\Entity\GroupRoleInstance;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\GroupRepository;
use Netrunners\Repository\GroupRoleInstanceRepository;
use Netrunners\Repository\GroupRoleRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class GroupService extends BaseService
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
     * GroupService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->groupRepo = $this->entityManager->getRepository('Netrunners\Entity\Group');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        $this->systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        $this->groupRoleInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\GroupRoleInstance');
        $this->groupRoleRepo = $this->entityManager->getRepository('Netrunners\Entity\GroupRole');
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
        // check if they are already in a group
        if ($profile->getGroup()) {
            $message = $this->translate('You are already a member of a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are in a faction system
        if (!$currentSystem->getFaction()) {
            $message = $this->translate('You must be in a faction system to create a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are in a faction
        if (!$profile->getFaction()) {
            $message = $this->translate('You must be a member of a faction to create a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are in a fitting faction system
        if ($profile->getFaction() != $currentSystem->getFaction()) {
            $message = $this->translate('You must be in a system of your faction to create a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they have enough credits
        if ($profile->getCredits() < self::GROUP_CREATION_COST) {
            $message = sprintf(
                $this->translate('You need %s credits to create a group'),
                self::GROUP_CREATION_COST
            );
            return $this->gameClientResponse->addMessage($message)->send();
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
        $currentNode = $profile->getCurrentNode();
        // check if they are already in a group
        if ($profile->getGroup()) {
            $message = $this->translate('You are already a member of a group - you need to leave that group before you can join another one');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $group = null;
        // check if they are in a recruitment node
        if ($currentNode->getNodeType()->getId() != NodeType::ID_RECRUITMENT) {
            $message = $this->translate('You must be in a recruitment node of the group that you want to join');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are currently blocked from joining a group
        if ($profile->getGroupJoinBlockDate() > new \DateTime()) {
            $message = sprintf(
                $this->translate('You must wait until [%s] before you can join another group - use "time" to get the current server time'),
                $profile->getGroupJoinBlockDate()->format('Y/m/d H:i:s')
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $currentSystem = $currentNode->getSystem();
        $group = $currentSystem->getGroup();
        if (!$group) {
            $message = $this->translate('You must be in a recruitment node of the group that you want to join');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$profile->getFaction()) {
            $message = $this->translate('You must join a faction before you can join a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($group->getFaction() !== $profile->getFaction()) {
            $message = $this->translate('You can not join a group that belongs to another faction');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if it is open recruitment or if they need to write an application
        if (!$group->getOpenRecruitment()) {
            // check if they have an invitation
            $invitation = $this->getWebsocketServer()->getGroupInvitation($group->getId(), $profile->getId());
            if ($invitation) {
                $this->getWebsocketServer()->removeGroupInvitation($group->getId(), $profile->getId());
            }
            else {
                // no invitation - reject
                $message = $this->translate('You can not join this group without an invitation');
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        /* checks passed, we can join the group */
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
        $members = $this->profileRepo->findBy(['group'=>$group]);
        $systems = $this->systemRepo->findBy(['group'=>$group]);
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
        }
        else {
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

}
