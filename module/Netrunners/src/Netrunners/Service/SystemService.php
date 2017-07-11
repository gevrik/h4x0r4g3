<?php

/**
 * System Service.
 * The service supplies methods that resolve logic around System objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Node;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\NodeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class SystemService extends BaseService
{

    const BASE_MEMORY_VALUE = 2;
    const BASE_STORAGE_VALUE = 4;

    const SYSTEM_STRING = 'system';
    const ADDY_STRING = 'address';
    const MEMORY_STRING = 'memory';
    const STORAGE_STRING = 'storage';

    /**
     * @var NodeRepository
     */
    protected $nodeRepo;

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepo;


    /**
     * SystemService constructor.
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
     * Shows important stats of the current system.
     * @param int $resourceId
     * @return array|bool
     */
    public function showSystemStats($resourceId)
    {
        $this->initService($resourceId);
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            if (!$this->user) return true;
            $profile = $this->user->getProfile();
            $currentSystem = $profile->getCurrentNode()->getSystem();
            $returnMessage = array();
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', $this->translate(self::SYSTEM_STRING), $currentSystem->getName());
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', $this->translate(self::ADDY_STRING), $currentSystem->getAddy());
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', $this->translate(self::MEMORY_STRING), $this->getSystemMemory($currentSystem));
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', $this->translate(self::STORAGE_STRING), $this->getSystemStorage($currentSystem));
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showSystemMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $currentSystem = $profile->getCurrentNode()->getSystem();
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodes = $this->nodeRepo->findBySystem($currentSystem);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getNodeType()->getId();
                $mapArray['nodes'][] = [
                    'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                    'type' => $group
                ];
                $connections = $this->connectionRepo->findBySourceNode($node);
                foreach ($connections as $connection) {
                    /** @var Connection $connection */
                    $mapArray['links'][] = [
                        'source' => (string)$connection->getSourceNode()->getId() . '_' . $connection->getSourceNode()->getNodeType()->getShortName() . '_' . $connection->getSourceNode()->getName(),
                        'target' => (string)$connection->getTargetNode()->getId() . '_' . $connection->getTargetNode()->getNodeType()->getShortName() . '_' . $connection->getTargetNode()->getName(),
                        'value' => 2,
                        'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                    ];
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showpanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showAreaMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if ($this->isSuperAdmin()) {
            return $this->showSystemMap($resourceId);
        }
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $currentNode = $profile->getCurrentNode();
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodes = [];
            $nodes[] = $currentNode;
            $connections = $this->connectionRepo->findBySourceNode($currentNode);
            foreach ($connections as $xconnection) {
                /** @var Connection $xconnection */
                $nodes[] = $xconnection->getTargetNode();
            }
            $counter = true;
            foreach ($nodes as $node) {
                /** @var Node $node */
                $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getNodeType()->getId();
                $mapArray['nodes'][] = [
                    'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                    'type' => $group
                ];
                if ($counter) {
                    $connections = $this->connectionRepo->findBySourceNode($node);
                    foreach ($connections as $connection) {
                        /** @var Connection $connection */
                        $mapArray['links'][] = [
                            'source' => (string)$connection->getSourceNode()->getId() . '_' . $connection->getSourceNode()->getNodeType()->getShortName() . '_' . $connection->getSourceNode()->getName(),
                            'target' => (string)$connection->getTargetNode()->getId() . '_' . $connection->getTargetNode()->getNodeType()->getShortName() . '_' . $connection->getTargetNode()->getName(),
                            'value' => 2,
                            'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                        ];
                    }
                    $counter = false;
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showpanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * Allows a player to recall to their home node.
     * TODO add this as an action that takes time
     * @param int $resourceId
     * @return array|bool
     */
    public function homeRecall($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        // check if they are not already there
        if (!$this->response && $profile->getHomeNode() == $currentNode) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You are already there')
                )
            );
        }
        /* checks passed, we can now move the player to their home node */
        if (!$this->response) {
            $profile->setCurrentNode($profile->getHomeNode());
            $this->entityManager->flush($profile);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('You recall to your home node')
                )
            );
        }
        return $this->response;
    }

}
