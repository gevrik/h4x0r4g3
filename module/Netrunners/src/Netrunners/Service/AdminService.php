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
use Netrunners\Entity\Profile;
use Netrunners\Repository\BannedIpRepository;
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

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function banIp($resourceId, $contentArray)
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
            $ip = $this->getNextParameter($contentArray, false);
            $validator = new Ip();
            if (!$validator->isValid($ip)) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid IP address')
                    )
                ];
            }
            else {
                $bannedIp = new BannedIp();
                $bannedIp->setBanner($this->user->getProfile());
                $bannedIp->setAdded(new \DateTime);
                $bannedIp->setIp($ip);
                $this->entityManager->persist($bannedIp);
                $this->entityManager->flush($bannedIp);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-info">%s</pre>',
                        $this->translate('DONE')
                    )
                ];
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function unbanIp($resourceId, $contentArray)
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
            $ip = $this->getNextParameter($contentArray, false);
            $validator = new Ip();
            if (!$validator->isValid($ip)) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid IP address')
                    )
                ];
            }
            else {
                $bannedIpRepo = $this->entityManager->getRepository('Netrunners\Entity\BannedIp');
                /** @var BannedIpRepository $bannedIpRepo */
                $bannedIpEntry = $bannedIpRepo->findOneBy([
                    'ip' => $ip
                ]);
                if (!$bannedIpEntry) {
                    $this->response = [
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('IP address is not in banned list')
                        )
                    ];
                }
                else {
                    $this->entityManager->remove($bannedIpEntry);
                    $this->entityManager->flush($bannedIpEntry);
                    $this->response = [
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-info">%s</pre>',
                            $this->translate('DONE')
                        )
                    ];
                }
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function banUser($resourceId, $contentArray)
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
            $userId = $this->getNextParameter($contentArray, false, true);
            $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $userId);
            if (!$targetUser) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid user id')
                    )
                ];
            }
            else {
                $targetUser->setBanned(true);
                $this->entityManager->flush($targetUser);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-info">%s</pre>',
                        $this->translate('DONE')
                    )
                ];
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function unbanUser($resourceId, $contentArray)
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
            $userId = $this->getNextParameter($contentArray, false, true);
            $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $userId);
            if (!$targetUser) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid user id')
                    )
                ];
            }
            else {
                $targetUser->setBanned(false);
                $this->entityManager->flush($targetUser);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-info">%s</pre>',
                        $this->translate('DONE')
                    )
                ];
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function kickClient($resourceId, $contentArray)
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
            list($contentArray, $targetResourceId) = $this->getNextParameter($contentArray, true, true);
            if (!$targetResourceId) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Please specify the resource id ("clients" for list)')
                    )
                ];
            }
            if (!$this->response && !array_key_exists($targetResourceId, $this->getWebsocketServer()->getClientsData())) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('That socket is not connected!')
                    )
                ];
            }
            if (!$this->response && ($this->isAdmin($targetResourceId) || $this->isSuperAdmin($targetResourceId))) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('They really would not like that!')
                    )
                ];
            }
            if (!$this->response) {
                $reason = $this->getNextParameter($contentArray, false, false, true, true);
                foreach ($this->getWebsocketServer()->getClients() as $wsClientId => $wsClient) {
                    if ($wsClient->resourceId === $targetResourceId) {
                        $clientResponse = [
                            'command' => 'showmessage',
                            'message' => sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">You have been kicked by [%s], reason given: %s</pre>'),
                                $this->user->getUsername(),
                                ($reason) ? $reason : $this->translate('no reason given')
                            )
                        ];
                        $wsClient->send(json_encode($clientResponse));
                        $wsClient->close();
                        break;
                    }
                }
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-info">%s</pre>',
                        $this->translate('DONE')
                    )
                ];
            }
        }
        return $this->response;
    }

}
