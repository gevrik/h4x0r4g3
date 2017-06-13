<?php

/**
 * Node Service.
 * The service supplies methods that resolve logic around Node objects.
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

class NodeService extends BaseService
{

    const NAME_STRING = "name";
    const LEVEL_STRING = "level";
    const CONNECTIONS_STRING = "connections";
    const FILES_STRING = "files";
    const USERS_STRING = "users";

    const RAW_NODE_COST = 50;

    /**
     * Shows important information about a node.
     * @param $clientData
     * @return array|bool
     */
    public function showNodeInfo($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre class="text-sysmsg">%-12s: %s</pre>', SystemService::SYSTEM_STRING, $currentSystem->getName());
        $returnMessage[] = sprintf('<pre class="text-sysmsg">%-12s: %s</pre>', self::NAME_STRING, $currentNode->getName());
        $returnMessage[] = sprintf('<pre class="text-sysmsg">%-12s: %s</pre>', self::LEVEL_STRING, $currentNode->getLevel());
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
        if (count($connections) > 0) $returnMessage[] = sprintf('<pre class="text-directory">%s:</pre>', self::CONNECTIONS_STRING);
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-directory">%-12s: %s</pre>', $counter, $connection->getName());
        }
        $files = $this->entityManager->getRepository('Netrunners\Entity\File')->findByNode($currentNode);
        if (count($files) > 0) $returnMessage[] = sprintf('<pre class="text-executable">%s:</pre>', self::FILES_STRING);
        $counter = 0;
        foreach ($files as $file) {
            /** @var File $file */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-executable">%-12s: %s</pre>', $counter, $file->getName());
        }
        $profiles = $this->entityManager->getRepository('Netrunners\Entity\Profile')->findByCurrentNode($currentNode, $profile);
        if (count($profiles) > 0) $returnMessage[] = sprintf('<pre class="text-users">%s:</pre>', self::USERS_STRING);
        $counter = 0;
        foreach ($profiles as $pprofile) {
            /** @var Profile $pprofile */
            if ($pprofile === $profile) continue;
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-users">%-12s: %s</pre>', $counter, $pprofile->getUser()->getUsername());
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * Adds a new node to the current system.
     * @param $clientData
     * @return array|bool
     */
    public function addNode($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $response = false;
        // only allow owner of system to add nodes
        if ($profile != $currentNode->getSystem()->getProfile()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Permission denied</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < self::RAW_NODE_COST) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need %s credits to add a node to the system</pre>')
            );
        }
        /* checks passed, we can now add the node */
        if (!$response) {
            // take creds from user
            $newCredits = $profile->getCredits() - self::RAW_NODE_COST;
            $profile->setCredits($newCredits);
            $node = new Node();
            $node->setCreated(new \DateTime());
            $node->setLevel(1);
            $node->setName(Node::STRING_RAW);
            $node->setSystem($currentNode->getSystem());
            $node->setType(NULL);
            $this->entityManager->persist($node);
            $sourceConnection = new Connection();
            $sourceConnection->setType(Connection::TYPE_NORMAL);
            $sourceConnection->setName($node->getName());
            $sourceConnection->setLevel(1);
            $sourceConnection->setCreated(new \DateTime());
            $sourceConnection->setSourceNode($currentNode);
            $sourceConnection->setTargetNode($node);
            $this->entityManager->persist($sourceConnection);
            $targetConnection = new Connection();
            $targetConnection->setType(Connection::TYPE_NORMAL);
            $targetConnection->setName($currentNode->getName());
            $targetConnection->setLevel(1);
            $targetConnection->setCreated(new \DateTime());
            $targetConnection->setSourceNode($node);
            $targetConnection->setTargetNode($currentNode);
            $this->entityManager->persist($targetConnection);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You have added a new node to the system for %s credits</pre>', self::RAW_NODE_COST)
            );
        }
        return $response;
    }

    public function changeNodeType($clientData, $contentArray)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        $response = false;
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = array_shift($contentArray);
        if (!$parameter) {
            $returnMessage = array();
            $nodeTypes = Node::$lookup;
            $returnMessage[] = sprintf('<pre class="text-sysmsg">Please choose a node type:</pre>');
            foreach ($nodeTypes as $typeId => $typeString) {
                $returnMessage[] = sprintf('<pre class="text-sysmsg">%-12s: %s</pre>', $typeId, $typeString);
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        else {
            $searchByNumber = false;
            if (is_numeric($parameter)) {
                $searchByNumber = true;
            }
            $type = false;
            if ($searchByNumber) {
                if (isset(Node::$lookup[$parameter])) {
                    $type = $parameter;
                    $name = Node::$lookup[$parameter];
                }
            }
            else {
                if (isset(Node::$revLookup[$parameter])) {
                    $type = Node::$revLookup[$parameter];
                    $name = Node::$lookup[$type];
                }
            }
            if (!$type) {
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'sysmsg',
                    'message' => sprintf('<pre style="white-space: pre-wrap;">No such node type</pre>')
                );
            }
            else {
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'sysmsg',
                    'message' => sprintf('<pre style="white-space: pre-wrap;">Node type changed to %s</pre>', $name)
                );
            }
        }
        return $response;
    }

}
