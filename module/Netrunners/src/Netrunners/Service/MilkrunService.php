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
use TmoAuth\Entity\User;

class MilkrunService extends BaseService
{

    const TILE_TYPE_EMPTY = 0;
    const TILE_TYPE_SOURCE = 1;
    const TILE_TYPE_TARGET = 2;
    const TILE_TYPE_ICE = 3;
    const TILE_TYPE_SPECIAL = 4;

    public function requestMilkrun($resourceId)
    {
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
            if (!empty($clientData->milkrun)) {
                $milkrunData = $clientData->milkrun;
            }
            else {
                $milkruns = $this->entityManager->getRepository('Netrunners\Entity\Milkrun')->findAll();
                $amount = count($milkruns) - 1;
                $targetMilkrun = $milkruns[mt_rand(0, $amount)];
                /** @var Milkrun $targetMilkrun */
                $milkrunId = $targetMilkrun->getId();
                $milkrunLevel = 1;
                $timer = $targetMilkrun->getTimer();
                $expires = new \DateTime();
                $expires->add(new \DateInterval('PT' . $timer . 'S'));
                $started = new \DateTime();
                $credits = $milkrunLevel * 50;
                $sourceFaction = $this->getRandomFaction();
                /** @var Faction $sourceFaction */
                $targetFaction = $this->getRandomFaction();
                /** @var Faction $targetFaction */
                while ($sourceFaction === $targetFaction) $this->getRandomFaction();
                $mInstance = new MilkrunInstance();
                $mInstance->setAdded(new \DateTime());
                $mInstance->setExpires($expires);
                $mInstance->setLevel($milkrunLevel);
                $mInstance->setProfile($profile);
                $mInstance->setSourceFaction($sourceFaction);
                $mInstance->setTargetFaction($targetFaction);
                $this->entityManager->persist($mInstance);
                $this->entityManager->flush($mInstance);
                $milkrunData = [
                    'id' => 0,
                    'milkrun' => $milkrunId,
                    'level' => $milkrunLevel,
                    'expires' => $expires,
                    'started' => $started,
                    'sourceFaction' => $sourceFaction->getId(),
                    'targetFaction' => $targetFaction->getId(),
                    'credits' => $credits,
                    'currentLevel' => 1,
                    'mapData' => $this->generateMapData($milkrunLevel)
                ];
            }
        }
        return $response;
    }

    public function getRandomFaction()
    {
        $factions = $this->entityManager->getRepository('Netrunners\Entity\Faction')->findAllForMilkrun();
        $factionCount = count($factions) - 1;
        $targetFaction = $factions[mt_rand(0, $factionCount)];
        /** @var Faction $targetFaction */
        return $targetFaction;
    }

    public function generateMapData($level)
    {
        $xSource = 0;
        $ySource = 0;
        $xTarget = 0;
        $yTarget = 0;
        while ($xSource == $xTarget && $ySource == $yTarget) {
            $xSource = mt_rand(0,$level-1);
            $ySource = mt_rand(0,$level-1);
            $xTarget = mt_rand(0,$level-1);
            $yTarget = mt_rand(0,$level-1);
        }
        $mapData = [
            'xSource' => $xSource,
            'ySource' => $ySource,
            'xTarget' => $xTarget,
            'yTarget' => $yTarget,
            'map' => []
        ];
        for ($y=1; $y<$level; $y++) {
            $mapData['map'][$y] = [];
            for ($x=1; $x<$level; $x++) {
                if ($y == $ySource && $x == $xSource) {
                    $tileType = self::TILE_TYPE_SOURCE;
                    $tileKnown = true;
                }
                else {
                    $tileType = $this->getRandomTileType();
                    $tileKnown = false;
                }
                $mapData['map'][$y][$x] = [

                ];
            }
        }
    }

    private function getRandomTileType()
    {

    }

}
