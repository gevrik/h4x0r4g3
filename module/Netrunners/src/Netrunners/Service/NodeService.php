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
use Zend\I18n\Validator\Alnum;
use Zend\View\Model\ViewModel;

class NodeService extends BaseService
{

    const NAME_STRING = "name";
    const TYPE_STRING = "type";
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
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
        if (count($connections) > 0) $returnMessage[] = sprintf('<pre class="text-directory">%s:</pre>', self::CONNECTIONS_STRING);
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-directory">%-12s: %s</pre>', $counter, $connection->getTargetNode()->getName());
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
            $node->setType(Node::ID_RAW);
            $this->entityManager->persist($node);
            $sourceConnection = new Connection();
            $sourceConnection->setType(Connection::TYPE_NORMAL);
            $sourceConnection->setLevel(1);
            $sourceConnection->setCreated(new \DateTime());
            $sourceConnection->setSourceNode($currentNode);
            $sourceConnection->setTargetNode($node);
            $this->entityManager->persist($sourceConnection);
            $targetConnection = new Connection();
            $targetConnection->setType(Connection::TYPE_NORMAL);
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

    /**
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
    public function changeNodeName($clientData, $contentArray)
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
        $parameter = trim($parameter);
        if (!$parameter) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Please specify a new name for the node</pre>')
            );
        }
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Permission denied</pre>')
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => false));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Invalid node name (alpha-numeric, no whitespace)</pre>')
            );
        }
        if (!$response) {
            $currentNode->setName($parameter);
            $this->entityManager->flush($currentNode);
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Node name changed to %s</pre>', $parameter)
            );
        }
        return $response;
    }

    /**
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
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
        $parameter = trim($parameter);
        if (!$parameter) {
            $returnMessage = array();
            $nodeTypes = Node::$lookup;
            $returnMessage[] = sprintf('<pre class="text-sysmsg">Please choose a node type:</pre>');
            foreach ($nodeTypes as $typeId => $typeString) {
                if ($typeId === 0) continue;
                $returnMessage[] = sprintf('<pre class="text-sysmsg">%-2s|%-18s|%sc</pre>', $typeId, $typeString, Node::$data[$typeId]['cost']);
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Permission denied</pre>')
            );
        }
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $type = false;
        $name = "";
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
        if (!$response && !$type) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">No such node type</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < Node::$data[$type]['cost']) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need %s credits to add a node to the system</pre>', Node::$data[$type]['cost'])
            );
        }
        if (!$response) {
            $currentCredits = $profile->getCredits();
            $profile->setCredits($currentCredits - Node::$data[$type]['cost']);
            $currentNode->setType($type);
            $currentNode->setName($name);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Node type changed to %s</pre>', $name)
            );
        }
        return $response;
    }

    public function editNodeDescription($clientData)
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
        /* checks passed, we can now edit the node */
        if (!$response) {
            $view = new ViewModel();
            $view->setTemplate('netrunners/node/edit-description.phtml');
            $view->setVariable('description', $currentNode->getDescription());
            $response = array(
                'command' => 'showPanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $response;
    }

}
