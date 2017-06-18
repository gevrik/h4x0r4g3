<?php

/**
 * System Service.
 * The service supplies methods that resolve logic around System objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use TmoAuth\Entity\User;
use Zend\View\Model\ViewModel;

class SystemService extends BaseService
{

    const BASE_MEMORY_VALUE = 2;
    const BASE_STORAGE_VALUE = 4;

    const SYSTEM_STRING = 'system';
    const ADDY_STRING = 'address';
    const MEMORY_STRING = 'memory';
    const STORAGE_STRING = 'storage';

    /**
     * Shows important stats of the current system.
     * @param int $resourceId
     * @return array|bool
     */
    public function showSystemStats($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentSystem = $profile->getCurrentNode()->getSystem();
        /** @var System $currentSystem */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::SYSTEM_STRING, $currentSystem->getName());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::ADDY_STRING, $currentSystem->getAddy());
        $returnMessage[] = sprintf('<pre>%-12s: %s/%s</pre>', self::MEMORY_STRING, $this->getUsedMemory($profile), $this->getSystemMemory($currentSystem));
        $returnMessage[] = sprintf('<pre>%-12s: %s/%s</pre>', self::STORAGE_STRING, $this->getUsedStorage($profile), $this->getSystemStorage($currentSystem));
        $response = array(
            'command' => 'system',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showSystemMap($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentSystem = $profile->getCurrentNode()->getSystem();
        /** @var System $currentSystem */
        $mapArray = [
            'nodes' => [],
            'links' => []
        ];
        $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystem($currentSystem);
        foreach ($nodes as $node) {
            /** @var Node $node */
            $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getType();
            $mapArray['nodes'][] = [
                'name' => (string)$node->getId() . '_' . Node::$lookup[$node->getType()] . '_' . $node->getName(),
                'type' => $group
            ];
            $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($node);
            foreach ($connections as $connection) {
                /** @var Connection $connection */
                $mapArray['links'][] = [
                    'source' => (string)$connection->getSourceNode()->getId() . '_' . Node::$lookup[$connection->getSourceNode()->getType()] . '_' . $connection->getSourceNode()->getName(),
                    'target' => (string)$connection->getTargetNode()->getId() . '_' . Node::$lookup[$connection->getTargetNode()->getType()] . '_' . $connection->getTargetNode()->getName(),
                    'value' => 2,
                    'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                ];
            }
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/partials/map.phtml');
        $view->setVariable('json', json_encode($mapArray));
        $response = array(
            'command' => 'showPanel',
            'type' => 'default',
            'content' => $this->viewRenderer->render($view)
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showAreaMap($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        $mapArray = [
            'nodes' => [],
            'links' => []
        ];
        $nodes = [];
        $nodes[] = $currentNode;
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
        foreach ($connections as $xconnection) {
            /** @var Connection $xconnection */
            $nodes[] = $xconnection->getTargetNode();
        }
        $counter = true;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getType();
            $mapArray['nodes'][] = [
                'name' => (string)$node->getId() . '_' . Node::$lookup[$node->getType()] . '_' . $node->getName(),
                'type' => $group
            ];
            if ($counter) {
                $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($node);
                foreach ($connections as $connection) {
                    /** @var Connection $connection */
                    $mapArray['links'][] = [
                        'source' => (string)$connection->getSourceNode()->getId() . '_' . Node::$lookup[$connection->getSourceNode()->getType()] . '_' . $connection->getSourceNode()->getName(),
                        'target' => (string)$connection->getTargetNode()->getId() . '_' . Node::$lookup[$connection->getTargetNode()->getType()] . '_' . $connection->getTargetNode()->getName(),
                        'value' => 2,
                        'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                    ];
                }
                $counter = false;
            }
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/partials/map.phtml');
        $view->setVariable('json', json_encode($mapArray));
        $response = array(
            'command' => 'showPanel',
            'type' => 'default',
            'content' => $this->viewRenderer->render($view)
        );
        return $response;
    }

    /**
     * Allows a player to recall to their home node.
     * TODO add this as an action that takes time
     * @param int $resourceId
     * @return array|bool
     */
    public function homeRecall($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $response = false;
        // check if they are not already there
        if ($profile->getHomeNode() == $currentNode) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You are already there</pre>')
            );
        }
        /* checks passed, we can now move the player to their home node */
        if (!$response) {
            $profile->setCurrentNode($profile->getHomeNode());
            $this->entityManager->flush($profile);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You recall to your home node</pre>')
            );
        }
        return $response;
    }

}
