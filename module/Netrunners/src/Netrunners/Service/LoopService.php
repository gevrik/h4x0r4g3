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
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartInstance;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Group;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\MilkrunInstanceRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NotificationRepository;
use Netrunners\Repository\NpcInstanceRepository;
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
     * LoopService constructor.
     * @param EntityManager $entityManager
     * @param \Zend\View\Renderer\PhpRenderer $viewRenderer
     * @param FileService $fileService
     * @param CombatService $combatService
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer,
        FileService $fileService,
        CombatService $combatService,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileService = $fileService;
        $this->combatService = $combatService;
        $this->nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $this->systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
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
                $result = $this->resolveCoding($jobId);
                if ($result) {
                    $profile = $this->entityManager->find('Netrunners\Entity\Profile', $jobData['profileId']);
                    /** @var Profile $profile */
                    $this->storeNotification($profile, $result['message'], $result['severity']);
                }
                // remove job from server
                unset($this->jobs[$jobId]);
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
            if (!$clientData->hash) continue;
            // first we get amount of notifications and actiontime
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            if (!$user) return true;
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
            $response = false;
            $clientData = $ws->getClientData($resourceId);
            if (empty($clientData->action)) continue;
            $actionData = (object)$clientData->action;
            $completionDate = $actionData->completion;
            if ($now < $completionDate) continue;
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
                            $response = $this->fileService->executePortscanner($file, $system);
                            break;
                        case FileType::ID_JACKHAMMER:
                            $system = $this->entityManager->find('Netrunners\Entity\System', $parameter->systemId);
                            /** @var System $system */
                            $nodeId = $parameter->nodeId;
                            $node = $this->entityManager->find('Netrunners\Entity\Node', $nodeId);
                            /** @var Node $node */
                            $response = $this->fileService->executeJackhammer($resourceId, $file, $system, $node);
                            break;
                    }
                    break;
            }
            if ($response) {
                $response['prompt'] = $ws->getUtilityService()->showPrompt($clientData);
                $wsClient->send(json_encode($response));
            }
            $ws->setClientData($resourceId, 'action', []);
        }
        /** now we check for milkruns that should expire */
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
                /* send message */
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Your current milkrun has expired before you could complete it')
                );
                $response = [
                    'command' => 'stopmilkrun',
                    'hash' => $targetClientData->hash,
                    'content' => 'default',
                    'silent' => true
                ];
                $targetClient->send(json_encode($response));
                $response = [
                    'command' => 'showmessageprepend',
                    'hash' => $targetClientData->hash,
                    'content' => $message,
                    'prompt' => $ws->getUtilityService()->showPrompt($targetClientData)
                ];
                $targetClient->send(json_encode($response));
            }
            else {
                /* store notification */
                $this->storeNotification(
                    $expiringMilkrun->getProfile(),
                    'Your current milkrun has expired before you could complete it',
                    'warning'
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
            list($attackerMessage, $defenderMessage) = $this->combatService->resolveCombatRound($profile, $target);
            if ($wsClient && $attackerMessage) {
                $wsClient->send(json_encode(['command'=>'showmessageprepend', 'message'=>$attackerMessage]));
            }
            if ($targetWsClient && $defenderMessage) {
                $targetWsClient->send(json_encode(['command'=>'showmessageprepend', 'message'=>$defenderMessage]));
                $this->updateInterfaceElement($targetWsClient->resourceId, '#current-eeg', $target->getEeg());
            }
        }
        foreach ($combatants['npcs'] as $npcId => $combatData) {
            $npc = $this->entityManager->find('Netrunners\Entity\NpcInstance', $npcId);
            /** @var NpcInstance $npc */
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
            list($attackerMessage, $defenderMessage) = $this->combatService->resolveCombatRound($npc, $target);
            /** @var Profile|NpcInstance $target */
            if ($wsClient && $defenderMessage) {
                $wsClient->send(json_encode(['command'=>'showmessageprepend', 'message'=>$defenderMessage]));
            }
        }
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
                    $this->translate('Codebreaker attempt failed')
                )
            ];
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
        $systems = $this->systemRepo->findAll();
        foreach ($systems as $system) {
            /** @var System $system */
            if ($this->npcInstanceRepo->countBySystem($system) >= $this->nodeRepo->countBySystem($system)) continue;
            $databaseNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_DATABASE);
            foreach ($databaseNodes as $databaseNode) {
                /** @var Node $databaseNode */
                if ($this->npcInstanceRepo->countBySystem($system) >= $this->nodeRepo->countBySystem($system)) break;
                $possibleSpawns = [Npc::ID_MURPHY_VIRUS, Npc::ID_KILLER_VIRUS];
                $spawn = mt_rand(0, count($possibleSpawns)-1);
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', $possibleSpawns[$spawn]);
                /** @var Npc $npc */
                $this->spawnNpcInstance($npc, $databaseNode);
            }
            $firewallNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_FIREWALL);
            foreach ($firewallNodes as $firewallNode) {
                /** @var Node $firewallNode */
                /* check if this node has already spawned a bouncer */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($firewallNode);
                if ($existing) continue;
                /* looks like we can spawn it */
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_BOUNCER_ICE);
                /** @var Npc $npc */
                $profile = $system->getProfile();
                $faction = $system->getFaction();
                $group = $system->getGroup();
                $this->spawnNpcInstance($npc, $firewallNode, $profile, $faction, $group);
            }
            $terminalNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_TERMINAL);
            foreach ($terminalNodes as $terminalNode) {
                /** @var Node $terminalNode */
                if ($this->npcInstanceRepo->countBySystem($system) >= $this->nodeRepo->countBySystem($system)) break;
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_WORKER_PROGRAM);
                /** @var Npc $npc */
                $profile = $system->getProfile();
                $faction = $system->getFaction();
                $group = $system->getGroup();
                $this->spawnNpcInstance($npc, $terminalNode, $profile, $faction, $group);
            }
            $recruitmentNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_RECRUITMENT);
            foreach ($recruitmentNodes as $recruitmentNode) {
                /** @var Node $recruitmentNode */
                /* check if this node has already spawned a sentinel */
                $existing = $this->npcInstanceRepo->findOneByHomeNode($recruitmentNode);
                if ($existing) continue;
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_SENTINEL_ICE);
                /** @var Npc $npc */
                $profile = $system->getProfile();
                $faction = $system->getFaction();
                $group = $system->getGroup();
                $this->spawnNpcInstance($npc, $recruitmentNode, $profile, $faction, $group);
            }
            $intrusionNodes = $this->nodeRepo->findBySystemAndType($system, NodeType::ID_INTRUSION);
            foreach ($intrusionNodes as $intrusionNode) {
                /** @var Node $intrusionNode */
                $npc = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_WILDERSPACE_INTRUDER);
                /** @var Npc $npc */
                if (mt_rand(1, 100) <= $intrusionNode->getLevel()) $this->spawnNpcInstance($npc, $intrusionNode);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param Npc $npc
     * @param Node $node
     * @param Profile|NULL $profile
     * @param Faction|NULL $faction
     * @param Group|NULL $group
     */
    private function spawnNpcInstance(
        Npc $npc,
        Node $node,
        Profile $profile = NULL,
        Faction $faction = NULL,
        Group $group = NULL
    )
    {
        $nodeLevel = $node->getLevel();
        $npcInstance = new NpcInstance();
        $npcInstance->setNpc($npc);
        $npcInstance->setAdded(new \DateTime());
        $npcInstance->setProfile($profile);
        $npcInstance->setNode($node);
        $credits = mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
        $npcInstance->setCredits($npc->getBaseCredits() + $credits);
        $snippets = mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
        $npcInstance->setSnippets($npc->getBaseSnippets() + $snippets);
        $npcInstance->setAggressive($npc->getAggressive());
        $maxEeg = mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
        if ($maxEeg < 1) $maxEeg = 1;
        $npcInstance->setMaxEeg($npc->getBaseEeg() + $maxEeg);
        $npcInstance->setCurrentEeg($npc->getBaseEeg() + $maxEeg);
        $npcInstance->setDescription($npc->getDescription());
        $npcInstance->setName($npc->getName());
        $npcInstance->setFaction($faction);
        $npcInstance->setHomeNode($node);
        $npcInstance->setRoaming($npc->getRoaming());
        $npcInstance->setGroup($group);
        $npcInstance->setLevel($node->getLevel());
        $npcInstance->setSlots($npc->getBaseSlots());
        $npcInstance->setStealthing($npc->getStealthing());
        $npcInstance->setSystem($node->getSystem());
        $npcInstance->setHomeSystem($node->getSystem());
        $this->entityManager->persist($npcInstance);
        /* add skills */
        $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
        foreach ($skills as $skill) {
            /** @var Skill $skill */
            $rating = 0;
            switch ($skill->getId()) {
                default:
                    continue;
                case Skill::ID_STEALTH:
                    $rating = $npc->getBaseStealth() + mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
                    break;
                case Skill::ID_DETECTION:
                    $rating = $npc->getBaseDetection() + mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
                    break;
                case Skill::ID_BLADES:
                    $rating = $npc->getBaseBlade() + mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
                    break;
                case Skill::ID_BLASTERS:
                    $rating = $npc->getBaseBlaster() + mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
                    break;
                case Skill::ID_SHIELDS:
                    $rating = $npc->getBaseShield() + mt_rand(($nodeLevel - 1) * 10, $nodeLevel * 10);
                    break;
            }
            $skillRating = new SkillRating();
            $skillRating->setNpc($npcInstance);
            $skillRating->setProfile(NULL);
            $skillRating->setSkill($skill);
            $skillRating->setRating($rating);
            $this->entityManager->persist($skillRating);
            // add files
            switch ($npc->getId()) {
                default:
                    break;
                case Npc::ID_WILDERSPACE_INTRUDER:
                    $dropChance = $npcInstance->getLevel();
                    if (mt_rand(1, 100) <= $dropChance) {
                        $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_WILDERSPACE_HUB_PORTAL);
                        /** @var FileType $fileType */
                        $file = new File();
                        $file->setProfile(NULL);
                        $file->setLevel($dropChance);
                        $file->setCreated(new \DateTime());
                        $file->setSystem($node->getSystem());
                        $file->setName($fileType->getName());
                        $file->setNpc($npcInstance);
                        $file->setData(NULL);
                        $file->setRunning(false);
                        $file->setSlots(NULL);
                        $file->setNode(NULL);
                        $file->setCoder(NULL);
                        $file->setExecutable($fileType->getExecutable());
                        $file->setFileType($fileType);
                        $file->setIntegrity($dropChance*10);
                        $file->setMaxIntegrity($dropChance*10);
                        $file->setMailMessage(NULL);
                        $file->setModified(NULL);
                        $file->setSize($fileType->getSize());
                        $file->setVersion(1);
                        $this->entityManager->persist($file);
                    }
                    break;
            }
        }
    }

    /**
     *
     */
    public function loopNpcRoam()
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $roamingNpcs = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance')->findBy([
            'roaming' => true
        ]);
        foreach ($roamingNpcs as $roamingNpc) {
            /** @var NpcInstance $roamingNpc */
            if ($this->isInCombat($roamingNpc)) continue;
            if (mt_rand(1, 100) > 50) continue;
            $connections = $connectionRepo->findBySourceNode($roamingNpc->getNode());
            $connectionsCount = count($connections);
            $randConnectionIndex = mt_rand(0, $connectionsCount - 1);
            $connection = $connections[$randConnectionIndex];
            /** @var Connection $connection */
            if ($connection->getType() == Connection::TYPE_CODEGATE && !$connection->getisOpen()) continue;
            $this->moveNpcToTargetNode($roamingNpc, $connection);
        }
    }

    /**
     * This runs every 15m to determine snippet and credit gains based on node types.
     */
    public function loopResources()
    {
        // init var to keep track of who receives what
        $items = [];
        // get all the db nodes (for snippet generation)
        $databaseNodes = $this->nodeRepo->findByType(NodeType::ID_DATABASE);
        foreach ($databaseNodes as $databaseNode) {
            /** @var Node $databaseNode */
            $currentNodeProfileId = $databaseNode->getSystem()->getProfile()->getId();
            /** @var Profile $currentNodeProfile */
            // add the profile id to the items if it is not already set
            $items = $this->addProfileIdToItems($items, $currentNodeProfileId);
            // add the snippets
            $items[$currentNodeProfileId]['snippets'] += $databaseNode->getLevel();
            // now check if there are running files in the same node that could affect the resource amount
            $items = $this->checkForModifyingFiles($databaseNode, $currentNodeProfileId, FileType::ID_DATAMINER, $items, 'snippets');
        }
        $terminalNodes = $this->nodeRepo->findByType(NodeType::ID_TERMINAL);
        foreach ($terminalNodes as $terminalNode) {
            /** @var Node $terminalNode */
            $currentNodeProfileId = $terminalNode->getSystem()->getProfile()->getId();
            /** @var Profile $currentNodeProfile */
            // add the profile id to the items if it is not already set
            $items = $this->addProfileIdToItems($items, $currentNodeProfileId);
            $items[$currentNodeProfileId]['credits'] += $terminalNode->getLevel();
            $items = $this->checkForModifyingFiles($terminalNode, $currentNodeProfileId, FileType::ID_COINMINER, $items, 'credits');
        }
        foreach ($items as $profileId => $amountData) {
            $profile = $this->entityManager->find('Netrunners\Entity\Profile', $profileId);
            /** @var Profile $profile */
            $profile->setSnippets($profile->getSnippets() + $amountData['snippets']);
            $profile->setCredits($profile->getCredits() + $amountData['credits']);
        }
        $this->entityManager->flush();
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
     * @param $profileId
     * @param $fileTypeId
     * @param $items
     * @param $resource
     * @return mixed
     */
    private function checkForModifyingFiles(Node $node, $profileId, $fileTypeId, $items, $resource)
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
            if ($fileInNode->getFileType()->getId() == $fileTypeId) {
                if ($fileInNode->getProfile()->getId() != $profileId) {
                    // check if the owning profile of this node is already in our item list, if not, add the profile
                    if (!isset($items[$fileInNode->getProfile()->getId()])) $items[$fileInNode->getProfile()->getId()] = [
                        'snippets' => 0,
                        'credits' => 0
                    ];
                    $items[$fileInNode->getProfile()->getId()][$resource] += $fileInNode->getLevel();
                }
                else {
                    $items[$profileId][$resource] += $fileInNode->getLevel();
                }
            }
        }
        return $items;
    }

    /**
     * @param $jobId
     * @return array|bool
     */
    private function resolveCoding($jobId)
    {
        $jobData = (isset($this->jobs[$jobId])) ? $this->jobs[$jobId] : false;
        if (!$jobData) return false;
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $jobData['profileId']);
        if (!$profile) return false;
        /** @var Profile $profile */
        $modifier = $jobData['modifier'];
        $difficulty = $jobData['difficulty'];
        $roll = mt_rand(1, 100);
        $chance = $modifier - $difficulty;
        $typeId = $jobData['typeId'];
        // TODO add bonus from "custom ide" program
        if ($jobData['mode'] == 'resource') {
            $basePart = $this->entityManager->find('Netrunners\Entity\FilePart', $typeId);
        }
        else {
            $basePart = $this->entityManager->find('Netrunners\Entity\FileType', $typeId);
        }
        if ($roll <= $chance) {
            if ($jobData['mode'] == 'resource') {
                // create the file part instance
                $newCode = new FilePartInstance();
                $newCode->setCoder($profile);
                $newCode->setFilePart($basePart);
                $newCode->setLevel($difficulty);
                $newCode->setProfile($profile);
                $this->entityManager->persist($newCode);
            }
            else {
                // programs
                $newFileName = $basePart->getName();
                $newCode = new File();
                $newCode->setProfile($profile);
                $newCode->setCoder($profile);
                $newCode->setLevel($difficulty);
                $newCode->setFileType($basePart);
                $newCode->setCreated(new \DateTime());
                $newCode->setExecutable($basePart->getExecutable());
                $newCode->setIntegrity($chance - $roll);
                $newCode->setMaxIntegrity($chance - $roll);
                $newCode->setMailMessage(NULL);
                $newCode->setModified(NULL);
                $newCode->setName($newFileName);
                $newCode->setRunning(NULL);
                $newCode->setSize($basePart->getSize());
                $newCode->setSlots(1);
                $newCode->setSystem(NULL);
                $newCode->setNode(NULL);
                $newCode->setVersion(1);
                $newCode->setData(NULL);
                $this->entityManager->persist($newCode);
                $canStore = $this->canStoreFile($profile, $newCode);
                if (!$canStore) {
                    $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $jobData['nodeId']);
                    $newCode->setProfile(NULL);
                    $newCode->setSystem($targetNode->getSystem());
                    $newCode->setNode($targetNode);
                }
            }
            $add = '';
            if (!$newCode->getProfile()) {
                $add = $this->translate('<br />The file could not be stored in storage - it has been added to the node that it was coded in');
            }
            $this->learnFromSuccess($profile, $jobData);
            $completionDate = $jobData['completionDate'];
            /** @var \DateTime $completionDate */
            $response = [
                'severity' => 'success',
                'message' => sprintf(
                    $this->translate('[%s] Coding project complete: %s [level: %s]%s'),
                    $completionDate->format('Y/m/d H:i:s'),
                    $basePart->getName(),
                    $difficulty,
                    $add
                )
            ];
            $this->entityManager->flush();
        }
        else {
            $message = '';
            $this->learnFromFailure($profile, $jobData);
            if ($basePart instanceof FileType) {
                $neededParts = $basePart->getFileParts();
                foreach ($neededParts as $neededPart) {
                    /** @var FilePart $neededPart */
                    $chance = mt_rand(1, 100);
                    if ($chance > 50) {
                        if (empty($message)) $message .= '(';
                        $fpi = new FilePartInstance();
                        $fpi->setProfile($profile);
                        $fpi->setLevel($difficulty);
                        $fpi->setCoder($profile);
                        $fpi->setFilePart($neededPart);
                        $this->entityManager->persist($fpi);
                        $message .= sprintf('[%s] ', $neededPart->getName());
                    }
                }
                if (!empty($message)) $message .= 'were recovered)]';
            }
            $completionDate = $jobData['completionDate'];
            /** @var \DateTime $completionDate */
            $response = [
                'severity' => 'warning',
                'message' => sprintf(
                    $this->translate("[%s] Coding project failed: %s [level: %s] %s"),
                    $completionDate->format('Y/m/d H:i:s'),
                    $basePart->getName(),
                    $difficulty,
                    $message
                )
            ];
            $this->entityManager->flush();
        }
        // TODO lower integrity of "custom ide"
        return $response;
    }

}
