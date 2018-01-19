<?php

/**
 * Admin Service.
 * The service supplies methods that resolve logic around admin commands.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use BjyAuthorize\Service\Authorize;
use Doctrine\ORM\EntityManager;
use Netrunners\Entity\BannedIp;
use Netrunners\Entity\File;
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ServerSetting;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\BannedIpRepository;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FileModRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\SystemRepository;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Mvc\I18n\Translator;
use Zend\Validator\Ip;

class AdminService extends BaseService
{

    /**
     * @var Authorize
     */
    protected $authorize;

    /**
     * @var FileTypeRepository
     */
    protected $fileTypeRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var FileModRepository
     */
    protected $fileModRepo;

    /**
     * @var FileModInstanceRepository
     */
    protected $fileModInstanceRepo;

    /**
     * AdminService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     * @param Authorize $authorize
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, $viewRenderer, $authorize, $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->authorize = $authorize;
        $this->fileTypeRepo = $this->entityManager->getRepository('Netrunners\Entity\FileType');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->fileModRepo = $this->entityManager->getRepository('Netrunners\Entity\FileMod');
        $this->fileModInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FileModInstance');
    }

    /**
     * Give a registration invitation to a player.
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function giveInvitation($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            $message = $this->translate('Please specify a user id (use "clients" to get a list)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        if (!$targetUser) {
            $message = $this->translate('Please specify a user id (use "clients" to get a list)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $special = $this->getNextParameter($contentArray, false, true);
        $this->gainInvitation($targetUser->getProfile(), $special);
        $message = $this->translate('Invitation given');
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function adminShowUsers($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $returnMessage = sprintf(
            '%-11s|%-32s',
            $this->translate('ID'),
            $this->translate('USERNAME')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        $users = $this->entityManager->getRepository('TmoAuth\Entity\User')->findAll();
        foreach ($users as $user) {
            /** @var User $user */
            $returnMessage = sprintf(
                '<pre class="text-white">%-11s|%-32s</pre>',
                $user->getId(),
                $user->getUsername()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function adminShowClients($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $ws = $this->getWebsocketServer();
        $message = sprintf(
            '%-6s|%-5s|%-32s|%s',
            $this->translate('socket'),
            $this->translate('user'),
            $this->translate('name'),
            $this->translate('ip')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $amountVoid = 0;
        foreach ($ws->getClientsData() as $xClientData) {
            $currentUser = $this->entityManager->find('TmoAuth\Entity\User', $xClientData['userId']);
            /** @var User $currentUser */
            if (!$currentUser) {
                $amountVoid++;
                continue;
            }
            $message = sprintf(
                '%-6s|%-5s|%-32s|%s',
                $xClientData['socketId'],
                $currentUser->getId(),
                $currentUser->getUsername(),
                $xClientData['ipaddy']
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        }
        if ($amountVoid >= 1) {
            $message = sprintf(
                $this->translate('%s sockets do not have user data yet'),
                $amountVoid
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_ADDON);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function adminSetMotd($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
        /** @var ServerSetting $serverSetting */
        $motd = $this->getNextParameter($contentArray, false, false, true, true);
        $serverSetting->setMotd($motd);
        $this->entityManager->flush($serverSetting);
        return $this->gameClientResponse->addMessage($this->translate('MOTD set'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function adminSetSnippets($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a user id (use "clients" to get a list)'))->send();
        }
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        if (!$targetUser) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid user id'))->send();
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$amount) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the amount'))->send();
        }
        $profile = $targetUser->getProfile();
        /** @var Profile $profile */
        $profile->setSnippets($profile->getSnippets() + $amount);
        $this->entityManager->flush($profile);
        return $this->gameClientResponse->addMessage($this->translate('Snippets added'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function adminSetCredits($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a user id (use "clients" to get a list)'))->send();
        }
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        if (!$targetUser) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid user id'))->send();
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$amount) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the amount'))->send();
        }
        $profile = $targetUser->getProfile();
        /** @var Profile $profile */
        $profile->setCredits($profile->getCredits() + $amount);
        $this->entityManager->flush($profile);
        return $this->gameClientResponse->addMessage($this->translate('Credits added'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function banIp($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $ip = $this->getNextParameter($contentArray, false);
        $validator = new Ip();
        if (!$validator->isValid($ip)) {
            $this->gameClientResponse->addMessage($this->translate('Invalid IP address'));
        }
        else {
            $bannedIp = new BannedIp();
            $bannedIp->setBanner($this->user->getProfile());
            $bannedIp->setAdded(new \DateTime);
            $bannedIp->setIp($ip);
            $this->entityManager->persist($bannedIp);
            $this->entityManager->flush($bannedIp);
            $this->gameClientResponse->addMessage($this->translate('IP banned'), GameClientResponse::CLASS_SUCCESS);

        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function unbanIp($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $ip = $this->getNextParameter($contentArray, false);
        $validator = new Ip();
        if (!$validator->isValid($ip)) {
            $this->gameClientResponse->addMessage($this->translate('Invalid IP address'));
        }
        else {
            $bannedIpRepo = $this->entityManager->getRepository('Netrunners\Entity\BannedIp');
            /** @var BannedIpRepository $bannedIpRepo */
            $bannedIpEntry = $bannedIpRepo->findOneBy([
                'ip' => $ip
            ]);
            if (!$bannedIpEntry) {
                $this->gameClientResponse->addMessage($this->translate('IP address is not in banned list'));
            }
            else {
                $this->entityManager->remove($bannedIpEntry);
                $this->entityManager->flush($bannedIpEntry);
                $this->gameClientResponse->addMessage($this->translate('IP address no longer banned'), GameClientResponse::CLASS_SUCCESS);
            }
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
    public function banUser($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $userId = $this->getNextParameter($contentArray, false, true);
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $userId);
        if (!$targetUser) {
            $this->gameClientResponse->addMessage($this->translate('Invalid user id'));
        }
        else {
            $targetUser->setBanned(true);
            $this->entityManager->flush($targetUser);
            $this->gameClientResponse->addMessage($this->translate('User banned'), GameClientResponse::CLASS_SUCCESS);
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
    public function unbanUser($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $userId = $this->getNextParameter($contentArray, false, true);
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $userId);
        if (!$targetUser) {
            $this->gameClientResponse->addMessage($this->translate('Invalid user id'));
        }
        else {
            $targetUser->setBanned(false);
            $this->entityManager->flush($targetUser);
            $this->gameClientResponse->addMessage($this->translate('User unbanned'), GameClientResponse::CLASS_SUCCESS);
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
    public function kickClient($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $targetResourceId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetResourceId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the resource id ("clients" for list)'))->send();
        }
        if (!array_key_exists($targetResourceId, $this->getWebsocketServer()->getClientsData())) {
            return $this->gameClientResponse->addMessage($this->translate('That socket is not connected!'))->send();
        }
        $targetUserClientData = $this->getWebsocketServer()->getClientData($targetResourceId);
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserClientData['userId']);
        if ($targetUser && $this->hasRole($targetUser, Role::ROLE_ID_SUPERADMIN)) {
            return $this->gameClientResponse->addMessage($this->translate('They really would not like that!'))->send();
        }
        $reason = $this->getNextParameter($contentArray, false, false, true, true);
        foreach ($this->getWebsocketServer()->getClients() as $wsClientId => $wsClient) {
            if ($wsClient->resourceId === $targetResourceId) {
                $otherResponse = new GameClientResponse($targetResourceId, GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
                $message = sprintf(
                    $this->translate('You have been kicked by [%s], reason given: %s'),
                    $this->user->getUsername(),
                    ($reason) ? $reason : $this->translate('no reason given')
                );
                $otherResponse->addMessage($message, GameClientResponse::CLASS_DANGER)->send();
                $wsClient->close();
                break;
            }
        }
        return $this->gameClientResponse->addMessage($this->translate('User kicked!'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function adminToggleAdminMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_SUPERADMIN)) {
            return $this->gameClientResponse->send();
        }
        /* user is superadmin, can change server mode */
        $ws = $this->getWebsocketServer();
        $ws->setAdminMode(($ws->isAdminMode()) ? false : true);
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-info">DONE - admin mode is now %s</pre>',
            ($ws->isAdminMode()) ? 'ON' : 'OFF'
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
    public function gotoNodeCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they own this system or if they are an admin
        if ($currentNode->getSystem()->getProfile() !== $profile) {
            if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
                return $this->gameClientResponse->addMessage($this->translate('Unable to quick-move in systems that are not owned by you'))->send();
            }
        }
        // get target node id from params
        $targetNodeId = $this->getNextParameter($contentArray, false, true);
        if (!$targetNodeId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the node id ("nodes" for list)'))->send();
        }
        // get target node
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $targetNode = $nodeRepo->find($targetNodeId);
        if (!$targetNode) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid node id'))->send();
        }
        // check if they are trying to move to the same node that they are currently in
        if ($targetNode == $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('You are already there'))->send();
        }
        $targetSystem = $targetNode->getSystem();
        // they can't quick-move if the target node is in a different system (unless admin role)
        if ($targetSystem != $currentNode->getSystem()) {
            if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
                return $this->gameClientResponse->addMessage($this->translate('Unable to quick-move between systems'))->send();
            }
        }
        $this->movePlayerToTargetNodeNew($resourceId, $profile, NULL, $currentNode, $targetNode);
        // check if we need to move the background map
        if ($currentNode->getSystem() != $targetNode->getSystem()) {
            $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
            $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, explode(',',$targetNode->getSystem()->getGeocoords()));
            $this->gameClientResponse->send();
        }
        // update map
        $this->updateMap($resourceId);
        // add success message and send
        $this->gameClientResponse
            ->reset()
            ->setSilent(true)
            ->addMessage($this->translate('You have connected to the target node'), GameClientResponse::CLASS_SUCCESS)
            ->send();
        // redirect to show-node-info method
        return $this->showNodeInfoNew($resourceId, NULL, true);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function nListCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $targetSystemId = $this->getNextParameter($contentArray, false, true);
        if (!$targetSystemId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the system id ("syslist" for list)'))->send();
        }
        $targetSystem = $this->entityManager->find('Netrunners\Entity\System', $targetSystemId);
        if (!$targetSystem) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid system id'))->send();
        }
        /** @var System $targetSystem */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systemNodes = $nodeRepo->findBySystem($targetSystem);
        $returnMessage = sprintf(
            '%-11s|%-20s|%-3s|%s',
            $this->translate('ID'),
            $this->translate('TYPE'),
            $this->translate('LVL'),
            $this->translate('NAME')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($systemNodes as $node) {
            /** @var Node $node */
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-20s|%-3s|%s</pre>',
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
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function sysListCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        /* user is superadmin, can see system list */
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $systems = $systemRepo->findAll();
        $returnMessage = sprintf(
            '%-11s|%-20s|%s',
            $this->translate('ID'),
            $this->translate('OWNER'),
            $this->translate('NAME')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($systems as $system) {
            /** @var System $system */
            $returnMessage = sprintf(
                '%-11s|%-20s|%s',
                $system->getId(),
                ($system->getProfile()) ? $system->getProfile()->getUser()->getUsername() : '---',
                $system->getName()
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
     */
    public function grantRoleCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_SUPERADMIN)) {
            return $this->gameClientResponse->send();
        }
        $validCheck = $this->validUserAndRoleCheck($contentArray);
        if (!is_array($validCheck)) {
            return $this->gameClientResponse->addMessage($validCheck)->send();
        }
        /* all checks passed - we can add the role */
        list($targetUser, $targetRole) = $validCheck;
        if ($targetUser && $targetRole) {
            /** @var Role $targetRole */
            $targetUser->addRole($targetRole);
            $this->entityManager->flush($targetUser);
            $this->gameClientResponse->addMessage($this->translate('Role granted'), GameClientResponse::CLASS_SUCCESS);
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
    public function removeRoleCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_SUPERADMIN)) {
            return $this->gameClientResponse->send();
        }
        $validCheck = $this->validUserAndRoleCheck($contentArray);
        if (!is_array($validCheck)) {
            return $this->gameClientResponse->addMessage($validCheck)->send();
        }
        /* all checks passed - we can add the role */
        list($targetUser, $targetRole) = $this->validUserAndRoleCheck($contentArray);
        if ($targetUser && $targetRole) {
            /** @var User $targetUser */
            /** @var Role $targetRole */
            $targetUser->removeRole($targetRole);
            $this->entityManager->flush($targetUser);
            $this->gameClientResponse->addMessage($this->translate('Role removed'), GameClientResponse::CLASS_SUCCESS);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $contentArray
     * @return array|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function validUserAndRoleCheck($contentArray)
    {
        $targetUser = NULL;
        $targetRole = NULL;
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            return $this->translate('Please specify the user id ("clients" for list)');
        }
        $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        /** @var User $targetUser */
        if (!$targetUser) {
            return $this->translate('Invalid user id');
        }
        $targetRoleString = $this->getNextParameter($contentArray, false);
        if (!$targetRoleString) {
            return $this->translate('Please specify the role to be granted');
        }
        $targetRole = $this->entityManager->getRepository('TmoAuth\Entity\Role')->findOneBy([
            'roleId' => $targetRoleString
        ]);
        if (!$targetRole) {
            return $this->translate('Invalid role name');
        }
        return [$targetUser, $targetRole];
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function invokeFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $profile = $this->user->getProfile();
        list($contentArray, $fileTypeName) = $this->getNextParameter($contentArray, true, false, false, true);
        list($contentArray, $level) = $this->getNextParameter($contentArray, true, true);
        $slots = $this->getNextParameter($contentArray, false, true);
        if (!$fileTypeName) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid file type'), GameClientResponse::CLASS_DANGER)->send();
        }
        if (!$level) $level = 1;
        if (!$slots) $slots = 1;
        $fileType = $this->fileTypeRepo->findLikeName($fileTypeName);
        $file = new File();
        $file->setIntegrity(100);
        $file->setCoder($profile);
        $file->setContent(NULL);
        $file->setCreated(new \DateTime());
        $file->setData(NULL);
        $file->setExecutable($fileType->getExecutable());
        $file->setFileType($fileType);
        $file->setLevel($level);
        $file->setMailMessage(NULL);
        $file->setMaxIntegrity(100);
        $file->setModified(NULL);
        $file->setName('prototype_' . $fileType->getName());
        $file->setNode(NULL);
        $file->setNpc(NULL);
        $file->setProfile($profile);
        $file->setRunning(false);
        $file->setSize($fileType->getSize());
        $file->setSlots($slots);
        $file->setSystem(NULL);
        $file->setVersion(1);
        $this->entityManager->persist($file);
        $this->entityManager->flush($file);
        return $this->gameClientResponse->addMessage($this->translate('File prototype invoked'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function setfileproperty($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $profile = $this->user->getProfile();
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true, false, false, true);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        /* start logic */
        /** @var File $targetFile */
        $targetFile = array_shift($targetFiles);
        list($contentArray, $property) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$property) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid property'))->send();
        }
        $newValue = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$newValue) $newValue = 1;
        switch ($property) {
            default:
                return $this->gameClientResponse->addMessage($this->translate('Invalid property'))->send();
            case 'integrity': // TODO extract constants
                $maxIntegrity = $targetFile->getMaxIntegrity();
                if ($newValue > $maxIntegrity) $newValue = $maxIntegrity;
                $targetFile->setIntegrity($newValue);
                break;
            case 'level':
                if ($newValue > 100) $newValue = 100;
                $targetFile->setLevel($newValue);
                break;
            case 'maxintegrity':
                if ($newValue > 100) $newValue = 100;
                $targetFile->setMaxIntegrity($newValue);
                break;
            case 'name':
                $checkResult = $this->stringChecker($newValue);
                if ($checkResult) {
                    return $this->gameClientResponse->addMessage($checkResult)->send();
                }
                $newValue = str_replace(' ', '_', $newValue);
                $targetFile->setName($newValue);
                break;
            case 'slots':
                $targetFile->setSlots($newValue);
                break;
            case 'version':
                $targetFile->setVersion($newValue);
                break;
            case 'profile':
                $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $newValue);
                if (!$targetUser) {
                    return $this->gameClientResponse->addMessage($this->translate('Invalid user id'))->send();
                }
                $targetFile->setProfile($targetUser->getProfile());
                break;
        }
        $this->gameClientResponse->addMessage(sprintf('%s: %s set to %s', $targetFile->getName(), $property, $newValue));
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function invokeFileMod($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $profile = $this->user->getProfile();
        list($contentArray, $fileModName) = $this->getNextParameter($contentArray, true, false, false, true);
        $level = $this->getNextParameter($contentArray, false, true);
        if (!$fileModName) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid file mod'), GameClientResponse::CLASS_DANGER)->send();
        }
        if (!$level) $level = 1;
        $fileMod = $this->fileModRepo->findLikeName($fileModName);
        $fileModInstance = new FileModInstance();
        $fileModInstance->setLevel($level);
        $fileModInstance->setProfile($profile);
        $fileModInstance->setCoder($profile);
        $fileModInstance->setAdded(new \DateTime());
        $fileModInstance->setFile(NULL);
        $fileModInstance->setFileMod($fileMod);
        $this->entityManager->persist($fileModInstance);
        $this->entityManager->flush($fileModInstance);
        return $this->gameClientResponse->addMessage($this->translate('Filemod prototype invoked'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function setfilemodproperty($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $profile = $this->user->getProfile();
        list($contentArray, $fileModInstanceId) = $this->getNextParameter($contentArray, true, true);
        // try to get target fmi via repo method
        $fmi = $this->fileModInstanceRepo->find($fileModInstanceId);
        if (!$fmi) {
            return $this->gameClientResponse->addMessage($this->translate('Filemod not found'))->send();
        }
        /* start logic */
        /** @var FileModInstance $fmi */
        list($contentArray, $property) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$property) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid property (one of "level, profile")'))->send();
        }
        $newValue = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$newValue) $newValue = 1;
        switch ($property) {
            default:
                return $this->gameClientResponse->addMessage($this->translate('Invalid property (one of "level, profile")'))->send();
            case 'level':
                if ($newValue > 100) $newValue = 100;
                $fmi->setLevel($newValue);
                break;
            case 'profile':
                if ($fmi->getFile()) {
                    return $this->gameClientResponse->addMessage($this->translate('File mod has already used on a file'))->send();
                }
                $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $newValue);
                if (!$targetUser) {
                    return $this->gameClientResponse->addMessage($this->translate('Invalid user id'))->send();
                }
                $fmi->setProfile($targetUser->getProfile());
                break;
        }
        $this->gameClientResponse->addMessage(sprintf('%s: %s set to %s', $fmi->getFileMod()->getName(), $property, $newValue));
        return $this->gameClientResponse->send();
    }

}
