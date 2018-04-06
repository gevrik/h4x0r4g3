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
use Netrunners\Entity\FileType;
use Netrunners\Entity\Group;
use Netrunners\Entity\Mission;
use Netrunners\Entity\MissionArchetype;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FactionRepository;
use Netrunners\Repository\GroupRepository;
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
     * @var NodeRepository
     */
    protected $nodeRepo;

    /**
     * @var SystemRepository
     */
    protected $systemRepo;

    /**
     * @var GroupRepository
     */
    protected $groupRepo;

    /**
     * @var FactionRepository
     */
    protected $factionRepo;


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
        $this->missionRepo = $this->entityManager->getRepository(Mission::class);
        $this->missionArchetypeRepo = $this->entityManager->getRepository(MissionArchetype::class);
        $this->nodeRepo = $this->entityManager->getRepository(Node::class);
        $this->systemRepo = $this->entityManager->getRepository(System::class);
        $this->groupRepo = $this->entityManager->getRepository(Group::class);
        $this->factionRepo = $this->entityManager->getRepository(Faction::class);
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function enterMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if (!$this->response && $currentNode->getNodeType()->getId() != NodeType::ID_AGENT) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in an agent node to request a mission'))->send();
        }
        $currentMission = $this->missionRepo->findCurrentMission($profile);
        if ($currentMission) {
            return $this->gameClientResponse->addMessage($this->translate('You have already accepted another mission'))->send();
        }
        $missions = $this->missionArchetypeRepo->findAll();
        $amount = count($missions) - 1;
        /** @var MissionArchetype $targetMission */
        $targetMission = $missions[mt_rand(0, $amount)];
        $missionLevel = $currentNode->getLevel();
        $timer = 3600;
        $expires = new \DateTime();
        $expires->add(new \DateInterval('PT' . $timer . 'S'));
        $possibleSourceFactions = [];
        if ($profile->getFaction()) $possibleSourceFactions[] = $profile->getFaction();
        if ($currentSystem->getFaction()) $possibleSourceFactions[] = $currentSystem->getFaction();
        $sourceFaction = $this->getRandomFaction($possibleSourceFactions);
        if ($targetMission->getSubtype() == MissionArchetype::ID_SUBTYPE_WHITE) { // whitehat missions
            $targetFaction = $sourceFaction;
        }
        else {
            $targetFaction = $this->getRandomFaction();
            while ($targetFaction === $sourceFaction) {
                $targetFaction = $this->getRandomFaction();
            }
        }
        $message = sprintf(
            $this->translate('MISSION: %s'),
            $targetMission->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $this->gameClientResponse->addMessage(wordwrap($targetMission->getDescription(), 120), GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('LEVEL: %s'),
            $missionLevel
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('EXPIRES: %s'),
            $expires->format('Y/m/d H:i:s')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('SOURCE: %s'),
            $sourceFaction->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('TARGET: %s'),
            $targetFaction->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('REWARD: %sc'),
            $missionLevel * self::CREDITS_MULTIPLIER
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $this->gameClientResponse->addMessage($this->translate('Accept this mission? (enter "y" to confirm)'), GameClientResponse::CLASS_WHITE);
        $confirmData = [
            'missionArchetypeId' => $targetMission->getId(),
            'level' => $missionLevel,
            'sourceFactionId' => $sourceFaction->getId(),
            'targetFactionId' => $targetFaction->getId(),
            'expires' => $expires
        ];
        $this->getWebsocketServer()->setConfirm($resourceId, 'mission', $confirmData);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param null|object $confirmData
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function requestMission($resourceId, $confirmData = NULL)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($profile->getCurrentNode()->getNodeType()->getId() != NodeType::ID_AGENT) {
            return $this->gameClientResponse
                ->addMessage(
                    $this->translate('You need to be in an agent node to request a mission')
                )->send();
        }
        if ($this->missionRepo->findCurrentMission($profile)) {
            return $this->gameClientResponse
                ->addMessage($this->translate('You have accepted another mission already'))->send();
        }
        $instanceData = (object)$confirmData->contentArray;
        $targetMission = $this->entityManager
            ->find(MissionArchetype::class, $instanceData->missionArchetypeId);
        $sourceFaction = $this->entityManager->find(Faction::class, $instanceData->sourceFactionId);
        $targetFaction = $this->entityManager->find(Faction::class, $instanceData->targetFactionId);
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
        $possibleSystems = $this->systemRepo->findByTargetFaction($targetFaction);
        // generate new system randomly or if we have found no existing systems
        if (count($possibleSystems) < 1 || mt_rand(1, 100) <= 50) {
            $targetSystem = $this->systemGeneratorService->generateRandomSystem($instanceData->level, $targetFaction);
        }
        else {
            shuffle($possibleSystems);
            $targetSystem = array_shift($possibleSystems);
        }
        $mInstance->setTargetSystem($targetSystem);
        $createTargetFile = false;
        $setTargetProfile = false;
        $targetNode = NULL;
        $addToNode = false;
        switch ($targetMission->getId()) {
            default:
                break;
            case MissionArchetype::ID_CLEAN_SYSTEM:
                $amount = ceil(round($this->nodeRepo->getAverageNodeLevelOfSystem($targetSystem))) *
                    NodeService::MAX_NODES_MULTIPLIER;
                $data = [
                    'amount' => 0,
                    'totalAmount' => $amount
                ];
                $mInstance->setData(json_encode($data));
                break;
            case MissionArchetype::ID_STEAL_FILE:
            case MissionArchetype::ID_DELETE_FILE:
                $addToNode = true;
                $createTargetFile = true;
                $targetNode = $this->nodeRepo->getRandomNodeForMission($targetSystem);
                break;
            case MissionArchetype::ID_PLANT_BACKDOOR:
            case MissionArchetype::ID_UPLOAD_FILE:
                $createTargetFile = true;
                $setTargetProfile = true;
                $targetNode = $this->nodeRepo->getRandomNodeForMission($targetSystem);
                break;
        }
        $profile->setCurrentMission($mInstance);
        if ($createTargetFile) {
            $fileType = $this->entityManager->find(FileType::class, FileType::ID_TEXT);
            /** @var FileType $fileType */
            $fileName = $this->getRandomString(12) . '.txt';
            $targetFile = $this->createFile(
                $fileType,
                false,
                $fileName,
                1,
                100,
                false,
                100,
                null,
                null,
                null,
                null,
                ($addToNode) ? $targetNode : NULL,
                null,
                ($setTargetProfile) ? $profile : NULL,
                ($addToNode) ? $targetSystem : NULL,
                0
            );
            $mInstance->setTargetFile($targetFile);
            $mInstance->setTargetNode($targetNode);
        }
        $this->entityManager->flush();
        $this->getWebsocketServer()->setClientData($resourceId, 'currentMission', $mInstance->getId());
        return $this->gameClientResponse
            ->addMessage($this->translate('You have accepted the mission'), GameClientResponse::CLASS_SUCCESS);
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showMissionDetails($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $currentMission = $this->missionRepo->findCurrentMission($profile);
        if (!$currentMission) {
            $message = $this->translate('No current mission');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var Mission $currentMission */
        $archetype = $currentMission->getMission();
        $message = sprintf(
            $this->translate('%s - %s'),
            $this->translate('MISSION'),
            strtoupper($archetype->getName())
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $this->gameClientResponse->addMessage($archetype->getDescription(), GameClientResponse::CLASS_ADDON);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('LEVEL'),
            $currentMission->getLevel()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('ACCEPTED'),
            $currentMission->getAdded()->format('Y/m/d H:i:s')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('EXPIRES'),
            $currentMission->getExpires()->format('Y/m/d H:i:s')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('SOURCE'),
            $currentMission->getSourceFaction()->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('TARGET'),
            $currentMission->getTargetFaction()->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('SYSTEM'),
            $currentMission->getTargetSystem()->getAddy()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-14s: %s'),
            $this->translate('FILE'),
            $currentMission->getTargetFile()->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        switch ($archetype->getId()) {
            default:
                break;
            case MissionArchetype::ID_PLANT_BACKDOOR:
            case MissionArchetype::ID_UPLOAD_FILE:
                $message = sprintf(
                    $this->translate('%-14s: %s'),
                    $this->translate('NODE'),
                    $currentMission->getTargetNode()->getName()
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                break;
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param array $factions
     * @return mixed|Faction
     */
    public function getRandomFaction($factions = [])
    {
        if (empty($factions)) {
            $factions = $this->factionRepo->findAllForMilkrun();
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
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function missionListCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($currentNode->getNodeType()->getId() != NodeType::ID_AGENT) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in an agent node to list available missions'))->send();
        }
        $availableMissions = $this->missionRepo->findForMissionListCommand($currentNode);
        if (count($availableMissions) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('There are no missions currently available'))->send();
        }
        // TODO add checks for change-node-type and remove-node commands
        $returnMessage = sprintf(
            '%-11s|%-32s|%-19s|%-19s|%s',
            $this->translate('MISSION-ID'),
            $this->translate('MISSION-GIVER'),
            $this->translate('MISSION-ADDED'),
            $this->translate('MISSION-EXPIRY'),
            $this->translate('MISSION-NAME')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        $returnMessage = [];
        /** @var Mission $availableMission */
        foreach ($availableMissions as $availableMission) {
            $missionName = ($availableMission->getName()) ?
                $availableMission->getName() :
                $availableMission->getMission()->getName();
            $returnMessage[] = sprintf(
                '%-11s|%-32s|%-19s|%-19s|%s',
                $availableMission->getId(),
                $this->getMissionGiver($availableMission),
                $availableMission->getAdded(),
                $availableMission->getExpires(),
                $missionName
            );
        }
        $this->gameClientResponse->addMessages($returnMessage, GameClientResponse::CLASS_WHITE);
        return $this->gameClientResponse->send();
    }

    /**
     * @param Mission $mission
     * @return string
     */
    private function getMissionGiver(Mission $mission)
    {
        $resultString = '';
        if ($mission->getMissionGiver()) {
            $resultString .= '[u] ' . $mission->getMissionGiver()->getUser()->getUsername();
        }
        elseif ($mission->getSourceGroup()) {
            $resultString .= '[g] ' . $mission->getSourceGroup()->getName();
        }
        else {
            $resultString .= '[f] ' . $mission->getSourceFaction()->getName();
        }
        return $resultString;
    }

}
