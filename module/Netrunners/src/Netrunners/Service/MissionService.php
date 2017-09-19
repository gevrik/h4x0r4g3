<?php

/**
 * Mission Service.
 * The service supplies methods that resolve logic around Missions.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Mission;
use Netrunners\Entity\MissionArchetype;
use Netrunners\Entity\NodeType;
use Netrunners\Repository\FactionRepository;
use Netrunners\Repository\MissionArchetypeRepository;
use Netrunners\Repository\MissionRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class MissionService extends BaseService
{

    const TILE_SUBTYPE_SPECIAL_CREDITS = 1;
    const TILE_SUBTYPE_SPECIAL_SNIPPETS = 2;
    const CREDITS_MULTIPLIER = 125;

    /**
     * @var SystemGeneratorService
     */
    protected $systemGeneratorService;

    /**
     * @var MissionRepository
     */
    protected $missionRepo;

    /**
     * @var MissionArchetypeRepository
     */
    protected $missionArchetypeRepo;


    /**
     * MissionService constructor.
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
        $this->missionRepo = $this->entityManager->getRepository('Netrunners\Entity\Mission');
        $this->missionArchetypeRepo = $this->entityManager->getRepository('Netrunners\Entity\MissionArchetype');
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function enterMode($resourceId)
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
                $this->translate('You need to be in an agent node to request a mission')
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        if (!$this->response) {
            $currentMission = $this->missionRepo->findCurrentMission($profile);
            if ($currentMission) {
                $returnMessage = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You have already accepted another mission')
                );
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => $returnMessage
                );
            }
            if (!$this->response) {
                $missions = $this->missionArchetypeRepo->findAll();
                $amount = count($missions) - 1;
                $targetMission = $missions[mt_rand(0, $amount)];
                /** @var MissionArchetype $targetMission */
                $missionLevel = $currentNode->getLevel();
                $timer = 3600;
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">MISSION: %s</pre>'),
                    $targetMission->getName()
                );
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-muted">%s</pre>',
                    wordwrap($targetMission->getDescription(), 120)
                );
                $message .= sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">LEVEL: %s</pre>'),
                    $missionLevel
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
                    $missionLevel * self::CREDITS_MULTIPLIER
                );
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                    $this->translate('Accept this mission? (enter "y" to confirm)')
                );
                $confirmData = [
                    'missionArchetypeId' => $targetMission->getId(),
                    'level' => $missionLevel,
                    'sourceFactionId' => $sourceFaction->getId(),
                    'targetFactionId' => $targetFaction->getId(),
                    'expires' => $expires
                ];
                $this->getWebsocketServer()->setConfirm($resourceId, 'mission', $confirmData);
                $this->response = [
                    'command' => 'enterconfirmmode',
                    'message' => $message
                ];
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param null|object $confirmData
     * @return array|bool|false
     */
    public function requestMission($resourceId, $confirmData = NULL)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response && $profile->getCurrentNode()->getNodeType()->getId() != NodeType::ID_AGENT) {
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('You need to be in an agent node to request a mission')
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        if (!$this->response) {
            $currentMission = $this->missionRepo->findCurrentMission($profile);
            if ($currentMission) {
                $returnMessage = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You have accepted another mission already')
                );
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => $returnMessage
                );
            }
            if (!$this->response) {
                $instanceData = (object)$confirmData->contentArray;
                $targetMission = $this->entityManager->find('Netrunners\Entity\MissionArchetype', $instanceData->missionArchetypeId);
                $sourceFaction = $this->entityManager->find('Netrunners\Entity\Faction', $instanceData->sourceFactionId);
                $targetFaction = $this->entityManager->find('Netrunners\Entity\Faction', $instanceData->targetFactionId);
                /** @var Faction $targetFaction */
                $mInstance = new Mission();
                $mInstance->setAdded(new \DateTime());
                $mInstance->setExpires($instanceData->expires);
                $mInstance->setLevel($instanceData->level);
                $mInstance->setProfile($profile);
                $mInstance->setSourceFaction($sourceFaction);
                $mInstance->setTargetFaction($targetFaction);
                $mInstance->setMission($targetMission);
                $mInstance->setCompleted(NULL);
                $mInstance->setExpired(NULL);
                $mInstance->setTargetFile(NULL);
                $mInstance->setTargetSystem(NULL);
                $mInstance->setTargetNode(NULL);
                $this->entityManager->persist($mInstance);
                $possibleSystems = $systemRepo->findByTargetFaction($targetFaction);
                // generate new system randomly or if we have found no existing systems
                if (count($possibleSystems) < 1 || mt_rand(1, 100) <= 50) {
                    $targetSystem = $this->systemGeneratorService->generateRandomSystem($instanceData->level, $targetFaction);
                }
                else {
                    shuffle($possibleSystems);
                    $targetSystem = array_shift($possibleSystems);
                }
                $mInstance->setTargetSystem($targetSystem);
                $this->entityManager->flush($mInstance);
                switch ($targetMission->getId()) {
                    default:
                        $createTargetFile = false;
                        $setTargetProfile = false;
                        $targetNode = NULL;
                        $addToNode = false;
                        break;
                    case MissionArchetype::ID_STEAL_FILE:
                    case MissionArchetype::ID_DELETE_FILE:
                        $addToNode = true;
                        $createTargetFile = true;
                        $setTargetProfile = false;
                        $targetNode = $nodeRepo->getRandomNodeForMission($targetSystem);
                        break;
                    case MissionArchetype::ID_PLANT_BACKDOOR:
                    case MissionArchetype::ID_UPLOAD_FILE:
                        $createTargetFile = true;
                        $setTargetProfile = true;
                        $targetNode = $nodeRepo->getRandomNodeForMission($targetSystem);
                        $addToNode = false;
                        break;
                }
                if ($createTargetFile) {
                    $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_TEXT);
                    /** @var FileType $fileType */
                    $fileName = $this->getRandomString(12) . '.txt';
                    $targetFile = new File();
                    $targetFile->setVersion(1);
                    $targetFile->setSlots(0);
                    $targetFile->setSize(0);
                    $targetFile->setExecutable(false);
                    $targetFile->setCoder(NULL);
                    $targetFile->setRunning(false);
                    $targetFile->setFileType($fileType);
                    $targetFile->setNode(($addToNode) ? $targetNode : NULL);
                    $targetFile->setMailMessage(NULL);
                    $targetFile->setModified(NULL);
                    $targetFile->setNpc(NULL);
                    $targetFile->setData(NULL);
                    $targetFile->setSystem(($addToNode) ? $targetSystem : NULL);
                    $targetFile->setCreated(new \DateTime());
                    $targetFile->setLevel(1);
                    $targetFile->setProfile(($setTargetProfile) ? $profile : NULL);
                    $targetFile->setName($fileName);
                    $targetFile->setMaxIntegrity(100);
                    $targetFile->setIntegrity(100);
                    $this->entityManager->persist($targetFile);
                    $mInstance->setTargetFile($targetFile);
                    $mInstance->setTargetNode($targetNode);
                    $this->entityManager->flush();
                }
                $returnMessage = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('You have accepted the mission')
                );
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => $returnMessage
                );
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showMissionDetails($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $currentMission = $this->missionRepo->findCurrentMission($profile);
            if (!$currentMission) {
                $command = 'showmessage';
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">No current mission</pre>'),
                    $this->user->getUsername()
                );
            }
            else {
                /** @var Mission $currentMission */
                $archetype = $currentMission->getMission();
                $command = 'showoutput';
                $message = [];
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s - %s</pre>'),
                    $this->translate('MISSION'),
                    strtoupper($archetype->getName())
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-addon">%s</pre>'),
                    $archetype->getDescription()
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('LEVEL'),
                    $currentMission->getLevel()
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('ACCEPTED'),
                    $currentMission->getAdded()->format('Y/m/d H:i:s')
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('EXPIRES'),
                    $currentMission->getExpires()->format('Y/m/d H:i:s')
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('SOURCE'),
                    $currentMission->getSourceFaction()->getName()
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('TARGET'),
                    $currentMission->getTargetFaction()->getName()
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('SYSTEM'),
                    $currentMission->getTargetSystem()->getAddy()
                );
                $message[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                    $this->translate('FILE'),
                    $currentMission->getTargetFile()->getName()
                );
                switch ($archetype->getId()) {
                    default:
                        break;
                    case MissionArchetype::ID_PLANT_BACKDOOR:
                    case MissionArchetype::ID_UPLOAD_FILE:
                        $message[] = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-14s: %s</pre>'),
                            $this->translate('NODE'),
                            $currentMission->getTargetNode()->getName()
                        );
                        break;
                }
            }
            $this->response = [
                'command' => $command,
                'message' => $message
            ];
        }
        return $this->response;
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

}