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

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function changeGeocoords($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId, true);
        // check if they can change the coords
        if (!$this->response && $currentSystem->getProfile() !== $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        /* checks passed, we can now get new coords for the system */
        if (!$this->response) {
            // check if the player has sent a faction argument
            $this->getWebsocketServer()->setClientData($resourceId, 'awaitingcoords', true);
            $faction = $this->getNextParameter($contentArray, false, false, true, true);
            $param = NULL;
            $command = NULL;
            $message = NULL;
            $additionalCommand = NULL;
            $additionalContent = NULL;
            switch ($faction) {
                default:
                    $command = 'showmessage';
                    $message = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate("System-coords have been updated - you will now be taken to the new location")
                    );
                    $additionalCommand = 'flyto';
                    $newCoords = $this->clientData->geocoords;
                    $additionalContent = $newCoords;
                    $currentSystem->setGeocoords($newCoords);
                    $this->entityManager->flush($currentSystem);
                    break;
                case 'random':
                    $param = 0;
                    break;
                case 'aztechnology':
                case 'gangers':
                    $param = 1;
                    break;
                case 'eurocorp':
                case 'mafia':
                    $param = 2;
                    break;
                case 'asiancoalition':
                case 'yakuza':
                    $param = 3;
                    break;
            }
            if ($param !== NULL) {
                $command = 'showmessage';
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate("System-coords will be randomly generated within the requested region")
                );
                $additionalCommand = 'getrandomgeocoords';
                $additionalContent = $param;
            }
            if ($command && $message) {
                $this->response = array(
                    'command' => $command,
                    'message' => $message
                );
                if ($additionalCommand && $additionalContent) {
                    $this->addAdditionalCommand($additionalCommand, $additionalContent, true);
                }
            }
            else {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate("Unable to change the coords of this system at the moment")
                    )
                );
            }
        }
        return $this->response;
    }

    public function createSystem($resourceId, $contentArray)
    {
        // TODO finish this
    }

    public function renameSystem($resourceId, $contentArray)
    {
        // TODO finish this
    }

}
