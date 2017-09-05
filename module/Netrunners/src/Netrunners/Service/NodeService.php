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
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SystemRepository;
use Ratchet\ConnectionInterface;
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
    const NPCS_STRING = "entities";

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
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;

    /**
     * @var array
     */
    public $connectionsChecked = [];

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
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
    }

    /**
     *
     */
    public function initConnectionsChecked()
    {
        $this->connectionsChecked = [];
        return $this;
    }

    /**
     * Shows important information about a node.
     * If no node is given, it will use the profile's current node.
     * @param $resourceId
     * @param Node|NULL $node
     * @return array|bool|false
     */
    public function showNodeInfo($resourceId, Node $node = NULL)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = ($node) ? $node : $profile->getCurrentNode();
        $returnMessage = array();
        // add a note if the node was given (most prolly a scan command)
        if ($node) $returnMessage[] = sprintf(
            '<pre class="text-info">You scan into the node [%s]:</pre>',
            $node->getName()
        );
        // add survey text if option is turned on
        if ($this->getProfileGameOption($profile, GameOption::ID_SURVEY)) $returnMessage[] = $this->getSurveyText($currentNode);
        // get connections and show them if there are any
        $connections = $this->connectionRepo->findBySourceNode($currentNode);
        if (count($connections) > 0) $returnMessage[] = sprintf('<pre class="text-directory">%s:</pre>', self::CONNECTIONS_STRING);
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $addonString = '';
            if ($connection->getType() == Connection::TYPE_CODEGATE) {
                $addonString = ($connection->getisOpen()) ?
                    $this->translate('<span class="text-muted">(codegate) (open)</span>') :
                    $this->translate('<span class="text-addon">(codegate) (closed)</span>');
            }
            $returnMessage[] = sprintf(
                '<pre class="text-directory">%-12s: %s %s</pre>',
                $counter,
                $connection->getTargetNode()->getName(),
                $addonString
            );
        }
        // get files and show them if there are any
        $files = [];
        foreach ($this->fileRepo->findByNode($currentNode) as $fileInstance) {
            /** @var File $fileInstance */
            if (!$fileInstance->getFileType()->getStealthing()) {
                $files[] = $fileInstance;
            }
            else {
                if ($this->canSee($profile, $fileInstance)) $files[] = $fileInstance;
            }
        }
        if (count($files) > 0) $returnMessage[] = sprintf('<pre class="text-executable">%s:</pre>', $this->translate(self::FILES_STRING));
        $counter = 0;
        foreach ($files as $file) {
            /** @var File $file */
            $counter++;
            $returnMessage[] = sprintf(
                '<pre class="text-executable">%-12s: %s%s</pre>',
                $counter, $file->getName(),
                ($file->getIntegrity() < 1) ? $this->translate(' <span class="text-danger">(defunct)</span>') : ''
            );
        }
        // get profiles and show them if there are any
        $profiles = [];
        foreach ($this->getWebsocketServer()->getClientsData() as $clientId => $xClientData) {
            $requestedProfile = $this->entityManager->find('Netrunners\Entity\Profile', $xClientData['profileId']);
            /** @var Profile $requestedProfile */
            if(
                $requestedProfile &&
                $requestedProfile !== $profile &&
                $requestedProfile->getCurrentNode() == $currentNode
            )
            {
                if (!$requestedProfile->getStealthing()) {
                    $profiles[] = $requestedProfile;
                }
                else {
                    if ($this->canSee($profile, $requestedProfile)) $profiles[] = $requestedProfile;
                }
            }
        }
        if (count($profiles) > 0) $returnMessage[] = sprintf('<pre class="text-users">%s:</pre>', $this->translate(self::USERS_STRING));
        $counter = 0;
        foreach ($profiles as $pprofile) {
            /** @var Profile $pprofile */
            $counter++;
            $returnMessage[] = sprintf(
                '<pre class="text-users">%-12s: %s %s %s %s</pre>',
                $counter,
                $pprofile->getUser()->getUsername(),
                ($pprofile->getStealthing()) ? $this->translate('<span class="text-info">[stealthing]</span>') : '',
                ($profile->getFaction()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $profile->getFaction()->getName()) : '',
                ($profile->getGroup()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $profile->getGroup()->getName()) : ''
            );
        }
        // get npcs and show them if there are any
        $npcInstances = $this->npcInstanceRepo->findByNode($currentNode);
        $npcs = [];
        foreach ($npcInstances as $npcInstance) {
            /** @var NpcInstance $npcInstance */
            if (!$npcInstance->getStealthing()) {
                $npcs[] = $npcInstance;
            }
            else {
                if ($this->canSee($profile, $npcInstance)) $npcs[] = $npcInstance;
            }
        }
        if (count($npcs) > 0)  $returnMessage[] = sprintf('<pre class="text-npcs">%s:</pre>', $this->translate(self::NPCS_STRING));
        $counter = 0;
        foreach ($npcs as $npcInstance) {
            /** @var NpcInstance $npcInstance */
            $counter++;
            $returnMessage[] = sprintf(
                '<pre class="text-npcs">%-12s: %s %s %s %s %s</pre>',
                $counter,
                $npcInstance->getName(),
                ($npcInstance->getStealthing()) ? $this->translate('<span class="text-info">[stealthing]</span>') : '',
                ($npcInstance->getProfile()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $npcInstance->getProfile()->getUser()->getUsername()) : '',
                ($npcInstance->getFaction()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $npcInstance->getFaction()->getName()) : '',
                ($npcInstance->getGroup()) ? sprintf($this->translate('<span class="text-info">[%s]</span>'), $npcInstance->getGroup()->getName()) : ''
            );
        }
        // prepare and return response
        $this->response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        $this->addAdditionalCommand();
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return array|bool|false
     */
    public function enterMode($resourceId, $command, $contentArray = [])
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $this->getWebsocketServer()->setConfirm($resourceId, $command, $contentArray);
            switch ($command) {
                default:
                    break;
                case 'upgradenode':
                    $node = $this->upgradeNodeChecks();
                    $nodeType = $node->getNodeType();
                    $upgradeCost = $nodeType->getCost() * pow($node->getLevel(), $node->getLevel() + 1);
                    if (!$this->response) {
                        $this->response = [
                            'command' => 'enterconfirmmode',
                            'message' => sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-white">You need %s credits to upgrade this node - Please confirm this action:</pre>'),
                                $upgradeCost
                            )
                        ];
                    }
                    break;
                case 'nodetype':
                    $nodeType = $this->changeNodeTypeChecks($contentArray);
                    if (!$this->response) {
                        $currentNode = $profile->getCurrentNode();
                        if ($currentNode->getLevel() > 1) {
                            $this->response = [
                                'command' => 'enterconfirmmode',
                                'message' => sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">You need [%s] credits to change the node type - <span class="text-danger">the current node [%s] will be reset to level 1</span></pre>'),
                                    $nodeType->getCost(),
                                    $currentNode->getNodeType()->getName()
                                )
                            ];
                        }
                        else {
                            $this->response = [
                                'command' => 'enterconfirmmode',
                                'message' => sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">You need [%s] credits to change the node type - the current node type is [%s]</span></pre>'),
                                    $nodeType->getCost(),
                                    $currentNode->getNodeType()->getName()
                                )
                            ];
                        }
                    }
                    break;
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function upgradeNode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        $node = false;
        if (!$this->response) {
            $node = $this->upgradeNodeChecks();
        }
        if (!$this->response && $node) {
            $nodeType = $node->getNodeType();
            $upgradeCost = $nodeType->getCost() * pow($node->getLevel(), $node->getLevel() + 1);
            $profile->setCredits($profile->getCredits() - $upgradeCost);
            $node->setLevel($node->getLevel()+1);
            $this->entityManager->flush();
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have upgraded [%s] to level [%s]</pre>'),
                $node->getName(),
                $node->getLevel()
            );
            $this->response = [
                'command' => 'showmessage',
                'message' => $message
            ];
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has upgraded the node to level [%s]</pre>'),
                $this->user->getUsername(),
                $node->getLevel()
            );
            $this->messageEveryoneInNode($node, $message, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @return Node|NULL
     */
    private function upgradeNodeChecks()
    {
        $profile = $this->user->getProfile();
        $node = $profile->getCurrentNode();
        if (!$this->response && $node->getSystem()->getProfile() !== $profile) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            ];
        }
        if (!$this->response && $node->getLevel() > 3) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('This node is already at max level')
                )
            ];
        }
        $nodeType = $node->getNodeType();
        $upgradeCost = $nodeType->getCost() * pow($node->getLevel(), $node->getLevel() + 1);
        if (!$this->response) {
            if ($upgradeCost > $profile->getCredits()) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to upgrade this node</pre>'),
                        $upgradeCost
                    )
                ];
            }
        }
        return $node;
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
        if (!$this->response && $profile !== $currentNode->getSystem()->getProfile()) {
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
        // check if the system has reached its max size
        $currentSystem = $currentNode->getSystem();
        $nodeamount = $this->nodeRepo->countBySystem($currentSystem);
        if (!$this->response && $nodeamount >= $currentSystem->getMaxSize()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('System has reached its maximum size')
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
                        $this->translate('You do not have enough CPU rating to add another node to this system - upgrade CPU nodes or add new CPU nodes')
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have added a new node to the system for %s credits</pre>'),
                    self::RAW_NODE_COST
                )
            );
            $this->addAdditionalCommand();
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] added a new node to the system</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function claimCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
        $this->response = $this->isActionBlocked($resourceId);

        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function exploreCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response && $currentSystem->getId() != $this->getServerSetting(self::SETTING_WILDERNESS_SYSTEM_ID)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You must be in Wilderspace to explore')
                )
            );
        }
        // check if they can explore here - node might be claimed by another player
        if (!$this->response && $currentNode->getProfile() && $currentNode->getProfile() != $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to explore in nodes that other users have claimed')
                )
            );
        }
        // check if they can explore - node might already be at max level (dead-end) - level 10 nodes in wilderspace are the homes of true AI
        if (!$this->response && $currentNode->getLevel() >= 10) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to explore in max level nodes')
                )
            );
        }
        /* all checks passed, we can explore */
        if (!$this->response) {
            // exploration is difficult, uses advanced coding and advanced networking for its skill check
            $advancedCoding = $this->getSkillRating($profile, Skill::ID_ADVANCED_CODING);
            $advancedNetworking = $this->getSkillRating($profile, Skill::ID_ADVANCED_NETWORKING);
            $chance = floor(($advancedCoding + $advancedNetworking) / 2);
            // node level makes it harder
            $chance -= ($currentNode->getLevel() * 10);
            if (mt_rand(1, 100) > $chance) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You fail to find any hidden connections')
                    )
                );
            }
            else {
                // player has found a hidden connection
                $excludedNodeTypes = [NodeType::ID_CPU, NodeType::ID_HOME, NodeType::ID_IO, NodeType::ID_PUBLICIO, NodeType::ID_RECRUITMENT];
                $exploredNodeType = $this->getRandomNodeType($excludedNodeTypes);
                $exploredNode =  new Node();
                $exploredNode->setSystem($currentSystem);
                $exploredNode->setProfile(NULL);
                $exploredNode->setCreated(new \DateTime());
                $exploredNode->setDescription($exploredNodeType->getDescription());
                $newLevel = (mt_rand(1, 100) > 90) ? $currentNode->getLevel() + 1 : $currentNode->getLevel();
                $exploredNode->setLevel($newLevel);
                $exploredNode->setName($exploredNodeType->getName());
                $exploredNode->setNodeType($exploredNodeType);
                $exploredNode->setNomob(false);
                $exploredNode->setNopvp(false);
                $this->entityManager->persist($exploredNode);
                $sourceConnection = new Connection();
                $sourceConnection->setType(Connection::TYPE_NORMAL);
                $sourceConnection->setLevel($newLevel);
                $sourceConnection->setCreated(new \DateTime());
                $sourceConnection->setSourceNode($currentNode);
                $sourceConnection->setTargetNode($exploredNode);
                $sourceConnection->setIsOpen(false);
                $this->entityManager->persist($sourceConnection);
                $targetConnection = new Connection();
                $targetConnection->setType(Connection::TYPE_NORMAL);
                $targetConnection->setLevel(1);
                $targetConnection->setCreated(new \DateTime());
                $targetConnection->setSourceNode($exploredNode);
                $targetConnection->setTargetNode($currentNode);
                $targetConnection->setIsOpen(false);
                $this->entityManager->persist($targetConnection);
                $this->entityManager->flush();
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('You have found a hidden service')
                    )
                );
                $this->addAdditionalCommand();
            }
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] is searching for a hidden service connection</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param array $excludedNodeTypes
     * @return null|NodeType
     */
    private function getRandomNodeType($excludedNodeTypes = [])
    {
        $nodeTypeId = mt_rand(1, 18);
        while (in_array($nodeTypeId, $excludedNodeTypes)) {
            $nodeTypeId = mt_rand(1, 18);
        }
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', $nodeTypeId);
        /** @var NodeType $nodeType */
        return $nodeType;
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
        if (!$this->response && $profile !== $currentSystem->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if only alphanumeric
        $this->stringChecker($parameter);
        if (!$this->response) {
            // turn spaces in name to underscores
            $name = str_replace(' ', '_', $parameter);
            $currentNode->setName($name);
            $this->entityManager->flush($currentNode);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">Node name changed to %s</pre>'),
                    $name
                )
            );
            $this->addAdditionalCommand();
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has changed the node name to [%s]</pre>'),
                $this->user->getUsername(),
                $name
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile->getId());
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
        $this->response = $this->isActionBlocked($resourceId);
        /* node types can be given by name or number, so we need to handle both */
        $nodeType = $this->changeNodeTypeChecks($contentArray);
        if (!$this->response) {
            // TODO a lot more stuff needs to be done depending on the existing node-type
            $currentCredits = $profile->getCredits();
            $profile->setCredits($currentCredits - $nodeType->getCost());
            $currentNode->setNodeType($nodeType);
            $currentNode->setLevel(1);
            $currentNode->setName($nodeType->getShortName());
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">Node type changed to %s</pre>'),
                    $nodeType->getName()
                )
            );
            $this->addAdditionalCommand();
        }
        return $this->response;
    }

    /**
     * @param $contentArray
     * @return NodeType
     */
    private function changeNodeTypeChecks($contentArray)
    {
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
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
                    '<pre style="white-space: pre-wrap;" class="text-white">%-2s|%-18s|%sc</pre>',
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
        if (!$this->response && $profile !== $currentSystem->getProfile()) {
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
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
        // check if it is a recruitment node but not a faction or group system
        if (
            !$this->response &&
            $nodeType->getId() == NodeType::ID_RECRUITMENT &&
            (!$currentSystem->getGroup() || !$currentSystem->getFaction())
        )
        {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Recruitment nodes can only be created in group or faction systems')
                )
            );
        }
        // check if this is a cpu node and the last one...
        $cpuCount = $this->nodeRepo->countBySystemAndType($currentSystem, $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU));
        if (
            !$this->response &&
            $currentNode->getNodeType()->getId() == NodeType::ID_CPU &&
            (int)$cpuCount < 2
        )
        {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove the last CPU node of this system')
                )
            );
        }
        return $nodeType;
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
        if (!$this->response && $profile !== $currentNode->getSystem()->getProfile()) {
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
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] is editing the node</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile->getId());
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
        if ($profile !== $currentNode->getSystem()->getProfile()) {
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
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
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
        // TODO needs a full rework - as a lot of stuff needs to happen on node removal
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        // check if they are allowed to remove nodes
        if (!$this->response && $profile !== $currentSystem->getProfile()) {
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
        $fileCount = $this->fileRepo->countByNode($currentNode);
        if (!$this->response && $fileCount > 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove node which still contains files')
                )
            );
        }
        // check if there are still npcs in this node
        $npcCount = $this->npcInstanceRepo->countByNode($currentNode);
        if (!$this->response && $npcCount > 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove node which still contains entities')
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
        // check if this is the home node of someone
        $homeProfiles = $this->profileRepo->findBy([
            'homeNode' => $currentNode
        ]);
        if (!$this->response && count($homeProfiles) > 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove a node which is another user\'s home node')
                )
            );
        }
        // check if this is the home node of some npc
        $homeNpcs = $this->npcInstanceRepo->findBy([
            'homeNode' => $currentNode
        ]);
        if (!$this->response && count($homeNpcs) > 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove a node which is still an entity\'s home node')
                )
            );
        }
        // check if this is a cpu node and the last one...
        $cpuCount = $this->nodeRepo->countBySystemAndType($currentSystem, $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU));
        if (
            !$this->response &&
            $currentNode->getNodeType()->getId() == NodeType::ID_CPU &&
            (int)$cpuCount < 2
        )
        {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to remove the last CPU node of this system')
                )
            );
        }
        // TODO sanity checks for storage/memory/etc - lots of things
        /* all checks passed, we can now remove the node */
        if (!$this->response) {
            $newCurrentNode = NULL;
            $connection = array_shift($connections);
            /** @var Connection $connection */
            $newCurrentNode = $connection->getTargetNode();
            $targetConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($newCurrentNode, $currentNode);
            /** @var Connection $targetConnection */
            $this->entityManager->remove($targetConnection);
            $this->entityManager->remove($connection);
            $this->movePlayerToTargetNode($resourceId, $profile, NULL, $currentNode, $newCurrentNode);
            $currentNodeName = $currentNode->getName();
            $this->entityManager->remove($currentNode);
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('The node has been removed')
                )
            );
            $this->response['additionalCommands'][] = [
                'command' => 'ls',
                'content' => false
            ];
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">The adjacent node [%s] has been removed</pre>'),
                $currentNodeName
            );
            $this->messageEveryoneInNode($newCurrentNode, $message, $profile->getId());
        }
        $this->connectionsChecked = [];
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
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] is looking around</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile->getId());
        }
        return $this->response;
    }

    /**
     * Recursive function that checks if the given node is still connected to a node of the given node type.
     * @param Node $node
     * @param Connection|NULL $ignoredConnection
     * @param array $nodeTypeIds
     * @return bool
     */
    public function nodeStillConnectedToNodeType(
        Node $node,
        Connection $ignoredConnection = NULL,
        $nodeTypeIds = []
    )
    {
        $nodeTypeFound = false;
        foreach ($this->connectionRepo->findBySourceNode($node) as $connection) {
            /** @var Connection $connection */
            if ($connection == $ignoredConnection) {
                continue;
            }
            if (in_array($connection->getId(), $this->connectionsChecked)) {
                continue;
            }
            $this->connectionsChecked[] = $connection->getId();
            $targetNode = $connection->getTargetNode();
            if (in_array($targetNode->getNodeType()->getId(), $nodeTypeIds)) {
                $nodeTypeFound = true;
            }
            if ($nodeTypeFound) {
                break;
            }
            else {
                $targetConnection = $this->connectionRepo->findOneBy([
                    'sourceNode' => $targetNode,
                    'targetNode' => $node
                ]);
                $nodeTypeFound = $this->nodeStillConnectedToNodeType($targetNode, $targetConnection, $nodeTypeIds);
            }
        }
        return $nodeTypeFound;
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
        if (!$this->response && $profile !== $currentSystem->getProfile()) {
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
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%-40s|%-12s|%-20s</pre>',
                $this->translate('SYSTEM'),
                $this->translate('ADDRESS'),
                $this->translate('ID'),
                $this->translate('NAME')
            );
            foreach ($publicIoNodes as $publicIoNode) {
                /** @var Node $publicIoNode */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-32s|%-40s|%-12s|%-20s</pre>',
                    $publicIoNode->getSystem()->getName(),
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
            $targetNode = NULL;
            if (!$this->response && !$targetSystem) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid system address')
                    )
                );
            }
            if (!$this->response) {
                // now check if the node id exists
                $targetNodeId = $this->getNextParameter($contentArray, false, true);
                if (!$targetNodeId) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Please specify the target node id')
                        )
                    );
                }
                if (!$this->response) {
                    $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
                    if (!$targetNode) {
                        $this->response = array(
                            'command' => 'showmessage',
                            'message' => sprintf(
                                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                                $this->translate('Invalid target node id')
                            )
                        );
                    }
                }
            }
            if (
                !$this->response && $targetNode &&
                ($targetNode->getNodeType()->getId() != NodeType::ID_PUBLICIO && $targetNode->getNodeType()->getId() != NodeType::ID_IO)
            ) {
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
            if (!$this->response && $targetNode && $targetNode == $currentNode) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You are already there')
                    )
                );
            }
            if (!$this->response && $targetNode) {
                /** @var Node $targetNode */
                $this->movePlayerToTargetNode(NULL, $profile, NULL, $currentNode, $targetNode);
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('You have connected to the target system')
                    )
                );
                $this->addAdditionalCommand();
                $this->addAdditionalCommand('flyto', $targetNode->getSystem()->getGeocoords(), true);
            }
        }
        return $this->response;
    }

}
