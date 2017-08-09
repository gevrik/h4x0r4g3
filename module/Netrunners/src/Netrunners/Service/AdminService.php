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
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\BannedIpRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\SystemRepository;
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
     * @param $resourceId
     * @return array|bool|false
     */
    public function adminShowClients($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isAdmin()) {
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
                $message[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-6s|%-5s|%-32s|%s</pre>',
                    $xClientData['socketId'],
                    $currentUser->getId(),
                    $currentUser->getUsername(),
                    $xClientData['ipaddy']
                );
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
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Unknown command')
                )
            ];
            return $this->response;
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a user id (use "clients" to get a list)')
                )
            ];
        }
        $targetUser = false;
        if (!$this->response) {
            $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        }
        if (!$this->response && !$targetUser) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a user id (use "clients" to get a list)')
                )
            ];
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$this->response && !$amount) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify the amount')
                )
            ];
        }
        if (!$this->response) {
            $profile = $targetUser->getProfile();
            /** @var Profile $profile */
            $profile->setSnippets($profile->getSnippets() + $amount);
            $this->entityManager->flush($profile);
            $message = sprintf('<pre style="white-space: pre-wrap;" class="text-addon">DONE</pre>');
            $this->response = [
                'command' => 'showmessage',
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
    public function adminSetCredits($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Unknown command')
                )
            ];
            return $this->response;
        }
        list($contentArray, $targetUserId) = $this->getNextParameter($contentArray, true, true);
        if (!$targetUserId) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a user id (use "clients" to get a list)')
                )
            ];
        }
        $targetUser = false;
        if (!$this->response) {
            $targetUser = $this->entityManager->find('TmoAuth\Entity\User', $targetUserId);
        }
        if (!$this->response && !$targetUser) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Unable to find a user for that ID'))
            ];
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        if (!$this->response && !$amount) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify the amount')
                )
            ];
        }
        if (!$this->response) {
            $profile = $targetUser->getProfile();
            /** @var Profile $profile */
            $profile->setCredits($profile->getCredits() + $amount);
            $this->entityManager->flush($profile);
            $message = sprintf('<pre style="white-space: pre-wrap;" class="text-addon">DONE</pre>');
            $this->response = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $this->response;
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
        if (!$this->isAdmin()) {
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
        if (!$this->isAdmin()) {
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
        if (!$this->isAdmin()) {
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
        if (!$this->isAdmin()) {
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
        if (!$this->isAdmin()) {
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
            if (!$this->response && ($this->isAdmin() || $this->isSuperAdmin())) {
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

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function adminToggleAdminMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isSuperAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        /* user is superadmin, can change server mode */
        if (!$this->response) {
            $ws = $this->getWebsocketServer();
            $ws->setAdminMode(($ws->isAdminMode()) ? false : true);
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-info">DONE - admin mode is now %s</pre>',
                    ($ws->isAdminMode()) ? 'ON' : 'OFF'
                )
            ];
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function gotoNodeCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $targetNodeId = $this->getNextParameter($contentArray, false, true);
            if (!$targetNodeId) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Please specify the node id ("nlist [systemid]" for list)')
                    )
                ];
            }
            $targetNode = NULL;
            if (!$this->response) {
                $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
                /** @var NodeRepository $nodeRepo */
                $targetNode = $nodeRepo->find($targetNodeId);
                if (!$targetNode) {
                    $this->response = [
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid node id')
                        )
                    ];
                }
            }
            if (!$this->response && $targetNode) {
                $profile = $this->user->getProfile();
                $currentNode = $profile->getCurrentNode();
                $this->response = $this->movePlayerToTargetNode($resourceId, $profile, NULL, $currentNode, $targetNode);
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function nListCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $targetSystemId = $this->getNextParameter($contentArray, false, true);
            if (!$targetSystemId) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Please specify the system id ("syslist" for list)')
                    )
                ];
            }
            $targetSystem = NULL;
            if (!$this->response) {
                $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
                /** @var SystemRepository $systemRepo */
                $targetSystem = $systemRepo->find($targetSystemId);
                /** @var System $targetSystem */
                if (!$targetSystem) {
                    $this->response = [
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid system id')
                        )
                    ];
                }
            }
            if (!$this->response && $targetSystem) {
                $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
                /** @var NodeRepository $nodeRepo */
                $systemNodes = $nodeRepo->findBySystem($targetSystem);
                $returnMessage = array();
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%-3s|%s</pre>',
                    $this->translate('ID'),
                    $this->translate('TYPE'),
                    $this->translate('LVL'),
                    $this->translate('NAME')
                );
                foreach ($systemNodes as $node) {
                    /** @var Node $node */
                    $returnMessage[] = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-20s|%-3s|%s</pre>',
                        $node->getId(),
                        $node->getNodeType()->getName(),
                        $node->getLevel(),
                        $node->getName()
                    );
                }
                $this->response = [
                    'command' => 'showoutput',
                    'message' => $returnMessage
                ];
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function sysListCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isSuperAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        /* user is superadmin, can see system list */
        if (!$this->response) {
            $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
            /** @var SystemRepository $systemRepo */
            $systems = $systemRepo->findAll();
            $returnMessage = array();
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%s</pre>',
                $this->translate('ID'),
                $this->translate('OWNER'),
                $this->translate('NAME')
            );
            foreach ($systems as $system) {
                /** @var System $system */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-20s|%s</pre>',
                    $system->getId(),
                    ($system->getProfile()) ? $system->getProfile()->getUser()->getUsername() : '---',
                    $system->getName()
                );
            }
            $this->response = [
                'command' => 'showoutput',
                'message' => $returnMessage
            ];
        }
        return $this->response;
    }

}
