<?php

/**
 * Milkrun Service.
 * The service supplies methods that resolve logic around Milkruns.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Faction;
use Netrunners\Entity\GameOption;
use Netrunners\Entity\Milkrun;
use Netrunners\Entity\MilkrunAivatarInstance;
use Netrunners\Entity\MilkrunIce;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Repository\FactionRepository;
use Netrunners\Repository\MilkrunInstanceRepository;
use Netrunners\Repository\MilkrunRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

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

    const LEVEL_ADD = 4;

    const CREDITS_MULTIPLIER = 25;

    /**
     * @var MilkrunRepository
     */
    protected $milkrunRepo;

    /**
     * @var MilkrunInstanceRepository
     */
    protected $milkrunInstanceRepo;


    /**
     * MilkrunService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->milkrunRepo = $this->entityManager->getRepository('Netrunners\Entity\Milkrun');
        $this->milkrunInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\MilkrunInstance');
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function enterMilkrunMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response && $currentNode->getNodeType()->getId() != NodeType::ID_AGENT) {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('You need to be in an agent node to request a milkrun')
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        $aivatar = $profile->getDefaultMilkrunAivatar();
        if (!$this->response && $aivatar && $aivatar->getCurrentEeg() < 1) {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('Your default Milkrun Aivatar does not have any EEG left')
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        if (!$this->response) {
            $currentMilkrun = $this->milkrunInstanceRepo->findCurrentMilkrun($profile);
            if (!$currentMilkrun) {
                $milkruns = $this->milkrunRepo->findAll();
                $amount = count($milkruns) - 1;
                $targetMilkrun = $milkruns[mt_rand(0, $amount)];
                /** @var Milkrun $targetMilkrun */
                $milkrunLevel = $currentNode->getLevel();
                $timer = $targetMilkrun->getTimer();
                $expires = new \DateTime();
                $expires->add(new \DateInterval('PT' . $timer . 'S'));
                $possibleSourceFactions = [];
                if ($profile->getFaction()) $possibleSourceFactions[] = $profile->getFaction();
                if ($currentSystem->getFaction()) $possibleSourceFactions[] = $currentSystem->getFaction();
                $sourceFaction = $this->getRandomFaction($possibleSourceFactions);
                $targetFaction = $this->getRandomFaction();
                while ($targetFaction === $sourceFaction) {
                    $targetFaction = $this->getRandomFaction();
                }
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">MILKRUN: %s</pre>'),
                    $targetMilkrun->getName()
                );
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-muted">%s</pre>',
                    wordwrap($targetMilkrun->getDescription(), 120)
                );
                $message .= sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">LEVEL: %s</pre>'),
                    $milkrunLevel
                );
                $message .= sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">EXPIRES: %s</pre>'),
                    $expires->format('Y/m/d H:i:s')
                );
                $message .= sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">SOURCE: %s</pre>'),
                    $sourceFaction->getName()
                );
                $message .= sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">TARGET: %s</pre>'),
                    $targetFaction->getName()
                );
                $message .= sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">REWARD: %sc</pre>'),
                    $milkrunLevel * self::CREDITS_MULTIPLIER
                );
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                    $this->translate('Accept this Milkrun? (enter "y" to confirm)')
                );
                $confirmData = [
                    'milkrunid' => $targetMilkrun->getId(),
                    'level' => $milkrunLevel,
                    'sourceFactionId' => $sourceFaction->getId(),
                    'targetFactionId' => $targetFaction->getId(),
                    'expires' => $expires
                ];
                $this->getWebsocketServer()->setConfirm($resourceId, 'milkrun', $confirmData);
                $this->response = [
                    'command' => 'enterconfirmmode',
                    'message' => $message
                ];
            }
            else {
                $this->response = $this->requestMilkrun($resourceId);
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param null|object $confirmData
     * @return array|bool|false
     */
    public function requestMilkrun($resourceId, $confirmData = NULL)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response && $profile->getCurrentNode()->getNodeType()->getId() != NodeType::ID_AGENT) {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('You need to be in an agent node to request a milkrun')
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        $aivatar = $profile->getDefaultMilkrunAivatar();
        if (!$this->response && $aivatar && $aivatar->getCurrentEeg() < 1) {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('Your default Milkrun Aivatar does not have any EEG left')
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        if (!$this->response) {
            $currentMilkrun = $this->milkrunInstanceRepo->findCurrentMilkrun($profile);
            $aivatar = $profile->getDefaultMilkrunAivatar();
            if (!$currentMilkrun) {
                $instanceData = (object)$confirmData->contentArray;
                $targetMilkrun = $this->entityManager->find('Netrunners\Entity\Milkrun', $instanceData->milkrunid);
                $sourceFaction = $this->entityManager->find('Netrunners\Entity\Faction', $instanceData->sourceFactionId);
                $targetFaction = $this->entityManager->find('Netrunners\Entity\Faction', $instanceData->targetFactionId);
                $mInstance = new MilkrunInstance();
                $mInstance->setAdded(new \DateTime());
                $mInstance->setExpires($instanceData->expires);
                $mInstance->setLevel($instanceData->level);
                $mInstance->setProfile($profile);
                $mInstance->setSourceFaction($sourceFaction);
                $mInstance->setTargetFaction($targetFaction);
                $mInstance->setMilkrun($targetMilkrun);
                $mInstance->setMilkrunAivatarInstance($aivatar);
                $this->entityManager->persist($mInstance);
                $this->entityManager->flush($mInstance);
                $milkrunData = $this->prepareMilkrunData($resourceId, $mInstance);
            }
            else {
                /** @var MilkrunInstance $currentMilkrun */
                if (!empty($this->clientData->milkrun)) {
                    $milkrunData = $this->clientData->milkrun;
                }
                else {
                    $milkrunData = $this->prepareMilkrunData($resourceId, $currentMilkrun);
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/milkrun/game.phtml');
            $view->setVariable('mapData', $milkrunData['mapData']);
            $music = ($this->getProfileGameOption($profile, GameOption::ID_MUSIC)) ? mt_rand(11,12) : NULL;
            $this->response = [
                'command' => 'startmilkrun',
                'content' => $this->viewRenderer->render($view),
                'level' => (int)$milkrunData['currentLevel'],
                'eeg' => (int)$aivatar->getCurrentEeg(),
                'attack' => (int)$aivatar->getCurrentAttack(),
                'armor' => (int)$aivatar->getCurrentArmor(),
                'music' => $music
            ];
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has requested a milkrun</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param MilkrunInstance $currentMilkrun
     * @param int $currentLevel
     * @return array|mixed
     */
    private function prepareMilkrunData($resourceId, MilkrunInstance $currentMilkrun, $currentLevel = 1)
    {
        $milkrunAivatar = $currentMilkrun->getMilkrunAivatarInstance();
        $milkrunData = [
            'id' => $currentMilkrun->getId(),
            'milkrun' => $currentMilkrun->getMilkrun()->getId(),
            'level' => $currentMilkrun->getLevel(),
            'currentLevel' => $currentLevel,
            'expires' => $currentMilkrun->getExpires(),
            'started' => $currentMilkrun->getAdded(),
            'aivatarid' => $milkrunAivatar->getId(),
            'sourceFaction' => $currentMilkrun->getSourceFaction()->getId(),
            'targetFaction' => $currentMilkrun->getTargetFaction()->getId(),
            'credits' => $currentMilkrun->getLevel() * self::CREDITS_MULTIPLIER,
            'keyX' => false,
            'keyY' => false,
            'mapData' => false
        ];
        $milkrunData = $this->generateMapData($milkrunData);
        $this->getWebsocketServer()->setClientData($resourceId, 'milkrun', $milkrunData);
        return $milkrunData;
    }

    /**
     * @param array $factions
     * @return mixed|Faction
     */
    public function getRandomFaction($factions = [])
    {
        $factionRepo = $this->entityManager->getRepository('Netrunners\Entity\Faction');
        /** @var FactionRepository $factionRepo */
        if (empty($factions)) {
            $factions = $factionRepo->findAllForMilkrun();
        }
        $factionCount = count($factions) - 1;
        $targetFaction = $factions[mt_rand(0, $factionCount)];
        /** @var Faction $targetFaction */
        while ($targetFaction->getId() == Faction::ID_AIVATARS || $targetFaction->getId() == Faction::ID_NETWATCH) {
            $targetFaction = $factions[mt_rand(0, $factionCount)];
        }
        return $targetFaction;
    }

    /**
     * @param $milkrunData
     * @return mixed
     */
    private function generateMapData($milkrunData)
    {
        $levelAdd = self::LEVEL_ADD;
        $realSize = $levelAdd + $milkrunData['currentLevel'];
        $xKey = 0;
        $yKey = 0;
        $xTarget = 1;
        $yTarget = 1;
        while ($xTarget >= 1 && $yTarget >= 1 ) {
            $xTarget = mt_rand(0,$realSize-1);
            $yTarget = mt_rand(0,$realSize-1);
        }
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
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // stop if the player is not on a milkrun
        if (empty($this->clientData->milkrun)) return true;
        // init response
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            // get x click
            list($contentArray, $targetX) = $this->getNextParameter($contentArray, true, true);
            // get y click
            $targetY = $this->getNextParameter($contentArray, false);
            if ($targetX < 0 || $targetY < 0) return true;
            // get map tile
            $milkrunData = $this->clientData->milkrun;
            $aivatarId = $milkrunData['aivatarid'];
            $aivatar = $this->entityManager->find('Netrunners\Entity\MilkrunAivatarInstance', $aivatarId);
            /** @var MilkrunAivatarInstance $aivatar */
            $mapData = $milkrunData['mapData'];
            $mapTile = $mapData['map'][$targetY][$targetX];
            if (!$mapTile) return true;
            $newLevel = false;
            $complete = false;
            $failed = false;
            $playSound = false;
            $newType = NULL;
            $subType = NULL;
            $iceEeg = NULL;
            $iceAttack = NULL;
            $iceArmor = NULL;
            switch ($mapTile['type']) {
                default:
                    return true;
                case self::TILE_TYPE_ICE:
                    /* player is attacking ICE */
                    $playSound = 5;
                    $milkrunIce = $this->entityManager->find('Netrunners\Entity\MilkrunIce', $mapTile['subtype']);
                    /** @var MilkrunIce $milkrunIce */
                    $milkrunIceEeg = $mapTile['iceEeg'];
                    $milkrunIceAttack = $mapTile['iceAttack'];
                    $milkrunIceArmor = $mapTile['iceArmor'];
                    $milkrunIceSpecials = ($milkrunIce->getSpecials()) ? explode(',', $milkrunIce->getSpecials()): false; // TODO for special abilities
                    // player hurts ice
                    $newMilkrunIceEeg = (int)$milkrunIceEeg - (int)$aivatar->getCurrentAttack();
                    if ($newMilkrunIceEeg < 1) {
                        // player has killed ice
                        $newType = self::TILE_TYPE_EMPTY;
                    }
                    else {
                        $newType = self::TILE_TYPE_ICE;
                        $subType = $mapTile['subtype'];
                        $iceEeg = $newMilkrunIceEeg;
                        $iceAttack = $milkrunIceAttack;
                        $iceArmor = $milkrunIceArmor;
                    }
                    // ice strikes back
                    $newPlayerEeg = (int)$aivatar->getCurrentEeg() - (int)$milkrunIceAttack;
                    if ($newPlayerEeg < 1) {
                        // ice has flatlined player - milkrun has failed
                        $aivatar->setCurrentEeg(0);
                        $failed = true;
                    }
                    else {
                        // ice has hurt player, but nothing else
                        $aivatar->setCurrentEeg($newPlayerEeg);
                        $this->updateDivHtml($profile, '#milkrun-eeg', $aivatar->getCurrentEeg(), [], true);
                    }
                    $this->entityManager->flush($aivatar);
                    break;
                case self::TILE_TYPE_UNKNOWN:
                    /* reveal the unknown tile */
                    $playSound = 6;
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
                        else if ($newTypeChance < 30) { // 30% of the time it will be milkrun-ice
                            $newType = self::TILE_TYPE_ICE;
                            $subType = mt_rand(1, 2);
                            $milkrunIce = $this->entityManager->find('Netrunners\Entity\MilkrunIce', $subType);
                            /** @var MilkrunIce $milkrunIce */
                            $iceEeg = mt_rand($milkrunIce->getBaseEeg()+($milkrunData['currentLevel']-1), $milkrunIce->getBaseEeg()+$milkrunData['currentLevel']);
                            $iceAttack = mt_rand($milkrunIce->getBaseAttack()+($milkrunData['currentLevel']-1), $milkrunIce->getBaseAttack()+$milkrunData['currentLevel']);
                            $iceArmor = ($milkrunIce->getBaseArmor() > 0) ? mt_rand($milkrunIce->getBaseArmor(), $milkrunIce->getBaseArmor()+$milkrunData['currentLevel']) : 0;
                        }
                        else {
                            $newType = self::TILE_TYPE_SPECIAL;
                            $subType = mt_rand(1, 2);
                        }
                    }
                    break;
                case self::TILE_TYPE_SPECIAL:
                    /* player clicked on a special tile */
                    switch ($mapTile['subtype']) {
                        default:
                            $playSound = 1;
                            $profile->setCredits($profile->getCredits() + mt_rand(1, $milkrunData['currentLevel']));
                            $newType = self::TILE_TYPE_EMPTY;
                            $subType = NULL;
                            break;
                        case self::TILE_SUBTYPE_SPECIAL_SNIPPETS:
                            $playSound = 10;
                            $profile->setSnippets($profile->getSnippets() + mt_rand(1, $milkrunData['currentLevel']));
                            $newType = self::TILE_TYPE_EMPTY;
                            $subType = NULL;
                            break;
                    }
                    $this->entityManager->flush($profile);
                    break;
                case self::TILE_TYPE_KEY:
                    /* player clicked on key tile */
                    $playSound = 7;
                    $newType = self::TILE_TYPE_EMPTY;
                    $subType = NULL;
                    $mapData['targetUnlocked'] = true;
                    break;
                case self::TILE_TYPE_TARGET:
                    /* player clicked on target tile */
                    if ($mapData['targetUnlocked']) {
                        if ($milkrunData['currentLevel'] == $milkrunData['level']) {
                            /* milkrun completed */
                            $playSound = 8;
                            $complete = true;
                        }
                        else {
                            /* generate next level */
                            $playSound = 9;
                            $milkrunData['currentLevel'] += 1;
                            $milkrunData = $this->generateMapData($milkrunData);
                            $newLevel = true;
                        }
                    }
                    break;
            }
            if ($complete) {
                $mri = $this->entityManager->find('Netrunners\Entity\MilkrunInstance', $milkrunData['id']);
                /** @var MilkrunInstance $mri */
                $mri->setCompleted(new \DateTime());
                $this->entityManager->flush($mri);
                $profile->setCredits($profile->getCredits() + ($mri->getMilkrun()->getCredits() * $mri->getLevel()));
                $profile->setCompletedMilkruns($profile->getCompletedMilkruns() + 1);
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
                $this->response = [
                    'command' => 'completemilkrun',
                    'content' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('You have completed your current milkrun')
                    )
                ];
                if ($this->getProfileGameOption($profile, GameOption::ID_SOUND)) $this->response['playsound'] = $playSound;
                // give rewards to aivatar
                $aivatar->setCompleted($aivatar->getCompleted()+1);
                $aivatar->setPointsearned($aivatar->getPointsearned()+$mri->getLevel());
                $this->entityManager->flush($aivatar);
            }
            else if ($failed) {
                $mri = $this->entityManager->find('Netrunners\Entity\MilkrunInstance', $milkrunData['id']);
                /** @var MilkrunInstance $mri */
                $mri->setExpired(true);
                $profile->setFaileddMilkruns($profile->getFaileddMilkruns() + 1);
                $this->entityManager->flush();
                $this->getWebsocketServer()->setClientData($resourceId, 'milkrun', []);
                $this->createProfileFactionRating(
                    $profile,
                    $mri,
                    NULL,
                    ProfileFactionRating::SOURCE_ID_MILKRUN,
                    $milkrunData['level'] * -1,
                    $milkrunData['level'] * -1,
                    $mri->getSourceFaction(),
                    $mri->getTargetFaction()
                );
                $this->response = [
                    'command' => 'completemilkrun',
                    'content' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You have failed your current milkrun')
                    )
                ];
                if ($this->getProfileGameOption($profile, GameOption::ID_SOUND)) $this->response['playsound'] = $playSound;
            }
            else {
                if (!$newLevel) {
                    $mapData['map'][$targetY][$targetX]['type'] = $newType;
                    $mapData['map'][$targetY][$targetX]['subtype'] = $subType;
                    $mapData['map'][$targetY][$targetX]['iceEeg'] = $iceEeg;
                    $mapData['map'][$targetY][$targetX]['iceAttack'] = $iceAttack;
                    $mapData['map'][$targetY][$targetX]['iceArmor'] = $iceArmor;
                    $mapData = $this->changeSurroundingTiles($mapData, $targetX, $targetY);
                    $milkrunData['mapData'] = $mapData;
                }
                $this->getWebsocketServer()->setClientData($resourceId, 'milkrun', $milkrunData);
                $view = new ViewModel();
                $view->setTemplate('netrunners/milkrun/partial-map.phtml');
                $view->setVariable('mapData', $milkrunData['mapData']);
                $this->updateDivHtml(
                    $profile,
                    '#milkrun-game-container',
                    $this->viewRenderer->render($view),
                    ['level'=>$milkrunData['currentLevel']]
                );
                if ($this->getProfileGameOption($profile, GameOption::ID_SOUND)) $this->response['playsound'] = $playSound;
            }
        }
        return $this->response;
    }

}
