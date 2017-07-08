<?php

/**
 * Node Service.
 * The service supplies methods that resolve logic around Node objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Entity\GameOption;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SystemRepository;
use Ratchet\ConnectionInterface;
use Zend\I18n\Validator\Alnum;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class NodeService extends BaseService
{

    const NAME_STRING = "name";
    const TYPE_STRING = "type";
    const LEVEL_STRING = "level";
    const CONNECTIONS_STRING = "connections";
    const FILES_STRING = "files";
    const USERS_STRING = "users";

    const RAW_NODE_COST = 50;
    const MAX_NODES_MULTIPLIER = 10;

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;

    /**
     * @var NodeRepository
     */
    protected $nodeRepo;

    /**
     * @var SystemRepository
     */
    protected $systemRepo;


    /**
     * NodeService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        $this->nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $this->systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
    }

    /**
     * Shows important information about a node.
     * @param int $resourceId
     * @return array|bool
     */
    public function showNodeInfo($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $returnMessage = array();
        // add survey text if option is turned on
        if ($this->getProfileGameOption($profile, GameOption::ID_SURVEY)) $returnMessage[] = $this->getSurveyText($currentNode);
        // get connections and show them if there are any
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
        if (count($connections) > 0) $returnMessage[] = sprintf('<pre class="text-directory">%s:</pre>', self::CONNECTIONS_STRING);
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-directory">%-12s: %s</pre>', $counter, $connection->getTargetNode()->getName());
        }
        // get files and show them if there are any
        $files = $this->fileRepo->findByNode($currentNode);
        if (count($files) > 0) $returnMessage[] = sprintf('<pre class="text-executable">%s:</pre>', $this->translate(self::FILES_STRING));
        $counter = 0;
        foreach ($files as $file) {
            /** @var File $file */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-executable">%-12s: %s</pre>', $counter, $file->getName());
        }
        // get profiles and show them if there are any
        $profiles = [];
        foreach ($this->getWebsocketServer()->getClientsData() as $clientId => $xClientData) {
            $requestedProfile = $this->entityManager->find('Netrunners\Entity\Profile', $xClientData['profileId']);
            if($requestedProfile && $requestedProfile != $profile && $requestedProfile->getCurrentNode() == $currentNode) $profiles[] = $requestedProfile;
        }
        if (count($profiles) > 0) $returnMessage[] = sprintf('<pre class="text-users">%s:</pre>', $this->translate(self::USERS_STRING));
        $counter = 0;
        foreach ($profiles as $pprofile) {
            /** @var Profile $pprofile */
            $counter++;
            $returnMessage[] = sprintf('<pre class="text-users">%-12s: %s</pre>', $counter, $pprofile->getUser()->getUsername());
        }
        // prepare and return response
        $this->response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $this->response;
    }

    /**
     * Adds a new node to the current system.
     * @param int $resourceId
     * @return array|bool
     */
    public function addNode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // check if they are busy
        $this->response = $this->isActionBlocked($resourceId);
        // only allow owner of system to add nodes
        if (!$this->response && $profile != $currentNode->getSystem()->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if they have enough credits
        if (!$this->response && $profile->getCredits() < self::RAW_NODE_COST) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a node to the system</pre>'),
                    self::RAW_NODE_COST
                )
            );
        }
        // check if we are in a home node, you can't add nodes to a home node
        if (!$this->response && $currentNode->getNodeType()->getId() == NodeType::ID_HOME) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You can not add nodes to a home node')
                )
            );
        }
        // check if there are enough cpus to support the new node
        if (!$this->response) {
            $currentSystem = $currentNode->getSystem();
            $cpus = $this->nodeRepo->findBySystemAndType($currentSystem, NodeType::ID_CPU);
            $amountCpus = count($cpus);
            $cpuRating = 0;
            foreach ($cpus as $cpu) {
                /** @var Node $cpu */
                $cpuRating += $cpu->getLevel();
            }
            $maxNodes = $cpuRating * self::MAX_NODES_MULTIPLIER;
            $amountNodes = $this->nodeRepo->countBySystem($currentSystem) - $amountCpus;
            if ($amountNodes >= $maxNodes) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You do not have enough CPU rating to add another node to this system')
                    )
                );
            }
        }
        /* checks passed, we can now add the node */
        if (!$this->response) {
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
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have added a new node to the system for %s credits</pre>'),
                    self::RAW_NODE_COST
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
    public function changeNodeName($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        if (!$this->response && !$parameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a new name for the node (alpha-numeric-only, 32-chars-max)')
                )
            );
        }
        // check if they can change the type
        if (!$this->response && $profile != $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$this->response && !$validator->isValid($parameter)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid node name (alpha-numeric only)')
                )
            );
        }
        // check if max of 32 characters
        if (mb_strlen($parameter) > 32) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid node name (32-characters-max)')
                )
            );
        }
        if (!$this->response) {
            // turn spaces in name to underscores
            $name = str_replace(' ', '_', $parameter);
            $currentNode->setName($name);
            $this->entityManager->flush($currentNode);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">Node name changed to %s</pre>'),
                    $name
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
    public function changeNodeType($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        /* node types can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        if (!$this->response && !$parameter) {
            $returnMessage = array();
            $nodeTypes = $this->entityManager->getRepository('Netrunners\Entity\NodeType')->findAll();
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('Please choose a node type:')
            );
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
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        // check if they can change the type
        if (!$this->response && $profile != $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        if ($searchByNumber) {
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', $parameter);
        }
        else {
            $nodeType = $this->entityManager->getRepository('Netrunners\Entity\NodeType')->findOneBy([
                'name' => $parameter
            ]);
        }
        if (!$this->response && !$nodeType) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No such node type')
                )
            );
        }
        // check a few combinations that are not valid
        if (!$this->response && $nodeType->getId() == NodeType::ID_HOME) {
            if ($this->countTargetNodesOfType($currentNode, NodeType::ID_HOME) > 0) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('There is already a home node around this node')
                    )
                );
            }
        }
        // check if they have enough credits
        if (!$this->response && $profile->getCredits() < $nodeType->getCost()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to add a node to the system</pre>'),
                    $nodeType->getCost()
                )
            );
        }
        if (!$this->response) {
            $currentCredits = $profile->getCredits();
            $profile->setCredits($currentCredits - $nodeType->getCost());
            $currentNode->setNodeType($nodeType);
            $currentNode->setName($nodeType->getShortName());
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">Node type changed to %s</pre>'),
                    $nodeType->getName()
                )
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool
     */
    public function editNodeDescription($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId, true);
        // only allow owner of system to add nodes
        if (!$this->response && $profile != $currentNode->getSystem()->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        /* checks passed, we can now edit the node */
        if (!$this->response) {
            $view = new ViewModel();
            $view->setTemplate('netrunners/node/edit-description.phtml');
            $description = $currentNode->getDescription();
            $processedDescription = '';
            if ($description) {
                $processedDescription = htmLawed($description, array('safe'=>1, 'elements'=>'strong, em, strike, u'));
            }
            $view->setVariable('description', $processedDescription);
            $this->response = array(
                'command' => 'showpanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $content
     * @return bool|ConnectionInterface
     */
    public function saveNodeDescription($resourceId, $content)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // only allow owner of system to add nodes
        if ($profile != $currentNode->getSystem()->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        /* checks passed, we can now edit the node */
        if (!$this->response) {
            $currentNode->setDescription($content);
            $this->entityManager->flush($currentNode);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Node description saved')
                )
            );
        }
        return $this->response;
    }

    /**
     * @param Node $node
     * @param $type
     * @return int
     */
    private function countTargetNodesOfType(Node $node, $type)
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
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        // check if they can change the type
        if (!$this->response && $profile != $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if there are still connections to this node
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
        if (!$this->response && count($connections) > 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove node with more than one connection')
                )
            );
        }
        // check if there are still files in this node
        $files = $this->fileRepo->findByNode($currentNode);
        if (!$this->response && count($files) > 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove node which still contains files')
                )
            );
        }
        // check if there are still other profiles in this node
        $profiles = $this->profileRepo->findByCurrentNode($currentNode);
        if (!$this->response && count($profiles) > 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove node which still contains other users')
                )
            );
        }
        // TODO sanity checks for storage/memory/etc
        /* all checks passed, we can now remove the node */
        if (!$this->response) {
            foreach ($connections as $connection) {
                /** @var Connection $connection */
                $newCurrentNode = $connection->getTargetNode();
                $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($newCurrentNode, $currentNode);
                $targetConnection = array_shift($targetConnection);
                $sourceConnection = $connection;
            }
            $this->entityManager->remove($targetConnection);
            $this->entityManager->remove($sourceConnection);
            $profile->setCurrentNode($newCurrentNode);
            $this->entityManager->remove($currentNode);
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('The node has been removed')
                )
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function surveyNode($resourceId)
    {
        $this->initService($resourceId);
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            if (!$this->user) return true;
            $profile = $this->user->getProfile();
            $currentNode = $profile->getCurrentNode();
            $returnMessage = array();
            $returnMessage[] = $this->getSurveyText($currentNode);
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param Node $currentNode
     * @return string
     */
    private function getSurveyText(Node $currentNode)
    {
        if (!$currentNode->getDescription()) {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-muted">%s</pre>',
                wordwrap($this->translate('This is a raw Cyberspace node, white walls, white ceiling, white floor - no efforts have been made to customize it.'), 120)
            );
        }
        else {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-survey">%s</pre>',
                wordwrap(htmLawed($currentNode->getDescription(), ['safe'=>1, 'elements'=>'strong, em, strike, u']), 120)
            );
        }
        return $returnMessage;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function listNodes($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId, true);
        // check if they can change the type
        if (!$this->response && $profile != $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        if (!$this->response) {
            $returnMessage = array();
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%-3s|%s</pre>',
                $this->translate('ID'),
                $this->translate('TYPE'),
                $this->translate('LVL'),
                $this->translate('NAME')
            );
            $nodes = $this->nodeRepo->findBySystem($currentSystem);
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
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function systemConnect($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        // check if they are in an io-node
        if (!$this->response && $currentNode->getNodeType()->getId() != NodeType::ID_PUBLICIO && $currentNode->getNodeType()->getId() != NodeType::ID_IO) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You must be in an I/O node to connect to another system')
                )
            );
        }
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        if (!$this->response && !$parameter) {
            $returnMessage = array();
            $publicIoNodes = $this->nodeRepo->findByType(NodeType::ID_PUBLICIO);
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-40s|%-12s|%-20s</pre>',
                $this->translate('ADDRESS'),
                $this->translate('ID'),
                $this->translate('NAME')
            );
            foreach ($publicIoNodes as $publicIoNode) {
                /** @var Node $publicIoNode */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-40s|%-12s|%-20s</pre>',
                    $publicIoNode->getSystem()->getAddy(),
                    $publicIoNode->getId(),
                    $publicIoNode->getName()
                );
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        if (!$this->response) {
            $addy = $parameter;
            // check if the target system exists
            $targetSystem = $this->systemRepo->findByAddy($addy);
            if (!$this->response && !$targetSystem) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid system address')
                    )
                );
            }
            // now check if the node id exists
            $targetNodeId = $this->getNextParameter($contentArray, false, true);
            $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
            /** @var Node $targetNode */
            if (!$this->response && !$targetNode) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid node id')
                    )
                );
            }
            if (!$this->response && ($targetNode->getNodeType()->getId() != NodeType::ID_PUBLICIO && $targetNode->getNodeType()->getId() != NodeType::ID_IO)) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid node id')
                    )
                );
            }
            if (!$this->response && ($targetNode->getNodeType()->getId() == NodeType::ID_IO && $targetSystem->getProfile() != $profile)) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid node id')
                    )
                );
            }
            if (!$this->response) {
                $profile->setCurrentNode($targetNode);
                $this->entityManager->flush($profile);
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('You have connected to the target system')
                    )
                );
            }
        }
        return $this->response;
    }

}
