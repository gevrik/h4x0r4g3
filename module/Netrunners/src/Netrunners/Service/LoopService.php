<?php

/**
 * Loop Service.
 * The service supplies methods that resolve logic around the loops that occur at regular intervals.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Effect;
use Netrunners\Entity\File;
use Netrunners\Entity\FileCategory;
use Netrunners\Entity\FileType;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Mission;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Entity\ProfileFileTypeRecipe;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use Netrunners\Repository\MilkrunInstanceRepository;
use Netrunners\Repository\MissionRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NotificationRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\ProfileFileTypeRecipeRepository;
use Netrunners\Repository\SystemRepository;
use Ratchet\ConnectionInterface;
use TmoAuth\Entity\User;
use Zend\Mvc\I18n\Translator;

class LoopService extends BaseService
{

    /**
     * Stores jobs that the players have started.
     * @var array
     */
    protected $jobs = [];

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var CombatService
     */
    protected $combatService;

    /**
     * @var CodingService
     */
    protected $codingService;

    /**
     * @var SystemService
     */
    protected $systemService;

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
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepo;


    /**
     * LoopService constructor.
     * @param EntityManager $entityManager
     * @param \Zend\View\Renderer\PhpRenderer $viewRenderer
     * @param FileService $fileService
     * @param CodingService $codingService
     * @param CombatService $combatService
     * @param SystemService $systemService
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer,
        FileService $fileService,
        CodingService $codingService,
        CombatService $combatService,
        SystemService $systemService,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileService = $fileService;
        $this->codingService = $codingService;
        $this->combatService = $combatService;
        $this->systemService = $systemService;
        $this->nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $this->systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
    }

    /**
     * @return array
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * @param array $jobData
     */
    public function addJob($jobData = [])
    {
        $this->jobs[] = $jobData;
    }

    /**
     * This runs to check if coding jobs are finished.
     */
    public function loopJobs()
    {
        $now = new \DateTime();
        /* first we deal with all the coding jobs */
        foreach ($this->jobs as $jobId => $jobData) {
            // if the job is finished now
            if ($jobData['completionDate'] <= $now) {
                // resolve the job
                $jobData = (isset($this->jobs[$jobId])) ? $this->jobs[$jobId] : false;
                if ($jobData) {
                    $result = $this->codingService->resolveCoding($jobData);
                    if ($result) {
                        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $jobData['profileId']);
                        /** @var Profile $profile */
                        $this->storeNotification($profile, $result['message'], $result['severity']);
                    }
                    // remove job from server
                    unset($this->jobs[$jobId]);
                };
            }
        }
        // now we iterate connected sockets for actions
        $ws = $this->getWebsocketServer();
        foreach ($ws->getClients() as $wsClient) {
            /** @var ConnectionInterface $wsClient */
            /** @noinspection PhpUndefinedFieldInspection */
            $resourceId = $wsClient->resourceId;
            $clientData = $ws->getClientData($resourceId);
            // skip sockets that are not properly connected yet
            if (!$clientData) continue;
            if (!$clientData->hash) continue;
            // first we get amount of notifications and actiontime
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            if (!$user) continue;
            /** @var User $user */
            $profile = $user->getProfile();
            /** @var Profile $profile */
            $this->checkCodebreaker($wsClient);
            $notificationRepo = $this->entityManager->getRepository('Netrunners\Entity\Notification');
            /** @var NotificationRepository $notificationRepo */
            $countUnreadNotifications = $notificationRepo->countUnreadByProfile($profile);
            $actionTimeRemaining = 0;
            if (!empty($clientData->action)) {
                $now = new \DateTime();
                $completionDate = $clientData->action['completion'];
                /** @var \DateTime $completionDate */
                $actionTimeRemaining = $completionDate->getTimestamp() - $now->getTimestamp();
            }
            $response = array(
                'command' => 'ticker',
                'amount' => $countUnreadNotifications,
                'actionTimeRemaining' => $actionTimeRemaining
            );
            $wsClient->send(json_encode($response));
            // now handle pending actions
            $gameClientResponse = false;
            $clientData = $ws->getClientData($resourceId);
            if (empty($clientData->action)) continue;
            $actionData = (object)$clientData->action;
            $completionDate = $actionData->completion;
            if ($now < $completionDate) continue;
            $ws->clearClientActionData($resourceId);
            switch ($actionData->command) {
                default:
                    break;
                case 'executeprogram':
                    $parameter = (object)$actionData->parameter;
                    $file = $this->entityManager->find('Netrunners\Entity\File', $parameter->fileId);
                    /** @var File $file */
                    switch ($file->getFileType()->getId()) {
                        default:
                            break;
                        case FileType::ID_PORTSCANNER:
                            $system = $this->entityManager->find('Netrunners\Entity\System', $parameter->systemId);
                            /** @var System $system */
                            $gameClientResponse = $this->fileService->executePortscanner($file, $system);
                            break;
                        case FileType::ID_JACKHAMMER:
                            $system = $this->entityManager->find('Netrunners\Entity\System', $parameter->systemId);
                            /** @var System $system */
                            $nodeId = $parameter->nodeId;
                            $node = $this->entityManager->find('Netrunners\Entity\Node', $nodeId);
                            /** @var Node $node */
                            $gameClientResponse = $this->fileService->executeJackhammer($resourceId, $file, $system, $node);
                            break;
                        case FileType::ID_SIPHON:
                            $miner = $this->entityManager->find('Netrunners\Entity\File', $parameter->minerId);
                            /** @var File $miner */
                            $gameClientResponse = $this->fileService->executeSiphon($file, $miner);
                            break;
                        case FileType::ID_MEDKIT:
                            $gameClientResponse = $this->fileService->executeMedkit($file);
                            break;
                        case FileType::ID_PROXIFIER:
                            $gameClientResponse = $this->fileService->executeProxifier($file);
                            break;
                    }
                    break;
                case 'homerecall':
                    $gameClientResponse = $this->systemService->homeRecallAction($resourceId, false);
                    break;
            }
            if ($gameClientResponse) {
                $gameClientResponse->send();
            }
        }
        /* now we check for milkruns that should expire */
        $milkrunInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\MilkrunInstance');
        /** @var MilkrunInstanceRepository $milkrunInstanceRepo */
        $expiringMilkruns = $milkrunInstanceRepo->findForExpiredLoop();
        foreach ($expiringMilkruns as $expiringMilkrun) {
            /** @var MilkrunInstance $expiringMilkrun */
            $expiringMilkrun->setExpired(true);
            $this->entityManager->flush($expiringMilkrun);
            $targetClient = NULL;
            $targetClientData = NULL;
            foreach ($ws->getClients() as $wsClient) {
                /** @noinspection PhpUndefinedFieldInspection */
                $clientData = $ws->getClientData($wsClient->resourceId);
                if (empty($clientData['milkrun'])) continue;
                if ($clientData['milkrun']['id'] == $expiringMilkrun->getId()) {
                    $targetClient = $wsClient;
                    $targetClientData = $clientData;
                    break;
                }
            }
            if ($targetClient && $targetClientData) {
                /** @noinspection PhpUndefinedFieldInspection */
                $resourceId = $targetClient->resourceId;
                /* send message */
                $message = $this->translate('Your current milkrun has expired before you could complete it');
                $response = new GameClientResponse($resourceId);
                $response->setCommand(GameClientResponse::COMMAND_STOPMILKRUN)->setSilent(true);
                $response->send();
                $response->reset()->addMessage($message);
                $profile = $expiringMilkrun->getProfile();
                $profile->setFaileddMilkruns($profile->getFaileddMilkruns()+1);
                $this->entityManager->flush($profile);
                $this->createProfileFactionRating(
                    $profile,
                    $expiringMilkrun,
                    NULL,
                    NULL,
                    ProfileFactionRating::SOURCE_ID_MILKRUN,
                    $expiringMilkrun->getLevel() * -1,
                    $expiringMilkrun->getLevel() * -1,
                    $expiringMilkrun->getSourceFaction(),
                    $expiringMilkrun->getTargetFaction()
                );
                $response->send();
            }
            else {
                /* store notification */
                $this->storeNotification(
                    $expiringMilkrun->getProfile(),
                    'Your current milkrun has expired before you could complete it',
                    Notification::SEVERITY_WARNING
                );
                $profile = $expiringMilkrun->getProfile();
                $profile->setFaileddMilkruns($profile->getFaileddMilkruns()+1);
                $this->entityManager->flush($profile);
                $this->createProfileFactionRating(
                    $profile,
                    $expiringMilkrun,
                    NULL,
                    NULL,
                    ProfileFactionRating::SOURCE_ID_MILKRUN,
                    $expiringMilkrun->getLevel() * -1,
                    $expiringMilkrun->getLevel() * -1,
                    $expiringMilkrun->getSourceFaction(),
                    $expiringMilkrun->getTargetFaction()
                );
            }
        }
        /* now we check for missions that should expire */
        $missionRepo = $this->entityManager->getRepository('Netrunners\Entity\Mission');
        /** @var MissionRepository $missionRepo */
        $expiringMissions = $missionRepo->findForExpiredLoop();
        foreach ($expiringMissions as $expiringMission) {
            /** @var Mission $expiringMission */
            $expiringMission->setExpired(true);
            $targetFile = $expiringMission->getTargetFile();
            if ($targetFile) {
                $expiringMission->setTargetFile(NULL);
            }
            $this->entityManager->flush($expiringMission);
            if ($targetFile) {
                $this->entityManager->remove($targetFile);
                $this->entityManager->flush($targetFile);
            }
            $targetClient = NULL;
            $targetClientData = NULL;
            $missionProfile = $expiringMission->getProfile();
            foreach ($ws->getClients() as $wsClient) {
                /** @noinspection PhpUndefinedFieldInspection */
                $clientData = $ws->getClientData($wsClient->resourceId);
                if ($clientData['profileId'] == $missionProfile->getId()) {
                    $targetClient = $wsClient;
                    $targetClientData = $clientData;
                    break;
                }
            }
            if ($targetClient && $targetClientData) {
                /* send message */
                $message = $this->translate('Your current mission has expired before you could complete it');
                /** @noinspection PhpUndefinedFieldInspection */
                $responseMission = new GameClientResponse($targetClient->resourceId);
                $responseMission->addMessage($message);
                $responseMission->send();
                $profile = $expiringMission->getProfile();
                $profile->setFailedMissions($profile->getFailedMissions()+1);
                $this->entityManager->flush($profile);
                $this->createProfileFactionRating(
                    $profile,
                    NULL,
                    $expiringMission,
                    NULL,
                    ProfileFactionRating::SOURCE_ID_MISSION,
                    $expiringMission->getLevel() * -2,
                    $expiringMission->getLevel() * -1,
                    $expiringMission->getSourceFaction(),
                    $expiringMission->getTargetFaction()
                );
            }
            else {
                /* store notification */
                $this->storeNotification(
                    $expiringMission->getProfile(),
                    'Your current mission has expired before you could complete it',
                    Notification::SEVERITY_WARNING
                );
                $profile = $expiringMission->getProfile();
                $profile->setFailedMissions($profile->getFailedMissions()+1);
                $this->entityManager->flush($profile);
                $this->createProfileFactionRating(
                    $profile,
                    NULL,
                    $expiringMission,
                    NULL,
                    ProfileFactionRating::SOURCE_ID_MISSION,
                    $expiringMission->getLevel() * -2,
                    $expiringMission->getLevel() * -1,
                    $expiringMission->getSourceFaction(),
                    $expiringMission->getTargetFaction()
                );
            }
        }
        return true;
    }

    /**
     *
     */
    public function loopCombatRound()
    {
        $ws = $this->getWebsocketServer();
        $combatants = $ws->getCombatants();
        // first we loop through profile attackers
        foreach ($combatants['profiles'] as $profileId => $combatData) {
            $profile = $this->entityManager->find('Netrunners\Entity\Profile', $profileId);
            /** @var Profile $profile */
            // skip if they are stunned
            if ($this->isUnderEffect($profile, Effect::ID_STUNNED)) continue;
            // get combat data
            $combatData = (object)$combatData;
            $wsClient = NULL;
            $targetWsClient = NULL;
            foreach ($ws->getClients() as $wsClientId => $xClient) {
                if ($xClient->resourceId == $combatData->attackerResourceId) {
                    $wsClient = $xClient;
                    break;
                }
            }
            $target = NULL;
            if ($combatData->npcTarget) {
                $target = $this->entityManager->find('Netrunners\Entity\NpcInstance', $combatData->npcTarget);
            }
            if ($combatData->profileTarget) {
                $target = $this->entityManager->find('Netrunners\Entity\Profile', $combatData->profileTarget);
                foreach ($ws->getClients() as $wsClientId => $xClient) {
                    if ($xClient->resourceId == $combatData->defenderResourceId) {
                        $targetWsClient = $xClient;
                        break;
                    }
                }
            }
            /** @var Profile|NpcInstance $target */
            list($attackerMessage, $defenderMessage, $flyToDefender, $nodeMessage) = $this->combatService->resolveCombatRound($profile, $target);
            if ($wsClient && $attackerMessage) {
                $wsClient->send(json_encode(['command'=>'showmessageprepend', 'message'=>$attackerMessage]));
            }
            if ($targetWsClient && $defenderMessage) {
                $targetWsClient->send(json_encode(['command'=>'showmessageprepend', 'message'=>$defenderMessage]));
                $this->updateInterfaceElement($targetWsClient->resourceId, '#current-eeg', $target->getEeg());
            }
            if ($nodeMessage) {
                $ignoredProfiles = [$profile->getId()];
                if (!$combatData->npcTarget) $ignoredProfiles[] = $target->getId();
                $this->messageEveryoneInNode(
                    ($combatData->npcTarget) ? $target->getNode() : $target->getCurrentNode(),
                    ['command' => 'showmessageprepend', 'message' => $nodeMessage],
                    NULL,
                    $ignoredProfiles
                );
            }
        }
        foreach ($combatants['npcs'] as $npcId => $combatData) {
            $npc = $this->entityManager->find('Netrunners\Entity\NpcInstance', $npcId);
            /** @var NpcInstance $npc */
            // skip if they are stunned
            if ($this->isUnderEffect($npc, Effect::ID_STUNNED)) continue;
            // get combat data
            $combatData = (object)$combatData;
            $wsClient = NULL;
            foreach ($ws->getClients() as $wsClientId => $xClient) {
                if ($xClient->resourceId == $combatData->defenderResourceId) {
                    $wsClient = $xClient;
                    break;
                }
            }
            $target = NULL;
            if ($combatData->npcTarget) {
                $target = $this->entityManager->find('Netrunners\Entity\NpcInstance', $combatData->npcTarget);
            }
            if ($combatData->profileTarget) {
                $target = $this->entityManager->find('Netrunners\Entity\Profile', $combatData->profileTarget);
            }
            list($attackerMessage, $defenderMessage, $flyToDefender, $nodeMessage) = $this->combatService->resolveCombatRound($npc, $target);
            /** @var Profile|NpcInstance $target */
            if ($wsClient && $defenderMessage) {
                $wsClient->send(json_encode(['command'=>'showmessageprepend', 'message'=>$defenderMessage]));
                if ($flyToDefender) {
                    $flyToMessage = [
                        'command' => 'flytocoords',
                        'content' => explode(',', $target->getCurrentNode()->getSystem()->getGeocoords()),
                        'silent' => true
                    ];
                    $wsClient->send(json_encode($flyToMessage));
                }
            }
            if ($nodeMessage) {
                $ignoredProfiles = [];
                if (!$combatData->npcTarget) $ignoredProfiles[] = $target->getId();
                $this->messageEveryoneInNode(
                    ($combatData->npcTarget) ? $target->getNode() : $target->getCurrentNode(),
                    ['command' => 'showmessageprepend', 'message' => $nodeMessage],
                    NULL,
                    $ignoredProfiles
                );
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param ConnectionInterface $wsClient
     * @return bool
     */
    private function checkCodebreaker(ConnectionInterface $wsClient)
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $resourceId = $wsClient->resourceId;
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        if (!$clientData->codebreaker) return true;
        $codebreakerData = $clientData->codebreaker;
        $codebreakerData['deadline']--;
        if ($codebreakerData['deadline'] < 1) {
            $this->getWebsocketServer()->setClientData($resourceId, 'codebreaker', []);
            $response = [
                'command' => 'showmessageprepend',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Codebreaker attempt failed - security level raised')
                ),
                'cleardeadline' => true,
            ];
            $profile = $this->entityManager->find('Netrunners\Entity\Profile', $clientData->profileId);
            /** @var Profile $profile */
            $connection = $this->entityManager->find('Netrunners\Entity\Connection', $codebreakerData['connectionId']);
            $this->raiseProfileSecurityRating($profile, $connection->getLevel());
            $targetSystem = $connection->getSourceNode()->getSystem();
            $this->raiseSystemAlertLevel($targetSystem, $connection->getLevel());
            $wsClient->send(json_encode($response));
        }
        else {
            $this->getWebsocketServer()->setClientData($resourceId, 'codebreaker', $codebreakerData);
        }
        return true;
    }

    /**
     *
     */
    public function loopNpcSpawn()
    {
        // despawn all temporary entities
        $temporaryNpcIds = [Npc::ID_NETWATCH_AGENT, Npc::ID_NETWATCH_INVESTIGATOR];
        foreach ($temporaryNpcIds as $despawnNpcId) {
            $despawnNpcInstances = $this->npcInstanceRepo->findByNpcId($despawnNpcId);
            foreach ($despawnNpcInstances as $despawnNpcInstance) {
                /** @var NpcInstance $despawnNpcInstance */
                if ($this->isInCombat($despawnNpcInstance)) continue;
                foreach ($despawnNpcInstance->getFiles() as $affectedFile) {
                    $despawnNpcInstance->removeFile($affectedFile);
                    $this->entityManager->remove($affectedFile);
                }
                $despawnNpcInstance->setBlasterModule(NULL);
                $despawnNpcInstance->setBladeModule(NULL);
                $despawnNpcInstance->setShieldModule(NULL);
                $this->entityManager->flush();
                $this->entityManager->remove($despawnNpcInstance);
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] disconnects from the system</pre>'),
                    $despawnNpcInstance->getName()
                );
                $this->messageEveryoneInNode($despawnNpcInstance->getNode(), $message);
            }
        }
        // now we iterate through systems and check if we need to spawn
        $systems = $this->systemRepo->findAll();
        foreach ($systems as $system) {
            /** @var System $system */
            $profile = $system->getProfile();
            $faction = $system->getFaction();
            $group = $system->getGroup();
            if ($this->npcInstanceRepo->countBySystem($system) >= $this->nodeRepo->countBySystem($system)) continue;
            $databaseNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_DATABASE);
            foreach ($databaseNodes as $databaseNode) {
                /** @var Node $databaseNode */
                $this->spawnVirus($system, $databaseNode);
                /* check if this node has already spawned a worker */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($databaseNode);
                if ($existing) continue;
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_WORKER_PROGRAM);
                /** @var Npc $npc */
                $npcInstance = $this->spawnNpcInstance($npc, $databaseNode, $profile, $faction, $group, $databaseNode);
                $this->checkNpcAggro($npcInstance);
                $this->checkAggro($npcInstance);
            }
            $firewallNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_FIREWALL);
            foreach ($firewallNodes as $firewallNode) {
                /** @var Node $firewallNode */
                /* check if this node has already spawned a bouncer */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($firewallNode);
                if ($existing) continue;
                /* looks like we can spawn it */
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_BOUNCER_ICE);
                $npcInstance = $this->spawnNpcInstance($npc, $firewallNode, $profile, $faction, $group, $firewallNode);
                $this->checkNpcAggro($npcInstance);
                $this->checkAggro($npcInstance);
            }
            $terminalNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_TERMINAL);
            foreach ($terminalNodes as $terminalNode) {
                /** @var Node $terminalNode */
                $this->spawnVirus($system, $terminalNode);
                /* check if this node has already spawned a worker */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($terminalNode);
                if ($existing) continue;
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_WORKER_PROGRAM);
                $npcInstance = $this->spawnNpcInstance($npc, $terminalNode, $profile, $faction, $group, $terminalNode);
                $this->checkNpcAggro($npcInstance);
                $this->checkAggro($npcInstance);
            }
            $recruitmentNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_RECRUITMENT);
            foreach ($recruitmentNodes as $recruitmentNode) {
                /** @var Node $recruitmentNode */
                /* check if this node has already spawned a sentinel */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($recruitmentNode);
                if ($existing) continue;
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_SENTINEL_ICE);
                $npcInstance = $this->spawnNpcInstance($npc, $recruitmentNode, $profile, $faction, $group, $recruitmentNode);
                $this->checkNpcAggro($npcInstance);
                $this->checkAggro($npcInstance);
            }
            $cpuNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_CPU);
            foreach ($cpuNodes as $cpuNode) {
                /** @var Node $cpuNode */
                /* check if this node has already spawned a debugger */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($cpuNode);
                if ($existing) continue;
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_DEBUGGER_PROGRAM);
                $npcInstance = $this->spawnNpcInstance($npc, $cpuNode, $profile, $faction, $group, $cpuNode);
                $this->checkNpcAggro($npcInstance);
                $this->checkAggro($npcInstance);
            }
            $intrusionNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_INTRUSION);
            foreach ($intrusionNodes as $intrusionNode) {
                /** @var Node $intrusionNode */
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_WILDERSPACE_INTRUDER);
                if (mt_rand(1, 100) <= $intrusionNode->getLevel()) {
                    $npcInstance = $this->spawnNpcInstance($npc, $intrusionNode);
                    $this->checkNpcAggro($npcInstance);
                    $this->checkAggro($npcInstance);
                }
            }
        }
        // now check for spawners
        $spawners = $this->fileRepo->findByCategoryForLoop(FileCategory::ID_SPAWNER);
        foreach ($spawners as $spawner) {
            /** @var File $spawner */
            switch ($spawner->getFileType()->getId()) {
                default:
                    break;
                case FileType::ID_GUARD_SPAWNER:
                    $fileData = $this->getFileData($spawner);
                    if ($fileData->npcid == 0) {
                        $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_GUARDIAN_ICE);
                        $npcInstance = $this->spawnNpcInstance(
                            $npc,
                            $spawner->getNode(),
                            $spawner->getProfile(),
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            true
                        );
                        $fileData->npcid = $npcInstance->getId();
                        $spawner->setData(json_encode($fileData));
                        $this->lowerIntegrityOfFile($spawner, 100, 10);
                        $this->checkNpcAggro($npcInstance);
                        $this->checkAggro($npcInstance);
                    }
                    break;
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param System $system
     * @param Node $node
     */
    private function spawnVirus(System $system, Node $node)
    {
        if ($this->npcInstanceRepo->countBySystem($system) < $this->nodeRepo->countBySystem($system)) {
            $possibleSpawns = [Npc::ID_MURPHY_VIRUS, Npc::ID_KILLER_VIRUS];
            $spawn = mt_rand(0, count($possibleSpawns)-1);
            $npc = $this->entityManager->find('Netrunners\Entity\Npc', $possibleSpawns[$spawn]);
            /** @var Npc $npc */
            $this->spawnNpcInstance($npc, $node);
        }
    }

    /**
     * Loop that regenerates eeg, willpower and security rating. Default loop time is 5 minutes.
     */
    public function loopRegeneration()
    {
        // iterate all profiles and trigger regen methods
        $profiles = $this->entityManager->getRepository('Netrunners\Entity\Profile')->findAll();
        foreach ($profiles as $profile) {
            /** @var Profile $profile */
            // eeg
            if ($profile->getEeg() < 100) {
                $this->regenerateEeg($profile);
            }
            // willpower
            if ($profile->getWillpower() < 100) {
                $this->regenerateWillpower($profile);
            }
            // security rating
            if ($profile->getSecurityRating() >= 1) {
                $this->regenerateSecurityRating($profile);
            }
        }
        // commit changes to db
        $this->entityManager->flush();
    }

    /**
     * @param Profile $profile
     */
    private function regenerateEeg(Profile $profile)
    {
        $currentNode = $profile->getCurrentNode();
        $nodeType = $currentNode->getNodeType();
        $amount = 1;
        if ($currentNode == $profile->getHomeNode() || $nodeType->getId() == NodeType::ID_HOME) $amount = 100 - $profile->getEeg();
        // TODO modify amount by programs in node or by effects from running programs
        $profile->setEeg($profile->getEeg() + $amount);
        if ($profile->getEeg() > 100) $profile->setEeg(100);
    }

    /**
     * @param Profile $profile
     */
    private function regenerateWillpower(Profile $profile)
    {
        $currentNode = $profile->getCurrentNode();
        $nodeType = $currentNode->getNodeType();
        $amount = 2;
        if ($currentNode == $profile->getHomeNode() || $nodeType->getId() == NodeType::ID_HOME) $amount = 100 - $profile->getWillpower();
        // TODO modify amount by programs in node or by effects from running programs
        $profile->setWillpower($profile->getWillpower() + $amount);
        if ($profile->getWillpower() > 100) $profile->setWillpower(100);
    }

    /**
     * @param Profile $profile
     */
    private function regenerateSecurityRating(Profile $profile)
    {
        $currentNode = $profile->getCurrentNode();
        $nodeType = $currentNode->getNodeType();
        $amount = 0;
        if ($currentNode == $profile->getHomeNode() || $nodeType->getId() == NodeType::ID_HOME) $amount = 1;
        // TODO modify amount by programs in node or by effects from running programs
        $profile->setSecurityRating($profile->getSecurityRating() - $amount);
        if ($profile->getSecurityRating() < 0) $profile->setSecurityRating(0);
    }

    /**
     *
     */
    public function loopNpcRoam()
    {
        $this->updatedSockets = [];
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $roamingNpcs = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance')->findBy([
            'roaming' => true
        ]);
        $currentSystem = NULL;
        $currentOwner = NULL;
        $currentFaction = NULL;
        $currentGroup = NULL;
        foreach ($roamingNpcs as $roamingNpc) {
            /** @var NpcInstance $roamingNpc */
            // skip if npc is in combat
            if ($this->isInCombat($roamingNpc)) continue;
            // skip if 50% TODO make this more dynamic
            if (mt_rand(1, 100) > 50) continue;
            $connections = $connectionRepo->findBySourceNode($roamingNpc->getNode());
            $connectionsCount = count($connections);
            if ($connectionsCount - 1 < 0) continue;
            $randConnectionIndex = mt_rand(0, $connectionsCount - 1);
            $connection = $connections[$randConnectionIndex];
            /** @var Connection $connection */
            // now we need to check a few things if the connection is secured
            if ($connection->getType() == Connection::TYPE_CODEGATE) {
                if ($roamingNpc->getProfile() && !$roamingNpc->getBypassCodegates()) continue;
                if ($currentSystem != $roamingNpc->getSystem()) {
                    $currentSystem = $roamingNpc->getSystem();
                    $currentOwner = $currentSystem->getProfile();
                    $currentFaction = $currentSystem->getFaction();
                    $currentGroup = $currentSystem->getGroup();
                }
                if (!$connection->getisOpen()) {
                    if (
                        $roamingNpc->getProfile() != $currentOwner ||
                        $roamingNpc->getGroup() != $currentGroup ||
                        $roamingNpc->getFaction() != $currentFaction)
                    {
                        // TODO add more checks here so that some entities can bypass in enemy systems
                        continue;
                    }
                }
            };
            $this->moveNpcToTargetNode($roamingNpc, $connection);
        }
        if (!empty($this->updatedSockets)) {
            foreach ($this->updatedSockets as $updatedSocketId => $updatedSocket) {
                $message = $this->showAreaMap($updatedSocketId);
                $updatedSocket->send(json_encode($message));
            }
            $this->updatedSockets = [];
        }
    }

    /**
     * This runs every 15m to determine snippet and credit gains based on node types.
     */
    public function loopResources()
    {
        $systems = $this->entityManager->getRepository('Netrunners\Entity\System')->findAll();
        foreach ($systems as $system) {
            /** @var System $system */
            if ($system->getProfile()) $this->calcSystemResourcesProfile($system);
            if ($system->getGroup()) $this->calcSystemResourcesGroup($system);
            if ($system->getFaction()) $this->calcSystemResourcesFaction($system);
            $this->checkForModifyingFiles($system);
        }
        // researcher node upgrades work like resources too
        $this->researchProgress();
        // now close all open codegates
        $this->closeOpenCodegates();
        // commit to db
        $this->entityManager->flush();
    }

    /**
     * @param System $system
     */
    private function calcSystemResourcesProfile(System $system)
    {
        $profile = $system->getProfile();
        $snippetsEarned = $this->nodeRepo->getSumResourceLevelsForSystem($system, NodeType::ID_DATABASE);
        $creditsEarned = $this->nodeRepo->getSumResourceLevelsForSystem($system, NodeType::ID_TERMINAL);
        $profile->setSnippets($profile->getSnippets()+$snippetsEarned);
        $profile->setCredits($profile->getCredits()+$creditsEarned);
    }

    /**
     * @param System $system
     */
    private function calcSystemResourcesGroup(System $system)
    {
        $group = $system->getGroup();
        $snippetsEarned = $this->nodeRepo->getSumResourceLevelsForSystem($system, NodeType::ID_DATABASE);
        $creditsEarned = $this->nodeRepo->getSumResourceLevelsForSystem($system, NodeType::ID_TERMINAL);
        $group->setSnippets($group->getSnippets()+$snippetsEarned);
        $group->setCredits($group->getCredits()+$creditsEarned);
    }

    /**
     * @param System $system
     */
    private function calcSystemResourcesFaction(System $system)
    {
        $faction = $system->getFaction();
        $snippetsEarned = $this->nodeRepo->getSumResourceLevelsForSystem($system, NodeType::ID_DATABASE);
        $creditsEarned = $this->nodeRepo->getSumResourceLevelsForSystem($system, NodeType::ID_TERMINAL);
        $faction->setSnippets($faction->getSnippets()+$snippetsEarned);
        $faction->setCredits($faction->getCredits()+$creditsEarned);
    }

    /**
     * OLD
     */
    public function loopResourcesOld()
    {
        // init var to keep track of who receives what
        $items = [];
        // get all the db nodes (for snippet generation)
        $databaseNodes = $this->nodeRepo->findByType(NodeType::ID_DATABASE);
        $system = NULL;
        $systemOwner = NULL;
        $currentNodeProfileId = NULL;
        foreach ($databaseNodes as $databaseNode) {
            /** @var Node $databaseNode */
            if ($databaseNode->getSystem() != $system) {
                // skip if the system does not have a profile
                $system = $databaseNode->getSystem();
                $systemOwner = $system->getProfile();
                $currentNodeProfileId = ($systemOwner) ? $systemOwner->getId() : NULL;
            }
            if (!$systemOwner) continue;
            if ($system->getGroup()) continue;
            if ($system->getFaction()) continue;
            // add the profile id to the items if it is not already set
            $items = $this->addProfileIdToItems($items, $currentNodeProfileId);
            $items[$currentNodeProfileId]['snippets'] += $databaseNode->getLevel();
            //$this->checkForModifyingFiles($databaseNode);
        }
        // get all the terminal nodes (for credit generation)
        $terminalNodes = $this->nodeRepo->findByType(NodeType::ID_TERMINAL);
        $system = NULL;
        $systemOwner = NULL;
        $currentNodeProfileId = NULL;
        foreach ($terminalNodes as $terminalNode) {
            /** @var Node $terminalNode */
            if ($terminalNode->getSystem() != $system) {
                $system = $terminalNode->getSystem();
                $systemOwner = $system->getProfile();
                $currentNodeProfileId = $systemOwner->getId();
            }
            if (!$systemOwner) continue;
            if ($system->getGroup()) continue;
            if ($system->getFaction()) continue;
            // add the profile id to the items if it is not already set
            $items = $this->addProfileIdToItems($items, $currentNodeProfileId);
            $items[$currentNodeProfileId]['credits'] += $terminalNode->getLevel();
            //$this->checkForModifyingFiles($terminalNode);
        }
        foreach ($items as $profileId => $amountData) {
            $profile = $this->entityManager->find('Netrunners\Entity\Profile', $profileId);
            /** @var Profile $profile */
            $profile->setSnippets($profile->getSnippets() + $amountData['snippets']);
            $profile->setCredits($profile->getCredits() + $amountData['credits']);
        }
        // researcher node upgrades work like resources too
        $this->researchProgress();
        // now close all open codegates
        $this->closeOpenCodegates();
        // commit all changes to db
        $this->entityManager->flush();
    }

    /**
     * Runs as part of the resource loop to close all open code-gates.
     */
    private function closeOpenCodegates()
    {
        $affectedConnections = $this->connectionRepo->findBy([
            'type' => Connection::TYPE_CODEGATE,
            'isOpen' => true
        ]);
        foreach ($affectedConnections as $affectedConnection) {
            /** @var Connection $affectedConnection */
            $affectedConnection->setIsOpen(false);
        }
    }

    /**
     * Part of the resource loop. Determines research progress on all researcher programs.
     */
    private function researchProgress()
    {
        $systems = $this->systemRepo->findAll();
        $fileTypeRepo = $this->entityManager->getRepository('Netrunners\Entity\FileType');
        /** @var FileTypeRepository $fileTypeRepo */
        $pftrRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFileTypeRecipe');
        /** @var ProfileFileTypeRecipeRepository $pftrRepo */
        foreach ($systems as $system) {
            /** @var System $system */
            $researchers = $this->fileRepo->findRunningFilesInSystemByType($system, true, FileType::ID_RESEARCHER);
            foreach ($researchers as $researcher) {
                /** @var File $researcher */
                $researchData = json_decode($researcher->getData());
                if (!$researchData) continue;
                $progress = (isset($researchData->progress)) ? $researchData->progress : 0;
                switch ($researchData->type) {
                    default:
                        $researchDataProgress = 0;
                        break;
                    case 'category':
                        $researchDataProgress = (mt_rand(1, 100) <= $researcher->getLevel()) ? 10 : 0;
                        //$researchDataProgress = (mt_rand(1, 100) <= 100) ? 10 : 0;
                        break;
                    case 'file-type':
                        $researchDataProgress = (mt_rand(1, 100) <= $researcher->getLevel()) ? 1 : 0;
                        //$researchDataProgress = (mt_rand(1, 100) <= 100) ? 1 : 0;
                        break;
                }
                $progress += $researchDataProgress;
                if ($progress >= 100) {
                    $researchData->progress = 0;
                    switch ($researchData->type) {
                        default:
                            break;
                        case 'category':
                            $fileCategory = $this->entityManager->find('Netrunners\Entity\FileCategory', $researchData->id);
                            $possibleFileTypes = $fileTypeRepo->findByCategoryId($researchData->id);
                            $found = NULL;
                            foreach ($possibleFileTypes as $possibleFileType) {
                                /** @var FileType $possibleFileType */
                                if (!$possibleFileType->getNeedRecipe()) continue;
                                $existingRecipe = $pftrRepo->findOneByProfileAndFileType($researcher->getProfile(), $possibleFileType);
                                if (!$existingRecipe) {
                                    $found = $possibleFileType;
                                    break;
                                }
                            }
                            if ($found instanceof FileType) {
                                $message = sprintf($this->translate("You have researched the file-type [%s] - it was added to your library."), $found->getName());
                                $this->storeNotification($researcher->getProfile(), $message, Notification::SEVERITY_SUCCESS);
                                $recipe = new ProfileFileTypeRecipe();
                                $recipe->setAdded(new \DateTime());
                                $recipe->setFileType($found);
                                $recipe->setProfile($researcher->getProfile());
                                $recipe->setRuns($researcher->getLevel());
                                $this->entityManager->persist($recipe);
                            }
                            else {
                                $message = sprintf($this->translate("You have already researched all types in category [%s]! [%s] will now stop running."), $fileCategory->getName(), $researcher->getName());
                                $this->storeNotification($researcher->getProfile(), $message, Notification::SEVERITY_WARNING);
                                $researcher->setRunning(false);
                            }
                            break;
                        case 'file-type':
                            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', $researchData->id);
                            /** @var FileType $fileType */
                            $recipe = new ProfileFileTypeRecipe();
                            $recipe->setAdded(new \DateTime());
                            $recipe->setFileType($fileType);
                            $recipe->setProfile($researcher->getProfile());
                            $recipe->setRuns($researcher->getLevel());
                            $this->entityManager->persist($recipe);
                            $message = sprintf(
                                $this->translate("You have researched a recipe for file-type [%s] with [%s] runs - it was added to your library."),
                                $fileType->getName(),
                                $recipe->getRuns()
                            );
                            $this->storeNotification($researcher->getProfile(), $message, Notification::SEVERITY_SUCCESS);
                            break;
                    }
                    $this->lowerIntegrityOfFile($researcher);
                    $researcher->setData(json_encode($researchData));
                }
                else {
                    $researchData->progress = $progress;
                    $researcher->setData(json_encode($researchData));
                }
            }
        }
    }

    /**
     * @param $items
     * @param $profileId
     * @return mixed
     */
    private function addProfileIdToItems($items, $profileId)
    {
        // check if the owning profile of this node is already in our item list, if not, add the profile
        if (!isset($items[$profileId])) $items[$profileId] = [
            'snippets' => 0,
            'credits' => 0
        ];
        return $items;
    }

    /**
     * @param Node $node
     * @return bool
     */
    private function checkForModifyingFilesOld(Node $node)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $filesInNode = $fileRepo->findByNode($node);
        foreach ($filesInNode as $fileInNode) {
            /** @var File $fileInNode */
            // skip if the file is not running or has 0 integrity or is not connected to a profile
            if (!$fileInNode->getRunning()) continue;
            if ($fileInNode->getIntegrity() < 1) continue;
            if (!$fileInNode->getProfile()) continue;
            switch ($fileInNode->getFileType()->getId()) {
                default:
                    continue;
                case FileType::ID_DATAMINER:
                case FileType::ID_COINMINER:
                    $fileData = json_decode($fileInNode->getData());
                    if (!is_object($fileData)) {
                        $fileData = json_encode(['value'=>0]);
                        $fileData = json_decode($fileData);
                    }
                    // skip if the program has already collected equal to or more than its integrity allows
                    if ($fileData->value >= $fileInNode->getIntegrity()) continue;
                    $this->lowerIntegrityOfFile($fileInNode, 50);
                    $fileData->value += $fileInNode->getLevel();
                    if ($fileData->value > $fileInNode->getIntegrity()) $fileData->value = $fileInNode->getIntegrity();
                    $fileInNode->setData(json_encode($fileData));
                    break;
            }
        }
        return true;
    }

    /**
     * @param System $system
     */
    private function checkForModifyingFiles(System $system)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $filesInNode = $fileRepo->findActiveBySystem($system);
        foreach ($filesInNode as $fileInNode) {
            /** @var File $fileInNode */
            switch ($fileInNode->getFileType()->getId()) {
                default:
                    continue;
                case FileType::ID_DATAMINER:
                case FileType::ID_COINMINER:
                    $fileData = json_decode($fileInNode->getData());
                    if (!is_object($fileData)) {
                        $fileData = json_encode(['value'=>0]);
                        $fileData = json_decode($fileData);
                    }
                    // skip if the program has already collected equal to or more than its integrity allows
                    if ($fileData->value >= $fileInNode->getIntegrity()) continue;
                    $this->lowerIntegrityOfFile($fileInNode, 50);
                    $fileData->value += $fileInNode->getLevel();
                    if ($fileData->value > $fileInNode->getIntegrity()) $fileData->value = $fileInNode->getIntegrity();
                    $fileInNode->setData(json_encode($fileData));
                    break;
            }
        }
    }

}
