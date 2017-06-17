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
use TmoAuth\Entity\User;

class ConnectionService extends BaseService
{

    const CONNECTION_COST = 10;

    const SECURE_CONNECTION_COST = 50;

    /**
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
    public function useConnection($clientData, $contentArray)
    {
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
        $parameter = array_shift($contentArray);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
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
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "No such connection")
            );
        } else {
            $profile->setCurrentNode($connection->getTargetNode());
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
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
    public function addConnection($clientData, $contentArray)
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
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        $parameter = (int)$parameter;
        if (!$parameter) {
            $returnMessage = array();
            $returnMessage[] = sprintf('<pre class="text-sysmsg">Please choose the target node:</pre>');
            $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystem($currentSystem);
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
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Permission denied</pre>')
            );
        }
        // check if this is a home node
        if (!$response && $currentNode->getType() == Node::ID_HOME) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Unable to add a connection to a home node</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < self::CONNECTION_COST) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need %s credits to add a connection to the node</pre>')
            );
        }
        // check if the target node exists
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $parameter);
        if (!$response && !$targetNode) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Invalid target node</pre>')
            );
        }
        // check if the target node is the current ndoe
        if (!$response && $targetNode == $currentNode) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Invalid target node</pre>')
            );
        }
        // check if the target node is in the same system
        if (!$response && $targetNode->getSystem() != $currentSystem) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Invalid target node</pre>')
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
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">The connection has been created for %s credits</pre>', self::CONNECTION_COST)
            );
        }
        return $response;
    }

    public function secureConnection($clientData, $contentArray)
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
        // check if they can add connections
        if (!$response && $profile != $currentSystem->getProfile()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">Permission denied</pre>')
            );
        }
        // check if they have enough credits
        if (!$response && $profile->getCredits() < self::CONNECTION_COST) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need %s credits to add a connection to the node</pre>')
            );
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = array_shift($contentArray);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
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
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "No such connection")
            );
        }
        if (!$response) {
            $profile->setCredits($profile->getCredits() - self::SECURE_CONNECTION_COST);
            $connection->setType(Connection::TYPE_CODEGATE);
            $targetnode = $connection->getTargetNode();
            $targetConnection = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNodeAndTargetNode($targetnode, $currentNode);
            $targetConnection = array_shift($targetConnection);
            $targetConnection->setType(Connection::TYPE_CODEGATE);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "The connection has been secured")
            );
        }
        return $response;
    }

}
