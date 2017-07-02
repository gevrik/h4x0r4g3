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
use Netrunners\Entity\Profile;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Mvc\I18n\Translator;

class AdminService extends BaseService
{

    /**
     * @var Authorize
     */
    protected $authorize;

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
    }

    /**
     *
     * @param int $resourceId
     * @return bool
     */
    private function isSuperAdmin($resourceId)
    {
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $isAdmin = false;
        foreach ($user->getRoles() as $role) {
            /** @var Role $role */
            if ($role->getRoleId() === Role::ROLE_ID_SUPERADMIN) {
                $isAdmin = true;
                break;
            }
        }
        return $isAdmin;
    }

    /**
     *
     * @param int $resourceId
     * @return bool
     */
    private function isAdmin($resourceId)
    {
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $isAdmin = false;
        foreach ($user->getRoles() as $role) {
            /** @var Role $role */
            if ($role->getRoleId() === Role::ROLE_ID_ADMIN || $role->getRoleId() === Role::ROLE_ID_SUPERADMIN) {
                $isAdmin = true;
                break;
            }
        }
        return $isAdmin;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function adminShowClients($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isAdmin($resourceId)) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $ws = $this->getWebsocketServer();
            $message = [sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-6s|%-5s|%-32s|%s</pre>',
                $this->translate('socket'),
                $this->translate('user'),
                $this->translate('name'),
                $this->translate('ip')
            )];
            $amountVoid = 0;
            foreach ($ws->getClientsData() as $xClientData) {
                $currentUser = $this->entityManager->find('TmoAuth\Entity\User', $xClientData['userId']);
                /** @var User $currentUser */
                if (!$currentUser) {
                    $amountVoid++;
                    continue;
                }
                $message[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%-6s|%-5s|%-32s|%s</pre>', $xClientData['socketId'], $currentUser->getId(), $currentUser->getUsername(), $xClientData['ipaddy']);
            }
            if ($amountVoid >= 1) $message[] = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-addon">%s sockets do not have user data yet</pre>'),
                $amountVoid
            );
            $this->response = [
                'command' => 'showoutput',
                'message' => $message
            ];
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function adminSetSnippets($resourceId, $contentArray)
    {
        $response = false;
        if (!$this->isAdmin($resourceId)) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Unknown command')
                )
            ];
            return $response;
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a user id (use "clients" to get a list)')
                )
            ];
        }
        $targetUser = false;
        if (!$response) {
            $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        }
        if (!$response && !$targetUser) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a user id (use "clients" to get a list)')
                )
            ];
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$response && !$amount) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify the amount')
                )
            ];
        }
        if (!$response) {
            $profile = $targetUser->getProfile();
            /** @var Profile $profile */
            $profile->setSnippets($profile->getSnippets() + $amount);
            $this->entityManager->flush($profile);
            $message = sprintf('<pre style="white-space: pre-wrap;" class="text-addon">DONE</pre>');
            $response = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function adminSetCredits($resourceId, $contentArray)
    {
        $response = false;
        if (!$this->isAdmin($resourceId)) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Unknown command')
                )
            ];
            return $response;
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a user id (use "clients" to get a list)')
                )
            ];
        }
        $targetUser = false;
        if (!$response) {
            $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        }
        if (!$response && !$targetUser) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Unable to find a user for that ID'))
            ];
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$response && !$amount) {
            $response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify the amount')
                )
            ];
        }
        if (!$response) {
            $profile = $targetUser->getProfile();
            /** @var Profile $profile */
            $profile->setCredits($profile->getCredits() + $amount);
            $this->entityManager->flush($profile);
            $message = sprintf('<pre style="white-space: pre-wrap;" class="text-addon">DONE</pre>');
            $response = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $response;
    }

}
