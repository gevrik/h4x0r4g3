<?php

/**
 * Connection Service.
 * The service supplies methods that resolve logic around Connection objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\NodeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class ConnectionService extends BaseService
{

    const CONNECTION_COST = 10;
    const SECURE_CONNECTION_COST = 50;

    /**
     * @var NodeService
     */
    protected $nodeService;

    /**
     * @var NodeRepository
     */
    protected $nodeRepo;

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepo;


    /**
     * ConnectionService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param NodeService $nodeService
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        NodeService $nodeService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->nodeService = $nodeService;
        $this->nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|\Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function useConnection($resourceId, $contentArray)
    {
        // TODO check for player-set permission (not implemented yet)
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter (connection name or number)
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        // check if they can access the connection
        if (
            $connection->getType() == Connection::TYPE_CODEGATE &&
            !$connection->getisOpen() &&
            !$this->canAccess($profile, $currentSystem)
        ) {
            return $this->gameClientResponse->addMessage($this->translate('Access denied'))->send();
        }
        $this->movePlayerToTargetNodeNew($resourceId, $profile, $connection);
        $this->updateMap($resourceId);
        if ($this->clientData->partyId) {
            $partyData = $this->getWebsocketServer()->getParty($this->clientData->partyId);
            if ($partyData['leader'] == $profile->getId()) {
                foreach ($partyData['members'] as $memberProfileId => $memberData) {
                    if ($memberData['following']) {
                        /** @var Profile $memberProfile */
                        $memberProfile = $this->entityManager->find('Netrunners\Entity\Profile', $memberProfileId);
                        if (!$memberProfile->getCurrentResourceId()) continue;
                        if ($memberProfile->getCurrentNode() != $currentNode) continue;
                        if ($this->isActionBlockedNew($memberProfile->getCurrentResourceId())) continue;
                        $this->movePlayerToTargetNodeNew(NULL, $memberProfile, $connection);
                        $this->updateMap($memberProfile->getCurrentResourceId(), $memberProfile);
                        $memberResponse = $this->showNodeInfoNew($memberProfile->getCurrentResourceId(), NULL, false);
                        $memberResponse->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND)->setSilent(true)->send();
                    }
                }
            }
        }
        return $this->showNodeInfoNew($resourceId, NULL, true);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function removeConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they can remove connections
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter and check if this is a valid connection
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        // check if there is still a connection to an io node
        $this->nodeService->initConnectionsChecked();
        $stillConnectedToIo = $this->nodeService->nodeStillConnectedToNodeType(
            $currentNode,
            $connection,
            [NodeType::ID_PUBLICIO, NodeType::ID_IO]
        );
        if (!$stillConnectedToIo) {
            $message = $this->translate('This node would no longer be connected to an io-node after removing this connection');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $this->nodeService->initConnectionsChecked();
        // check the same for the target node
        $this->nodeService->initConnectionsChecked();
        $targetNode = $connection->getTargetNode();
        $reversedConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($targetNode, $currentNode);
        $stillConnectedToIo = $this->nodeService->nodeStillConnectedToNodeType(
            $targetNode,
            $reversedConnection,
            [NodeType::ID_PUBLICIO, NodeType::ID_IO]
        );
        if (!$stillConnectedToIo) {
            $message = $this->translate('The target node would no longer be connected to an io-node after removing this connection');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $this->nodeService->initConnectionsChecked();
        /* all seems good, we can remove the connection */
        if (!$this->response && $connection && $reversedConnection) {
            $this->entityManager->remove($connection);
            $this->entityManager->remove($reversedConnection);
            $this->entityManager->flush();
            $message = sprintf(
                $this->translate('You removed the connection to [%s]'),
                ($targetNode) ? $targetNode->getName() : $this->translate('unknown')
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            $this->updateMap($resourceId);
            $sourceMessage = sprintf(
                $this->translate('The connection to [%s] was removed'),
                ($targetNode) ? $targetNode->getName() : $this->translate('unknown')
            );
            $this->messageEveryoneInNodeNew($currentNode, $sourceMessage, GameClientResponse::CLASS_MUTED, NULL, $profile->getId());
            $targetMessage = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">The connection to [%s] was removed</pre>'),
                $currentNode->getName()
            );
            $this->messageEveryoneInNodeNew($targetNode, $targetMessage, GameClientResponse::CLASS_MUTED, NULL, $profile->getId());
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function scanConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        // check if they can access the connection
        if (
            $connection->getType() == Connection::TYPE_CODEGATE && $profile !== $currentSystem->getProfile() && !$connection->getisOpen()
        ) {
            return $this->gameClientResponse->addMessage($this->translate('Access denied'))->send();
        }
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is scanning into [%s]'),
            $this->user->getUsername(),
            $connection->getTargetNode()->getName()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->nodeService->showNodeInfoNew($resourceId, $connection->getTargetNode(), true);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function closeConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        if ($connection->getType() != Connection::TYPE_CODEGATE) {
            return $this->gameClientResponse->addMessage($this->translate('That is not a codegate'))->send();
        }
        if ($connection->getType() == Connection::TYPE_CODEGATE && !$connection->getisOpen()) {
            return $this->gameClientResponse->addMessage($this->translate('That codegate is not open'))->send();
        }
        // all good - can close
        $connection->setIsOpen(false);
        $this->entityManager->flush($connection);
        $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($connection->getTargetNode(), $connection->getSourceNode());
        $targetConnection->setIsOpen(false);
        $this->entityManager->flush($targetConnection);
        $message = sprintf(
            $this->translate('You have closed the connection to [%s]'),
            $connection->getTargetNode()->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has closed the connection to [%s]</pre>'),
            $this->user->getUsername(),
            $connection->getTargetNode()->getName()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        // inform other players in target node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">Someone has opened the connection to [%s]</pre>'),
            $currentNode->getName()
        );
        $this->messageEveryoneInNodeNew($connection->getTargetNode(), $message, GameClientResponse::CLASS_MUTED, NULL, [], true);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function openConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        if ($connection->getType() != Connection::TYPE_CODEGATE) {
            return $this->gameClientResponse->addMessage($this->translate('That is not a codegate'))->send();
        }
        if ($connection->getType() == Connection::TYPE_CODEGATE && $connection->getisOpen()) {
            return $this->gameClientResponse->addMessage($this->translate('That codegate is already open'))->send();
        }
        $checker = $this->checkSystemPermission($profile, $currentNode->getSystem());
        if ($checker !== false) { // TODO add check for wilderspace claimed nodes
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // all good - can open
        $connection->setIsOpen(true);
        $this->entityManager->flush($connection);
        $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($connection->getTargetNode(), $connection->getSourceNode());
        $targetConnection->setIsOpen(true);
        $this->entityManager->flush($targetConnection);
        $message = sprintf(
            $this->translate('You have opened the connection to [%s]'),
            $connection->getTargetNode()->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has opened the connection to [%s]'),
            $this->user->getUsername(),
            $connection->getTargetNode()->getName()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        // inform other players in target node
        $message = sprintf(
            $this->translate('Someone has opened the connection to [%s]'),
            $currentNode->getName()
        );
        $this->messageEveryoneInNodeNew($connection->getTargetNode(), $message, GameClientResponse::CLASS_MUTED, NULL, [], true);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function addConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they can add connections
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        $parameter = $this->getNextParameter($contentArray, false, true);
        if (!$parameter) {
            $this->gameClientResponse->addMessage($this->translate('Please choose the target node:'));
            $nodes = $this->nodeRepo->findBySystem($currentSystem);
            $returnMessage = sprintf(
                '%-11s|%s',
                $this->translate('NODE-ID'),
                $this->translate('NODE-NAME')
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
            foreach ($nodes as $node) {
                /** @var Node $node */
                if ($node === $currentNode) continue;
                $returnMessage = sprintf(
                    '%-11s|%s',
                    $node->getId(),
                    $node->getName()
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
            }
            return $this->gameClientResponse->send();
        }
        // check if this is a home node
        if ($currentNode->getNodeType()->getId() == NodeType::ID_HOME) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to add a connection to a home node'))->send();
        }
        // check if they have enough credits
        if ($profile->getCredits() < self::CONNECTION_COST) {
            $message = sprintf(
                $this->translate('You need %s credits to add a connection to the node'),
                self::CONNECTION_COST
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if the target node exists
        $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $parameter);
        if (!$targetNode) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid target node'))->send();
        }
        /** @var Node $targetNode */
        // check if the target node is the current node
        if ($targetNode === $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid target node'))->send();
        }
        // check if the target node is in the same system
        if ($targetNode->getSystem() != $currentSystem) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid target node'))->send();
        }
        // check if there already is a connection between the current node and the target node
        if ($this->connectionRepo->findBySourceNodeAndTargetNode($currentNode, $targetNode)) {
            return $this->gameClientResponse->addMessage($this->translate('There already exists a connection between those nodes'))->send();
        }
        // check if this is a home node
        if ($targetNode->getNodeType()->getId() == NodeType::ID_HOME) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to add a connection to a home node'))->send();
        }
        /* all checks passed, we can now add the connection */
        $newCredits = $profile->getCredits() - self::CONNECTION_COST;
        $profile->setCredits($newCredits);
        $aconnection = new Connection();
        $aconnection->setType(Connection::TYPE_NORMAL);
        $aconnection->setTargetNode($targetNode);
        $aconnection->setSourceNode($currentNode);
        $aconnection->setCreated(new \DateTime());
        $aconnection->setLevel($currentNode->getLevel());
        $aconnection->setIsOpen(false);
        $this->entityManager->persist($aconnection);
        $bconnection = new Connection();
        $bconnection->setType(Connection::TYPE_NORMAL);
        $bconnection->setTargetNode($currentNode);
        $bconnection->setSourceNode($targetNode);
        $bconnection->setCreated(new \DateTime());
        $bconnection->setLevel(1);
        $bconnection->setIsOpen(false);
        $this->entityManager->persist($bconnection);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('The connection has been created for %s credits'),
            self::CONNECTION_COST
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] added a new connection'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function secureConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they can add connections
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // check if they have enough credits
        if ($profile->getCredits() < self::CONNECTION_COST) {
            $message = sprintf(
                $this->translate('You need %s credits to add a connection to the node'),
                self::CONNECTION_COST
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
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
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        if ($connection->getType() == Connection::TYPE_CODEGATE) {
            return $this->gameClientResponse->addMessage($this->translate('This connection is already secure'))->send();
        }
        $profile->setCredits($profile->getCredits() - self::SECURE_CONNECTION_COST);
        $connection->setType(Connection::TYPE_CODEGATE);
        $connection->setIsOpen(false);
        $targetnode = $connection->getTargetNode();
        $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($targetnode, $currentNode);
        /** @var Connection $targetConnection */
        $targetConnection->setType(Connection::TYPE_CODEGATE);
        $targetConnection->setIsOpen(false);
        $this->entityManager->flush();
        $this->gameClientResponse->addMessage($this->translate('The connection has been secured'), GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has secured the connection to [%s]'),
            $this->user->getUsername(),
            $targetConnection->getTargetNode()->getName()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        // inform other players in target node
        $message = sprintf(
            $this->translate('Someone has secured the connection to [%s]'),
            $currentNode->getName()
        );
        $this->messageEveryoneInNodeNew($targetnode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function unsecureConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they can add connections
        $checker = $this->checkSystemPermission($profile, $currentSystem);
        if ($checker !== false) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
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
            return $this->gameClientResponse->addMessage($this->translate('No such connection'))->send();
        }
        if ($connection->getType() != Connection::TYPE_CODEGATE) {
            return $this->gameClientResponse->addMessage($this->translate('This connection is not secure'))->send();
        }
        $connection->setType(Connection::TYPE_NORMAL);
        $connection->setIsOpen(false);
        $targetnode = $connection->getTargetNode();
        $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($targetnode, $currentNode);
        /** @var Connection $targetConnection */
        $targetConnection->setType(Connection::TYPE_NORMAL);
        $targetConnection->setIsOpen(true);
        $this->entityManager->flush();
        $this->gameClientResponse->addMessage($this->translate('The connection is no longer secured'), GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has unsecured the connection to [%s]'),
            $this->user->getUsername(),
            $targetnode->getName()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        // inform other players in target node
        $message = sprintf(
            $this->translate('Someone has unsecured the connection to [%s]'),
            $currentNode->getName()
        );
        $this->messageEveryoneInNodeNew($targetnode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        return $this->gameClientResponse->send();
    }

}
