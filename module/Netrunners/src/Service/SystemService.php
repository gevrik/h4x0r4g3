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
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\NodeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class SystemService extends BaseService
{

    const HOME_RECALL_TIMER = 10;
    const BASE_MEMORY_VALUE = 2;
    const BASE_STORAGE_VALUE = 4;

    const SYSTEM_STRING = 'system';
    const ADDY_STRING = 'address';
    const MEMORY_STRING = 'memory';
    const STORAGE_STRING = 'storage';
    const AVG_NODE_LVL_STRING = 'avg-level';
    const PROFILE_OWNER_STRING = 'profile';
    const GROUP_OWNER_STRING = 'group';
    const FACTION_OWNER_STRING = 'faction';

    /**
     * @var SystemGeneratorService
     */
    protected $systemGeneratorService;

    /**
     * SystemService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param SystemGeneratorService $systemGeneratorService
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        SystemGeneratorService $systemGeneratorService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->systemGeneratorService = $systemGeneratorService;
    }

    /**
     * Shows important stats of the current system.
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showSystemStats($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $currentSystem = $profile->getCurrentNode()->getSystem();
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $headerMessage = sprintf('%-12s: %s', $this->translate(self::SYSTEM_STRING), $currentSystem->getName());
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $returnMessage = [];
        $returnMessage[] = sprintf('%-12s: %s', $this->translate(self::ADDY_STRING), $currentSystem->getAddy());
        $returnMessage[] = sprintf('%-12s: %s', $this->translate(self::MEMORY_STRING), $this->getSystemMemory($currentSystem));
        $returnMessage[] = sprintf('%-12s: %s', $this->translate(self::STORAGE_STRING), $this->getSystemStorage($currentSystem));
        $returnMessage[] = sprintf('%-12s: %s', $this->translate(self::AVG_NODE_LVL_STRING), $nodeRepo->getAverageNodeLevelOfSystem($currentSystem));
        $this->gameClientResponse->addMessages($returnMessage, GameClientResponse::CLASS_WHITE);
        $addonMessage = [];
        if ($currentSystem->getProfile()) $addonMessage[] = sprintf('%-12s: %s', $this->translate(self::PROFILE_OWNER_STRING), $currentSystem->getProfile()->getUser()->getUsername());
        if ($currentSystem->getGRoup()) $addonMessage[] = sprintf('%-12s: %s', $this->translate(self::GROUP_OWNER_STRING), $currentSystem->getGroup()->getName());
        if ($currentSystem->getFaction()) $addonMessage[] = sprintf('%-12s: %s', $this->translate(self::FACTION_OWNER_STRING), $currentSystem->getFaction()->getName());
        if (!empty($addonMessage)) $this->gameClientResponse->addMessages($addonMessage, GameClientResponse::CLASS_ADDON);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function homeRecall($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are not already there
        if ($profile->getHomeNode() == $currentNode) {
            $message = $this->translate('You are already there');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* checks passed, we can now move the player to their home node */
        $completionDate = new \DateTime();
        $completionDate->add(new \DateInterval('PT' . self::HOME_RECALL_TIMER . 'S'));
        $actionData = [
            'command' => 'homerecall',
            'completion' => $completionDate,
            'blocking' => true,
            'fullblock' => true,
            'parameter' => []
        ];
        $this->getWebsocketServer()->setClientActionData($resourceId, $actionData);
        $message = $this->translate('You recall to your home node - please wait');
        $this->gameClientResponse->addOption(GameClientResponse::OPT_TIMER, self::HOME_RECALL_TIMER);
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param bool $sendNow
     * @return bool|GameClientResponse
     */
    public function homeRecallAction($resourceId, $sendNow = true)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // check if they are not already there
        if ($profile->getHomeNode() == $currentNode) {
            $message = $this->translate('You are already there');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* checks passed, we can now move the player to their home node */
        $homeNode = $profile->getHomeNode();
        $this->movePlayerToTargetNodeNew(NULL, $profile, NULL, $currentNode, $homeNode);
        if ($currentNode->getSystem() != $homeNode->getSystem()) {
            $flytoResponse = new GameClientResponse($resourceId);
            $flytoResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
            $flytoResponse->addOption(GameClientResponse::OPT_CONTENT, explode(',',$homeNode->getSystem()->getGeocoords()));
            $flytoResponse->send();
        }
        $message = $this->translate('Recalling to your home node');
        $this->gameClientResponse
            ->setSilent(true)
            ->addMessage($message, GameClientResponse::CLASS_SUCCESS)
            ->addOption(GameClientResponse::OPT_CLEARDEADLINE, true);
        $this->gameClientResponse->send();
        $this->updateMap($resourceId);
        return $this->showNodeInfoNew($resourceId, NULL, $sendNow);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function changeGeocoords($resourceId, $contentArray)
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
        // check if they can change the coords
        if ($currentSystem->getProfile() !== $profile) {
            $message = $this->translate('Permission denied');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if the player has sent a faction argument
        $this->getWebsocketServer()->setClientData($resourceId, 'awaitingcoords', true);
        $faction = $this->getNextParameter($contentArray, false, false, true, true);
        $param = NULL;
        switch ($faction) {
            default:
                $message = $this->translate("System-coords have been updated - you will now be taken to the new location");
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                $newCoords = $this->clientData->geocoords;
                $currentSystem->setGeocoords($newCoords);
                $this->entityManager->flush($currentSystem);
                $flytoResponse = new GameClientResponse($resourceId);
                $flytoResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
                $flytoResponse->addOption(GameClientResponse::OPT_CONTENT, $newCoords);
                $flytoResponse->send();
                return $this->gameClientResponse->send();
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
            $message = $this->translate("System-coords will be randomly generated within the requested region");
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            $getCoordsResponse = new GameClientResponse($resourceId);
            $getCoordsResponse->setCommand(GameClientResponse::COMMAND_GETRANDOMGEOCOORDS);
            $getCoordsResponse->addOption(GameClientResponse::OPT_CONTENT, $param);
            $getCoordsResponse->send();
            return $this->gameClientResponse->send();
        }
        $message = $this->translate("Unable to change the coords of this system at the moment");
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_DANGER)->send();
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
