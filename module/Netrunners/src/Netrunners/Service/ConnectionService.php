<?php

/**
 * Connection Service.
 * The service supplies methods that resolve logic around Connection objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Connection;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\NodeRepository;
use TmoAuth\Entity\User;

class ConnectionService extends BaseService
{

    const CONNECTION_COST = 10;

    const SECURE_CONNECTION_COST = 50;


    public function useConnection($resourceId, $contentArray)
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        // TODO check for codegate and permission
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
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $connectionRepo->findBySourceNode($currentNode);
        $connection = false;
        if ($searchByNumber) {
            if (isset($connections[$parameter - 1])) {
                $connection = $connections[$parameter - 1];
            }
        } else {
            foreach ($connections as $pconnection) {
                /** @var Connection $pconnection */
                if ($pconnection->getTargetNode()->getName() == $parameter) {
                    $connection = $pconnection;
                    break;
                }
            }
        }
        if (!$connection) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>', "No such connection")
            );
        }
        // check if they can access the connection
        if (!$response && ($connection->getType() == Connection::TYPE_CODEGATE && $profile != $currentSystem->getProfile())) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>', "Access denied")
            );
        }
        if (!$response) {
            $targetNode = $connection->getTargetNode();
            $sourceNode = $connection->getSourceNode();
            // message everyone in source node
            $messageText = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has used the connection to %s</pre>', $profile->getUser()->getUsername(), $targetNode->getName());
            $message = array(
                'command' => 'showmessageprepend',
                'message' => $messageText
            );
            $this->messageEveryoneInNode($sourceNode, $message, $profile);
            $profile->setCurrentNode($connection->getTargetNode());
            $messageText = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has connected to this node from %s</pre>', $profile->getUser()->getUsername(), $sourceNode->getName());
            $message = array(
                'command' => 'showmessageprepend',
                'message' => $messageText
            );
            $this->messageEveryoneInNode($targetNode, $message, $profile);
            $this->entityManager->flush($profile);
            $response = array(
                'command' => 'cd',
                'type' => 'default',
                'message' => false
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function addConnection($resourceId, $contentArray)
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
        $response = false;
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        if (!$parameter) {
            $returnMessage = array();
            $returnMessage[] = sprintf('<pre class="text-sysmsg">Please choose the target node:</pre>');
            $nodes = $nodeRepo->findBySystem($currentSystem);
            foreach ($nodes as $node) {
                /** @var Node $node */
                if ($node === $currentNode) continue;
                $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-11s|%s</pre>', $node->getId(), $node->getName());
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        // check if they can add connections
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        // check if this is a home node
        if (!$response && $currentNode->getType() == Node::ID_HOME) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Unable to add a connection to a home node</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < self::CONNECTION_COST) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You need %s credits to add a connection to the node</pre>')
            );
        }
        // check if the target node exists
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $parameter);
        if (!$response && !$targetNode) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid target node</pre>')
            );
        }
        // check if the target node is the current ndoe
        if (!$response && $targetNode == $currentNode) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Invalid target node</pre>')
            );
        }
        // check if the target node is in the same system
        if (!$response && $targetNode->getSystem() != $currentSystem) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid target node</pre>')
            );
        }
        /* all checks passed, we can now add the connection */
        if (!$response) {
            $newCredits = $profile->getCredits() - self::CONNECTION_COST;
            $profile->setCredits($newCredits);
            $aconnection = new Connection();
            $aconnection->setType(Connection::TYPE_NORMAL);
            $aconnection->setTargetNode($targetNode);
            $aconnection->setSourceNode($currentNode);
            $aconnection->setCreated(new \DateTime());
            $aconnection->setLevel(1);
            $this->entityManager->persist($aconnection);
            $bconnection = new Connection();
            $bconnection->setType(Connection::TYPE_NORMAL);
            $bconnection->setTargetNode($currentNode);
            $bconnection->setSourceNode($targetNode);
            $bconnection->setCreated(new \DateTime());
            $bconnection->setLevel(1);
            $this->entityManager->persist($bconnection);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">The connection has been created for %s credits</pre>', self::CONNECTION_COST)
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function secureConnection($resourceId, $contentArray)
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
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
        // check if they can add connections
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < self::CONNECTION_COST) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a connection to the node</pre>')
            );
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $connectionRepo->findBySourceNode($currentNode);
        $connection = false;
        if ($searchByNumber) {
            if (isset($connections[$parameter - 1])) {
                $connection = $connections[$parameter - 1];
            }
        } else {
            foreach ($connections as $pconnection) {
                /** @var Connection $pconnection */
                if ($pconnection->getTargetNode()->getName() == $parameter) {
                    $connection = $pconnection;
                    break;
                }
            }
        }
        if (!$response && !$connection) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>', "No such connection")
            );
        }
        if (!$response) {
            $profile->setCredits($profile->getCredits() - self::SECURE_CONNECTION_COST);
            $connection->setType(Connection::TYPE_CODEGATE);
            $targetnode = $connection->getTargetNode();
            $targetConnection = $connectionRepo->findBySourceNodeAndTargetNode($targetnode, $currentNode);
            $targetConnection = array_shift($targetConnection);
            $targetConnection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>', "The connection has been secured")
            );
        }
        return $response;
    }

}
