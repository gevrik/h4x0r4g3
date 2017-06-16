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
use Netrunners\Entity\MailMessage;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Profile;

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
     *
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
            }
            $this->entityManager->flush();
            $this->learnFromSuccess($profile, $jobData, $roll);
            $response = [
                'severity' => 'success',
                'message' => sprintf("[%s] Coding project complete: %s [level: %s]", $jobData['completionDate']->format('Y/m/d H:i:s'), $basePart->getName(), $difficulty)
            ];
        }
        else {
            $this->learnFromFailure($profile, $jobData, $roll);
            $response = [
                'severity' => 'warning',
                'message' => sprintf("[%s] Coding project failed: %s [level: %s]", $jobData['completionDate']->format('Y/m/d H:i:s'), $basePart->getName(), $difficulty)
            ];
        }
        return $response;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @param $roll
     * @return bool
     */
    protected function learnFromSuccess(Profile $profile, $jobData, $roll)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skillRating = $this->getSkillRating($profile, $skillName);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skillName, $newSkillRating);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @param $roll
     * @return bool
     */
    protected function learnFromFailure(Profile $profile, $jobData, $roll)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skillRating = $this->getSkillRating($profile, $skillName);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skillName, $newSkillRating);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $skillName
     * @return int
     */
    protected function getSkillRating(Profile $profile, $skillName)
    {
        $skillRating = 0;
        switch ($skillName) {
            default:
                break;
            case 'coding':
                $skillRating = $profile->getSkillCoding();
                break;
            case 'advancedcoding':
                $skillRating = $profile->getSkillAdvancedCoding();
                break;
            case 'whitehat':
                $skillRating = $profile->getSkillWhitehat();
                break;
            case 'blackhat':
                $skillRating = $profile->getSkillBlackhat();
                break;
            case 'crypto':
                $skillRating = $profile->getSkillCryptography();
                break;
            case 'database':
                $skillRating = $profile->getSkillDatabases();
                break;
            case 'electronics':
                $skillRating = $profile->getSkillElectronics();
                break;
            case 'forensics':
                $skillRating = $profile->getSkillForensics();
                break;
            case 'networking':
                $skillRating = $profile->getSkillNetworking();
                break;
            case 'reverse':
                $skillRating = $profile->getSkillReverseEngineering();
                break;
            case 'social':
                $skillRating = $profile->getSkillSocialEngineering();
                break;
        }
        return $skillRating;
    }

    /**
     * @param Profile $profile
     * @param $skillName
     * @param $newSkillRating
     * @return bool
     */
    public function setSkillRating(Profile $profile, $skillName, $newSkillRating)
    {
        switch ($skillName) {
            default:
                break;
            case 'coding':
                $profile->setSkillCoding($newSkillRating);
                break;
            case 'advancedcoding':
                $profile->setSkillAdvancedCoding($newSkillRating);
                break;
            case 'whitehat':
                $profile->setSkillWhitehat($newSkillRating);
                break;
            case 'blackhat':
                $profile->setSkillBlackhat($newSkillRating);
                break;
            case 'crypto':
                $profile->setSkillCryptography($newSkillRating);
                break;
            case 'database':
                $profile->setSkillDatabases($newSkillRating);
                break;
            case 'electronics':
                $profile->setSkillElectronics($newSkillRating);
                break;
            case 'forensics':
                $profile->setSkillForensics($newSkillRating);
                break;
            case 'networking':
                $profile->setSkillNetworking($newSkillRating);
                break;
            case 'reverse':
                $profile->setSkillReverseEngineering($newSkillRating);
                break;
            case 'social':
                $profile->setSkillSocialEngineering($newSkillRating);
                break;
        }
        $this->entityManager->flush($profile);
        return true;
    }

}
