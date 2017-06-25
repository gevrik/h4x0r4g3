<?php

/**
 * Milkrun Service.
 * The service supplies methods that resolve logic around Milkruns.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Faction;
use Netrunners\Entity\Milkrun;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Repository\FactionRepository;
use Netrunners\Repository\MilkrunInstanceRepository;
use TmoAuth\Entity\User;
use Zend\View\Model\ViewModel;

class MilkrunService extends BaseService
{

    const TILE_TYPE_UNKNOWN = 0;
    const TILE_TYPE_KEY = 1;
    const TILE_TYPE_TARGET = 2;
    const TILE_TYPE_ICE = 3;
    const TILE_TYPE_SPECIAL = 4;
    const TILE_TYPE_EMPTY = 5;

    const TILE_SUBTYPE_SPECIAL_CREDITS = 1;
    const TILE_SUBTYPE_SPECIAL_SNIPPETS = 2;

    /**
     * @param $resourceId
     * @return array|bool
     */
    public function requestMilkrun($resourceId)
    {
        var_dump('start');
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = $this->isActionBlocked($resourceId);
        if (!$response && $profile->getCurrentNode()->getType() != Node::ID_AGENT) {
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You need to be in an agent node to request a milkrun</pre>');
            $response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        if (!$response) {
            $milkrunInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\MilkrunInstance');
            /** @var MilkrunInstanceRepository $milkrunInstanceRepo */
            $currentMilkrun = $milkrunInstanceRepo->findCurrentMilkrun($profile);
            if (!$currentMilkrun) {
                var_dump('milkrun empty');
                $milkruns = $this->entityManager->getRepository('Netrunners\Entity\Milkrun')->findAll();
                $amount = count($milkruns) - 1;
                $targetMilkrun = $milkruns[mt_rand(0, $amount)];
                /** @var Milkrun $targetMilkrun */
                var_dump('milkrun template found');
                $milkrunLevel = 1;
                $timer = $targetMilkrun->getTimer();
                $expires = new \DateTime();
                $expires->add(new \DateInterval('PT' . $timer . 'S'));
                $sourceFaction = $this->getRandomFaction();
                /** @var Faction $sourceFaction */
                $targetFaction = $this->getRandomFaction();
                /** @var Faction $targetFaction */
                while ($sourceFaction === $targetFaction) {
                    $sourceFaction = $this->getRandomFaction();
                }
                var_dump('got factions');
                $mInstance = new MilkrunInstance();
                $mInstance->setAdded(new \DateTime());
                $mInstance->setExpires($expires);
                $mInstance->setLevel($milkrunLevel);
                $mInstance->setProfile($profile);
                $mInstance->setSourceFaction($sourceFaction);
                $mInstance->setTargetFaction($targetFaction);
                $mInstance->setMilkrun($targetMilkrun);
                $mInstance->setHealth(20);
                $mInstance->setAttack(1);
                $mInstance->setArmor(0);
                $this->entityManager->persist($mInstance);
                $this->entityManager->flush($mInstance);
                var_dump('flushed milkrun instance');
                $milkrunData = $this->prepareMilkrunData($resourceId, $mInstance);
            }
            else {
                /** @var MilkrunInstance $currentMilkrun */
                if (!empty($clientData->milkrun)) {
                    $milkrunData = $clientData->milkrun;
                }
                else {
                    $milkrunData = $this->prepareMilkrunData($resourceId, $currentMilkrun);
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/milkrun/game.phtml');
            $view->setVariable('mapData', $milkrunData['mapData']);
            $response = [
                'command' => 'startmilkrun',
                'content' => $this->viewRenderer->render($view),
                'level' => (int)$milkrunData['currentLevel'],
                'eeg' => (int)$milkrunData['eeg'],
                'attack' => (int)$milkrunData['attack'],
                'armor' => (int)$milkrunData['armor']
            ];
        }
        return $response;
    }

    /**
     * @param $resourceId
     * @param MilkrunInstance $currentMilkrun
     * @param int $currentLevel
     * @return array|mixed
     */
    private function prepareMilkrunData($resourceId, MilkrunInstance $currentMilkrun, $currentLevel = 1)
    {
        $milkrunData = [
            'id' => $currentMilkrun->getId(),
            'milkrun' => $currentMilkrun->getMilkrun()->getId(),
            'level' => $currentMilkrun->getLevel(),
            'currentLevel' => $currentLevel,
            'expires' => $currentMilkrun->getExpires(),
            'started' => $currentMilkrun->getAdded(),
            'eeg' => $currentMilkrun->getHealth(),
            'attack' => $currentMilkrun->getAttack(),
            'armor' => $currentMilkrun->getArmor(),
            'sourceFaction' => $currentMilkrun->getSourceFaction()->getId(),
            'targetFaction' => $currentMilkrun->getTargetFaction()->getId(),
            'credits' => 50,
            'keyX' => false,
            'keyY' => false,
            'mapData' => false
        ];
        $milkrunData = $this->generateMapData($milkrunData);
        $this->getWebsocketServer()->setClientData($resourceId, 'milkrun', $milkrunData);
        return $milkrunData;
    }

    /**
     * @return Faction
     */
    public function getRandomFaction()
    {
        $factionRepo = $this->entityManager->getRepository('Netrunners\Entity\Faction');
        /** @var FactionRepository $factionRepo */
        $factions = $factionRepo->findAllForMilkrun();
        $factionCount = count($factions) - 1;
        $targetFaction = $factions[mt_rand(0, $factionCount)];
        /** @var Faction $targetFaction */
        return $targetFaction;
    }

    /**
     * @param $milkrunData
     * @return mixed
     */
    public function generateMapData($milkrunData)
    {
        $levelAdd = 4;
        $realSize = $levelAdd + $milkrunData['currentLevel'];
        $xKey = 0;
        $yKey = 0;
        $xTarget = 1;
        $yTarget = 1;
        var_dump('realsize:' . $realSize);
        while ($xTarget >= 1 && $yTarget >= 1 ) {
            $xTarget = mt_rand(0,$realSize-1);
            $yTarget = mt_rand(0,$realSize-1);
        }
        var_dump('got target coords x: ' . $xTarget . ' y: ' . $yTarget);
        while (
            ($xKey < 1 || $yKey < 1) ||
            ($xKey == $xTarget && $yKey == $yTarget) ||
            ($xKey-1 == $xTarget && $yKey == $yTarget) ||
            ($xKey+1 == $xTarget && $yKey == $yTarget) ||
            ($xKey == $xTarget && $yKey-1 == $yTarget) ||
            ($xKey == $xTarget && $yKey+1 == $yTarget) ||
            ($xKey-1 == $xTarget && $yKey-1 == $yTarget) ||
            ($xKey+1 == $xTarget && $yKey-1 == $yTarget) ||
            ($xKey-1 == $xTarget && $yKey+1 == $yTarget) ||
            ($xKey+1 == $xTarget && $yKey+1 == $yTarget)
        ) {
            $xKey = mt_rand(0,$realSize-1);
            $yKey = mt_rand(0,$realSize-1);
        }
        var_dump('got key coords x: ' . $xKey . ' y: ' . $yKey);
        $milkrunData['keyX'] = $xKey;
        $milkrunData['keyY'] = $yKey;
        $mapData = [
            'targetUnlocked' => false,
            'level' => $milkrunData['currentLevel'],
            'maxLevel' => $milkrunData['level'],
            'map' => []
        ];
        for ($y=0; $y<$realSize; $y++) {
            $mapData['map'][$y] = [];
            for ($x=0; $x<$realSize; $x++) {
                $mapData['map'][$y][$x] = [
                    'type' => 0,
                    'blocked' => false,
                    'inaccessible' => true
                ];
            }
        }
        $mapData['map'][$yTarget][$xTarget]['type'] = self::TILE_TYPE_TARGET;
        $mapData['map'][$yTarget][$xTarget]['subtype'] = NULL;
        $mapData['map'][$yTarget][$xTarget]['blocked'] = false;
        $mapData['map'][$yTarget][$xTarget]['inaccessible'] = false;
        $mapData = $this->changeSurroundingTiles($mapData, $xTarget, $yTarget);
        $milkrunData['mapData'] = $mapData;
        return $milkrunData;
    }

    /**
     * @param $mapData
     * @param $sourceX
     * @param $sourceY
     * @return mixed
     */
    private function changeSurroundingTiles($mapData, $sourceX, $sourceY)
    {
        // first check the one up
        if (isset($mapData['map'][$sourceY-1][$sourceX]) && $mapData['map'][$sourceY-1][$sourceX]['type'] == self::TILE_TYPE_UNKNOWN) {
                $mapData['map'][$sourceY-1][$sourceX]['inaccessible'] = false;
                $mapData['map'][$sourceY-1][$sourceX]['blocked'] = $this->isTileBlocked($mapData, $sourceX, $sourceY-1);
        }
        // first check the one right
        if (isset($mapData['map'][$sourceY][$sourceX+1]) && $mapData['map'][$sourceY][$sourceX+1]['type'] == self::TILE_TYPE_UNKNOWN) {
                $mapData['map'][$sourceY][$sourceX+1]['inaccessible'] = false;
                $mapData['map'][$sourceY][$sourceX+1]['blocked'] = $this->isTileBlocked($mapData, $sourceX + 1, $sourceY);
        }
        // first check the one down
        if (isset($mapData['map'][$sourceY+1][$sourceX]) && $mapData['map'][$sourceY+1][$sourceX]['type'] == self::TILE_TYPE_UNKNOWN) {
            $mapData['map'][$sourceY+1][$sourceX]['inaccessible'] = false;
            $mapData['map'][$sourceY+1][$sourceX]['blocked'] = $this->isTileBlocked($mapData, $sourceX, $sourceY+1);
        }
        // first check the one left
        if (isset($mapData['map'][$sourceY][$sourceX-1]) && $mapData['map'][$sourceY][$sourceX-1]['type'] == self::TILE_TYPE_UNKNOWN) {
            $mapData['map'][$sourceY][$sourceX-1]['inaccessible'] = false;
            $mapData['map'][$sourceY][$sourceX-1]['blocked'] = $this->isTileBlocked($mapData, $sourceX-1, $sourceY);
        }
        return $mapData;
    }

    /**
     * @param $mapData
     * @param $sourceX
     * @param $sourceY
     * @return bool
     */
    private function isTileBlocked($mapData, $sourceX, $sourceY)
    {
        $result = false;
        // first check the one up
        if (isset($mapData['map'][$sourceY-1][$sourceX])) {
            $result = ($mapData['map'][$sourceY-1][$sourceX]['type'] == self::TILE_TYPE_ICE) ? true : false;
        }
        // first check the one right
        if (!$result && isset($mapData['map'][$sourceY][$sourceX+1])) {
            $result = ($mapData['map'][$sourceY][$sourceX+1]['type'] == self::TILE_TYPE_ICE) ? true : false;
        }
        // first check the one down
        if (!$result && isset($mapData['map'][$sourceY+1][$sourceX])) {
            $result = ($mapData['map'][$sourceY+1][$sourceX]['type'] == self::TILE_TYPE_ICE) ? true : false;
        }
        // first check the one left
        if (!$result && isset($mapData['map'][$sourceY][$sourceX-1])) {
            $result = ($mapData['map'][$sourceY][$sourceX-1]['type'] == self::TILE_TYPE_ICE) ? true : false;
        }
        return $result;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function clickTile($resourceId, $contentArray)
    {
        var_dump('click triggered on server');
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // stop if the player is not on a milkrun
        if (empty($clientData->milkrun)) return true;
        // init response
        $response = $this->isActionBlocked($resourceId);
        if (!$response) {
            // get x click
            list($contentArray, $targetX) = $this->getNextParameter($contentArray, true, true);
            // get y click
            $targetY = $this->getNextParameter($contentArray, false);
            if ($targetX < 0 || $targetY < 0) return true;
            var_dump('got x: ' . $targetX . ' y: ' . $targetY);
            // get map tile
            $milkrunData = $clientData->milkrun;
            $mapData = $milkrunData['mapData'];
            $mapTile = $mapData['map'][$targetY][$targetX];
            if (!$mapTile) return true;
            $newLevel = false;
            $complete = false;
            $newType = NULL;
            $subType = NULL;
            switch ($mapTile['type']) {
                default:
                    return true;
                case self::TILE_TYPE_UNKNOWN:
                    if ($mapTile['blocked'] || $mapTile['inaccessible']) return true;
                    if ($targetX == $milkrunData['keyX'] && $targetY == $milkrunData['keyY']) {
                        $newType = self::TILE_TYPE_KEY;
                        $subType = NULL;
                    }
                    else {
                        $newTypeChance = mt_rand(1, 100);
                        if ($newTypeChance > 50) {
                            $newType = self::TILE_TYPE_EMPTY;
                            $subType = NULL;
                        }
                        else if ($newTypeChance < 25) {
                            $newType = self::TILE_TYPE_ICE;
                            $subType = mt_rand(1, 2);
                        }
                        else {
                            $newType = self::TILE_TYPE_SPECIAL;
                            $subType = mt_rand(1, 2);
                        }
                    }
                    break;
                case self::TILE_TYPE_SPECIAL:
                    switch ($mapTile['subtype']) {
                        default:
                            $profile->setCredits($profile->getCredits() + mt_rand(1, $milkrunData['currentLevel']));
                            $newType = self::TILE_TYPE_EMPTY;
                            $subType = NULL;
                            break;
                        case self::TILE_SUBTYPE_SPECIAL_SNIPPETS:
                            $profile->setSnippets($profile->getSnippets() + mt_rand(1, $milkrunData['currentLevel']));
                            $newType = self::TILE_TYPE_EMPTY;
                            $subType = NULL;
                            break;
                    }
                    $this->entityManager->flush($profile);
                    break;
                case self::TILE_TYPE_KEY:
                    $newType = self::TILE_TYPE_EMPTY;
                    $subType = NULL;
                    $mapData['targetUnlocked'] = true;
                    break;
                case self::TILE_TYPE_TARGET:
                    if ($mapData['targetUnlocked']) {
                        if ($milkrunData['currentLevel'] == $milkrunData['level']) {
                            /* milkrun completed */
                            $complete = true;
                        }
                        else {
                            /* generate next level */
                            $milkrunData['currentLevel'] += 1;
                            $milkrunData['mapData'] = $this->generateMapData($milkrunData);
                            $newLevel = true;
                        }
                    }
                    break;
            }
            if ($complete) {
                var_dump('completed milkrun!');
                $mri = $this->entityManager->find('Netrunners\Entity\MilkrunInstance', $milkrunData['id']);
                /** @var MilkrunInstance $mri */
                $mri->setCompleted(new \DateTime());
                $this->entityManager->flush($mri);
                $profile->setCredits($profile->getCredits() + ($mri->getMilkrun()->getCredits() * $mri->getLevel()));
                $this->entityManager->flush($profile);
                $this->getWebsocketServer()->setClientData($resourceId, 'milkrun', []);
                $this->createProfileFactionRating(
                    $profile,
                    $mri,
                    NULL,
                    ProfileFactionRating::SOURCE_ID_MILKRUN,
                    $milkrunData['level'],
                    0,
                    $mri->getSourceFaction(),
                    $mri->getTargetFaction()
                );
                $response = [
                    'command' => 'completemilkrun',
                    'content' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have completed your current milkrun</pre>')
                ];
                var_dump('built completed response!');
            }
            else {
                if (!$newLevel) {
                    $mapData['map'][$targetY][$targetX]['type'] = $newType;
                    $mapData['map'][$targetY][$targetX]['subtype'] = $subType;
                    $mapData = $this->changeSurroundingTiles($mapData, $targetX, $targetY);
                    $milkrunData['mapData'] = $mapData;
                }
                $this->getWebsocketServer()->setClientData($resourceId, 'milkrun', $milkrunData);
                $view = new ViewModel();
                $view->setTemplate('netrunners/milkrun/partial-map.phtml');
                $view->setVariable('mapData', $milkrunData['mapData']);
                $response = [
                    'command' => 'updatedivhtml',
                    'content' => $this->viewRenderer->render($view),
                    'level' => $milkrunData['currentLevel']
                ];
            }
        }
        return $response;
    }

}
