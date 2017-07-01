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
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SystemRepository;
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
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $returnMessage = array();
        $connections = $connectionRepo->findBySourceNode($currentNode);
        if (count($connections) > 0) $returnMessage[] = sprintf('<pre class="text-directory">%s:</pre>', self::CONNECTIONS_STRING);
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-directory">%-12s: %s</pre>', $counter, $connection->getTargetNode()->getName());
        }
        $files = $fileRepo->findByNode($currentNode);
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
        // check if they are busy
        $response = $this->isActionBlocked($resourceId);
        // only allow owner of system to add nodes
        if (!$response && $profile != $currentNode->getSystem()->getProfile()) {
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
        if (!$response && $currentNode->getNodeType()->getId() == NodeType::ID_HOME) {
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
            // create the new node
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_RAW);
            /** @var NodeType $nodeType */
            $node = new Node();
            $node->setCreated(new \DateTime());
            $node->setLevel(1);
            $node->setName($nodeType->getName());
            $node->setSystem($currentNode->getSystem());
            $node->setNodeType($nodeType);
            $this->entityManager->persist($node);
            $sourceConnection = new Connection();
            $sourceConnection->setType(Connection::TYPE_NORMAL);
            $sourceConnection->setLevel(1);
            $sourceConnection->setCreated(new \DateTime());
            $sourceConnection->setSourceNode($currentNode);
            $sourceConnection->setTargetNode($node);
            $sourceConnection->setIsOpen(false);
            $this->entityManager->persist($sourceConnection);
            $targetConnection = new Connection();
            $targetConnection->setType(Connection::TYPE_NORMAL);
            $targetConnection->setLevel(1);
            $targetConnection->setCreated(new \DateTime());
            $targetConnection->setSourceNode($node);
            $targetConnection->setTargetNode($currentNode);
            $targetConnection->setIsOpen(false);
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
        $response = $this->isActionBlocked($resourceId);
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        if (!$response && !$parameter) {
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
        $response = $this->isActionBlocked($resourceId);
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        if (!$response && !$parameter) {
            $returnMessage = array();
            $nodeTypes = $this->entityManager->getRepository('Netrunners\Entity\NodeType')->findAll();
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Please choose a node type:</pre>');
            foreach ($nodeTypes as $nodeType) {
                /** @var NodeType $nodeType */
                if ($nodeType->getId() == NodeType::ID_RAW) continue;
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-2s|%-18s|%sc</pre>',
                    $nodeType->getId(),
                    $nodeType->getName(),
                    $nodeType->getCost()
                );
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
        if ($searchByNumber) {
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', $parameter);
        }
        else {
            $nodeType = $this->entityManager->getRepository('Netrunners\Entity\NodeType')->findOneBy([
                'name' => $parameter
            ]);
        }
        if (!$response && !$nodeType) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">No such node type</pre>')
            );
        }
        // check a few combinations that are not valid
        if (!$response && $nodeType->getId() == NodeType::ID_HOME) {
            if ($this->countTargetNodesOfType($currentNode, NodeType::ID_HOME) > 0) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">There is already a home node around this node</pre>')
                );
            }
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < $nodeType->getCost()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a node to the system</pre>',
                    $nodeType->getCost()
                )
            );
        }
        if (!$response) {
            $currentCredits = $profile->getCredits();
            $profile->setCredits($currentCredits - $nodeType->getCost());
            $currentNode->setNodeType($nodeType);
            $currentNode->setName($nodeType->getShortName());
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Node type changed to %s</pre>', $nodeType->getName())
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
        $response = $this->isActionBlocked($resourceId, true);
        // only allow owner of system to add nodes
        if (!$response && $profile != $currentNode->getSystem()->getProfile()) {
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
                'command' => 'showpanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $response;
    }

    /**
     * @param ConnectionInterface $conn
     * @param $clientData
     * @param $content
     * @return bool|ConnectionInterface
     */
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
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $amount = 0;
        $connections = $connectionRepo->findBySourceNode($node);
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            if ($connection->getTargetNode()->getNodeType()->getId() == $type) $amount++;
        }
        return $amount;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function removeNode($resourceId)
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
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
        $response = $this->isActionBlocked($resourceId);
        // check if they can change the type
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        // check if there are still connections to this node
        $connections = $connectionRepo->findBySourceNode($currentNode);
        if (!$response && count($connections) > 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Unable to remove node with more than one connection</pre>')
            );
        }
        // check if there are still files in this node
        $files = $fileRepo->findByNode($currentNode);
        if (!$response && count($files) > 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Unable to remove node which still contains files</pre>')
            );
        }
        // check if there are still other profiles in this node
        $profiles = $profileRepo->findByCurrentNode($currentNode);
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
                $targetConnection = $connectionRepo->findBySourceNodeAndTargetNode($newCurrentNode, $currentNode);
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
        $response = $this->isActionBlocked($resourceId, true);
        if (!$response) {
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
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function listNodes($resourceId)
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
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
        $response = $this->isActionBlocked($resourceId, true);
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
            $nodes = $nodeRepo->findBySystem($currentSystem);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-20s|%-3s|%s</pre>',
                    $node->getId(),
                    $node->getNodeType()->getName(),
                    $node->getLevel(),
                    $node->getName()
                );
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
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $response = $this->isActionBlocked($resourceId);
        // check if they are in an io-node
        if (!$response && $currentNode->getNodeType()->getId() != NodeType::ID_PUBLICIO && $currentNode->getNodeType()->getId() != NodeType::ID_IO) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You must be in an I/O node to connect to another system</pre>')
            );
        }
        // get parameter
        $parameter = $this->getNextParameter($contentArray);
        if (!$response && !$parameter) {
            $returnMessage = array();
            $publicIoNodes = $nodeRepo->findByType(NodeType::ID_PUBLICIO);
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
        $targetSystem = $systemRepo->findByAddy($addy);
        if (!$response && !$targetSystem) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid system address</pre>')
            );
        }
        // now check if the node id exists
        $targetNodeId = $this->getNextParameter($contentArray, false, true);
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
        /** @var Node $targetNode */
        if (!$response && !$targetNode) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node id</pre>')
            );
        }
        if (!$response && ($targetNode->getNodeType()->getId() != NodeType::ID_PUBLICIO && $targetNode->getNodeType()->getId() != NodeType::ID_IO)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid node id</pre>')
            );
        }
        if (!$response && ($targetNode->getNodeType()->getId() == NodeType::ID_IO && $targetSystem->getProfile() != $profile)) {
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
