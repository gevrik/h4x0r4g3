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
use Zend\Mvc\I18n\Translator;
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
     * SystemService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
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
            $homeNode = $profile->getHomeNode();
            $this->movePlayerToTargetNode(NULL, $profile, NULL, $currentNode, $homeNode);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('You recall to your home node')
                )
            );
            $this->addAdditionalCommand();
            if ($currentNode->getSystem() != $homeNode->getSystem()) {
                $this->addAdditionalCommand('flyto', $homeNode->getSystem()->getGeocoords(), true);
            }
        }
        return $this->response;
    }

}
