<?php

/**
 * Loop Service.
 * The service supplies methods that resolve logic around the loops that occur at regular intervals.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\FilePartInstance;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Profile;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;

class LoopService extends BaseService
{

    /**
     * Stores jobs that the players have started.
     * @var array
     */
    protected $jobs = [];

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
        foreach ($this->jobs as $jobId => $jobData) {
            // if the job is finished now
            if ($jobData['completionDate'] <= $now) {
                // resolve the job
                $result = $this->resolveCoding($jobId);
                if ($result) {
                    $profile = $this->entityManager->find('Netrunners\Entity\Profile', $jobData['profileId']);
                    $mail = new Notification();
                    $mail->setProfile($profile);
                    $mail->setSentDateTime($now);
                    $mail->setSubject($result['message']);
                    $mail->setSeverity($result['severity']);
                    $this->entityManager->persist($mail);
                    $this->entityManager->flush($mail);
                }
                // remove job from server
                unset($this->jobs[$jobId]);
            }
        }
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
        $databaseNodes = $nodeRepo->findByType(Node::ID_DATABASE);
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
        $terminalNodes = $nodeRepo->findByType(Node::ID_TERMINAL);
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
        $roll = rand(1, 100);
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
            $this->entityManager->flush();
            $add = '';
            if (!$newCode->getProfile()) {
                $add = '<br />The file could not be stored in storage - it has been added to the node that it was coded in';
            }
            $this->learnFromSuccess($profile, $jobData);
            $response = [
                'severity' => 'success',
                'message' => sprintf("[%s] Coding project complete: %s [level: %s]%s", $jobData['completionDate']->format('Y/m/d H:i:s'), $basePart->getName(), $difficulty, $add)
            ];
        }
        else {
            $this->learnFromFailure($profile, $jobData);
            $response = [
                'severity' => 'warning',
                'message' => sprintf("[%s] Coding project failed: %s [level: %s]", $jobData['completionDate']->format('Y/m/d H:i:s'), $basePart->getName(), $difficulty)
            ];
        }
        return $response;
    }

}
