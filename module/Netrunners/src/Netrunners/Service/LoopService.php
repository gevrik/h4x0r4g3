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
use Netrunners\Entity\File;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartInstance;
use Netrunners\Entity\FileType;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\MilkrunInstanceRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NotificationRepository;
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
     * LoopService constructor.
     * @param EntityManager $entityManager
     * @param \Zend\View\Renderer\PhpRenderer $viewRenderer
     * @param FileService $fileService
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer,
        FileService $fileService,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileService = $fileService;
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
     * This runs every 5s to check if coding jobs are finished.
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
     * This runs every 15m to determine snippet and credit gains based on node types.
     */
    public function loopResources()
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        // init var to keep track of who receives what
        $items = [];
        // get all the db nodes (for snippet generation)
        $databaseNodes = $nodeRepo->findByType(NodeType::ID_DATABASE);
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
        $terminalNodes = $nodeRepo->findByType(NodeType::ID_TERMINAL);
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

    protected function addProfileIdToItems($items, $profileId)
    {
        // check if the owning profile of this node is already in our item list, if not, add the profile
        if (!isset($items[$profileId])) $items[$profileId] = [
            'snippets' => 0,
            'credits' => 0
        ];
        return $items;
    }

    protected function checkForModifyingFiles(Node $node, $profileId, $fileTypeId, $items, $resource)
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
    protected function resolveCoding($jobId)
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
            $response = [
                'severity' => 'success',
                'message' => sprintf(
                    $this->translate('[%s] Coding project complete: %s [level: %s]%s'),
                    $jobData['completionDate']->format('Y/m/d H:i:s'),
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
            $response = [
                'severity' => 'warning',
                'message' => sprintf(
                    $this->translate("[%s] Coding project failed: %s [level: %s] %s"),
                    $jobData['completionDate']->format('Y/m/d H:i:s'),
                    $basePart->getName(),
                    $difficulty,
                    $message
                )
            ];
            $this->entityManager->flush();
        }
        return $response;
    }

}
