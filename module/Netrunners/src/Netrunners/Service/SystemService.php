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
     * @param $clientData
     * @return array|bool
     */
    public function showSystemStats($clientData)
    {
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
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::MEMORY_STRING, $this->getSystemMemory($currentSystem));
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::STORAGE_STRING, $this->getSystemStorage($currentSystem));
        $response = array(
            'command' => 'system',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @param System $system
     * @return int
     */
    public function getSystemMemory(System $system)
    {
        $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystemAndType($system, Node::ID_MEMORY);
        $total = 0;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $total += $node->getLevel() * self::BASE_MEMORY_VALUE;
        }
        return $total;
    }

    /**
     * @param System $system
     * @return int
     */
    public function getSystemStorage(System $system)
    {
        $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystemAndType($system, Node::ID_STORAGE);
        $total = 0;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $total += $node->getLevel() * self::BASE_STORAGE_VALUE;
        }
        return $total;
    }

    public function showSystemMap($clientData)
    {
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
            // if this is the current node, add to beginning of array
//            if ($node == $profile->getCurrentNode()) {
//                array_unshift($mapArray['nodes'], [
//                    'id' => (string)$node->getId() . '_' . Node::$lookup[$node->getType()] . '_' . $node->getName(),
//                    'group' => 99
//                ]);
//            }
//            else {
//                $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getType();
//                $mapArray['nodes'][] = [
//                    'name' => (string)$node->getId() . '_' . Node::$lookup[$node->getType()] . '_' . $node->getName(),
//                    'type' => $group
//                ];
//            }
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

}
