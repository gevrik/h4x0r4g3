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
use Ratchet\ConnectionInterface;
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
     * @param int $resourceId
     * @return array|bool
     */
    public function showNodeInfo($resourceId)
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
        $profiles = [];
        foreach ($this->getWebsocketServer()->getClientsData() as $clientId => $xClientData) {
            $requestedProfile = $this->entityManager->find('Netrunners\Entity\Profile', $xClientData['profileId']);
            if($requestedProfile && $requestedProfile != $profile && $requestedProfile->getCurrentNode() == $currentNode) $profiles[] = $requestedProfile;
        }
        if (count($profiles) > 0) $returnMessage[] = sprintf('<pre class="text-users">%s:</pre>', self::USERS_STRING);
        $counter = 0;
        foreach ($profiles as $pprofile) {
            /** @var Profile $pprofile */
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
     * @param int $resourceId
     * @return array|bool
     */
    public function addNode($resourceId)
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
        // only allow owner of system to add nodes
        if ($profile != $currentNode->getSystem()->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < self::RAW_NODE_COST) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a node to the system</pre>')
            );
        }
        // check if we are in a home node, you can't add nodes to a home node
        if (!$response && $currentNode->getType() == Node::ID_HOME) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You can not add nodes to a home node</pre>')
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
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have added a new node to the system for %s credits</pre>', self::RAW_NODE_COST)
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function changeNodeName($resourceId, $contentArray)
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
        $response = false;
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        if (!$parameter) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Please specify a new name for the node (alpha-numeric-only, 32-chars-max)</pre>')
            );
        }
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node name (alpha-numeric only)</pre>')
            );
        }
        // check if max of 32 characters
        if (mb_strlen($parameter) > 32) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node name (32-characters-max)</pre>')
            );
        }
        if (!$response) {
            // turn spaces in name to underscores
            $name = str_replace(' ', '_', $parameter);
            $currentNode->setName($name);
            $this->entityManager->flush($currentNode);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Node name changed to %s</pre>', $name)
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function changeNodeType($resourceId, $contentArray)
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
        $response = false;
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        if (!$parameter) {
            $returnMessage = array();
            $nodeTypes = Node::$lookup;
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Please choose a node type:</pre>');
            foreach ($nodeTypes as $typeId => $typeString) {
                if ($typeId === 0) continue;
                $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-2s|%-18s|%sc</pre>', $typeId, $typeString, Node::$data[$typeId]['cost']);
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
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
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">No such node type</pre>')
            );
        }
        // check a few combinations that are not valid
        if (!$response && $type == Node::ID_HOME) {
            if ($this->countTargetNodesOfType($currentNode, Node::ID_HOME) > 0) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">There is already a home node around this node</pre>')
                );
            }
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < Node::$data[$type]['cost']) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a node to the system</pre>', Node::$data[$type]['cost'])
            );
        }
        if (!$response) {
            $currentCredits = $profile->getCredits();
            $profile->setCredits($currentCredits - Node::$data[$type]['cost']);
            $currentNode->setType($type);
            $currentNode->setName($name);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Node type changed to %s</pre>', $name)
            );
        }
        return $response;
    }

    /**
     * @param $resourceId
     * @return array|bool
     */
    public function editNodeDescription($resourceId)
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
        // only allow owner of system to add nodes
        if ($profile != $currentNode->getSystem()->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        /* checks passed, we can now edit the node */
        if (!$response) {
            $view = new ViewModel();
            $view->setTemplate('netrunners/node/edit-description.phtml');
            $description = $currentNode->getDescription();
            $processedDescription = '';
            if ($description) {
                $processedDescription = htmLawed($description, array('safe'=>1, 'elements'=>'strong, em, strike, u'));
            }
            $view->setVariable('description', $processedDescription);
            $response = array(
                'command' => 'showPanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $response;
    }

    public function saveNodeDescription(ConnectionInterface $conn, $clientData, $content)
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
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        /* checks passed, we can now edit the node */
        if (!$response) {
            $currentNode->setDescription($content);
            $this->entityManager->flush($currentNode);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Node description saved</pre>')
            );
        }
        return $conn->send(json_encode($response));
    }

    /**
     * @param Node $node
     * @param $type
     * @return int
     */
    public function countTargetNodesOfType(Node $node, $type)
    {
        $amount = 0;
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($node);
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            if ($connection->getTargetNode()->getType() == $type) $amount++;
        }
        return $amount;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function removeNode($resourceId)
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
        $response = false;
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        // check if there are still connections to this node
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
        if (!$response && count($connections) > 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Unable to remove node with more than one connection</pre>')
            );
        }
        // check if there are still files in this node
        $files = $this->entityManager->getRepository('Netrunners\Entity\File')->findByNode($currentNode);
        if (!$response && count($files) > 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Unable to remove node which still contains files</pre>')
            );
        }
        // check if there are still other profiles in this node
        $profiles = $this->entityManager->getRepository('Netrunners\Entity\Profile')->findByCurrentNode($currentNode);
        if (!$response && count($profiles) > 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Unable to remove node which still contains other users</pre>')
            );
        }
        // TODO sanity checks for storage/memory/etc
        /* all checks passed, we can now remove the node */
        if (!$response) {
            foreach ($connections as $connection) {
                /** @var Connection $connection */
                $newCurrentNode = $connection->getTargetNode();
                $targetConnection = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNodeAndTargetNode($newCurrentNode, $currentNode);
                $targetConnection = array_shift($targetConnection);
                $sourceConnection = $connection;
            }
            $this->entityManager->remove($targetConnection);
            $this->entityManager->remove($sourceConnection);
            $profile->setCurrentNode($newCurrentNode);
            $this->entityManager->remove($currentNode);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">The node has been removed</pre>')
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function surveyNode($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>', htmLawed($currentNode->getDescription(), array('safe'=>1, 'elements'=>'strong, em, strike, u')));
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function listNodes($resourceId)
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
        $response = false;
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        if (!$response) {
            $returnMessage = array();
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%-3s|%s</pre>', 'id', 'type', 'lvl', 'name');
            $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystem($currentSystem);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%-3s|%s</pre>', $node->getId(), Node::$lookup[$node->getType()], $node->getLevel(), $node->getName());
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function systemConnect($resourceId, $contentArray)
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
        $response = false;
        // check if they are in an io-node
        if ($currentNode->getType() != Node::ID_PUBLICIO && $currentNode->getType() != Node::ID_IO) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You must be in an I/O node to connect to another system</pre>')
            );
        }
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        if (!$response && !$parameter) {
            $returnMessage = array();
            $publicIoNodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findByType(Node::ID_PUBLICIO);
            $returnMessage[] = sprintf('<pre>%-40s|%-12s|%-20s</pre>', 'address', 'id', 'name');
            foreach ($publicIoNodes as $publicIoNode) {
                /** @var Node $publicIoNode */
                $returnMessage[] = sprintf('<pre>%-40s|%-12s|%-20s</pre>', $publicIoNode->getSystem()->getAddy(), $publicIoNode->getId(), $publicIoNode->getName());
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        $addy = $parameter;
        // check if the target system exists
        $targetSystem = $this->entityManager->getRepository('Netrunners\Entity\System')->findByAddy($addy);
        if (!$response && !$targetSystem) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid system address</pre>')
            );
        }
        // now check if the node id exists
        $targetNodeId = array_shift($contentArray);
        $targetNodeId = trim($targetNodeId);
        $targetNodeId = (int)$targetNodeId;
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
        if (!$response && !$targetNode) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node id</pre>')
            );
        }
        if (!$response && ($targetNode->getType() != Node::ID_PUBLICIO && $targetNode->getType() != Node::ID_IO)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node id</pre>')
            );
        }
        if (!$response && ($targetNode->getType() == Node::ID_IO && $targetSystem->getProfile() != $profile)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node id</pre>')
            );
        }
        if (!$response) {
            $profile->setCurrentNode($targetNode);
            $this->entityManager->flush($profile);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have connected to the target system</pre>')
            );
        }
        return $response;
    }

}
