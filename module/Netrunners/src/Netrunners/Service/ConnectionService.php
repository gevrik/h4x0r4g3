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
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\NodeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class ConnectionService extends BaseService
{

    const CONNECTION_COST = 10;
    const SECURE_CONNECTION_COST = 50;

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
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function useConnection($resourceId, $contentArray)
    {
        // TODO check for player-set permission (not implemented yet)
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No such connection')
                )
            );
        }
        // check if they can access the connection
        if (!$this->response &&
            ($connection->getType() == Connection::TYPE_CODEGATE && $profile != $currentSystem->getProfile() && !$connection->getisOpen() && !$this->isSuperAdmin())
        ) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Access denied')
                )
            );
        }
        if (!$this->response) {
            $this->response = $this->movePlayerToTargetNode($resourceId, $profile, $connection);
        }
        return $this->response;
    }

    public function scanConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $connection = $this->findConnectionByNameOrNumber($parameter, $currentNode);
        if (!$connection) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No such connection')
                )
            );
        }
        // check if they can access the connection
        if (!$this->response &&
            ($connection->getType() == Connection::TYPE_CODEGATE && $profile != $currentSystem->getProfile() && !$connection->getisOpen())
        ) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Access denied')
                )
            );
        }
        if (!$this->response) {
            $this->response = $this->getWebsocketServer()->getNodeService()->showNodeInfo($resourceId, $connection->getTargetNode());
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function addConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        $parameter = $this->getNextParameter($contentArray, false, true);
        if (!$this->response && !$parameter) {
            $returnMessage = array();
            $returnMessage[] = sprintf(
                '<pre class="text-sysmsg">%s</pre>',
                $this->translate('Please choose the target node:')
            );
            $nodes = $this->nodeRepo->findBySystem($currentSystem);
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%s</pre>',
                $this->translate('node-id'),
                $this->translate('node-name')
            );
            foreach ($nodes as $node) {
                /** @var Node $node */
                if ($node === $currentNode) continue;
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%s</pre>',
                    $node->getId(),
                    $node->getName()
                );
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        // check if they can add connections
        if (!$this->response && $profile != $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if this is a home node
        if (!$this->response && $currentNode->getNodeType()->getId() == NodeType::ID_HOME) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to add a connection to a home node')
                )
            );
        }
        // check if they have enough credits
        if (!$this->response && $profile->getCredits() < self::CONNECTION_COST) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You need %s credits to add a connection to the node</pre>'),
                    self::CONNECTION_COST
                )
            );
        }
        // check if the target node exists
        if (!$this->response) {
            $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $parameter);
            if (!$this->response && !$targetNode) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid target node')
                    )
                );
            }
        }
        /** @var Node $targetNode */
        // check if the target node is the current ndoe
        if (!$this->response && $targetNode === $currentNode) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Invalid target node')
                )
            );
        }
        // check if the target node is in the same system
        if (!$this->response && $targetNode->getSystem() != $currentSystem) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Invalid target node')
                )
            );
        }
        /* all checks passed, we can now add the connection */
        if (!$this->response) {
            $newCredits = $profile->getCredits() - self::CONNECTION_COST;
            $profile->setCredits($newCredits);
            $aconnection = new Connection();
            $aconnection->setType(Connection::TYPE_NORMAL);
            $aconnection->setTargetNode($targetNode);
            $aconnection->setSourceNode($currentNode);
            $aconnection->setCreated(new \DateTime());
            $aconnection->setLevel(1);
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
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">The connection has been created for %s credits</pre>'),
                    self::CONNECTION_COST
                )
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function secureConnection($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        // check if they can add connections
        if (!$this->response && $profile != $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if they have enough credits
        if (!$this->response && $profile->getCredits() < self::CONNECTION_COST) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a connection to the node</pre>'),
                    self::CONNECTION_COST
                )
            );
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
        if (!$this->response && !$connection) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No such connection')
                )
            );
        }
        if (!$this->response) {
            $profile->setCredits($profile->getCredits() - self::SECURE_CONNECTION_COST);
            $connection->setType(Connection::TYPE_CODEGATE);
            $connection->setIsOpen(false);
            $targetnode = $connection->getTargetNode();
            $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($targetnode, $currentNode);
            $targetConnection = array_shift($targetConnection);
            $targetConnection->setType(Connection::TYPE_CODEGATE);
            $targetConnection->setIsOpen(false);
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('The connection has been secured')
                )
            );
        }
        return $this->response;
    }

}
