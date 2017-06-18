<?php

/**
 * Profile Service.
 * The service supplies methods that resolve logic around Profile objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\SkillRatingRepository;
use TmoAuth\Entity\User;

class ProfileService extends BaseService
{

    const SKILL_CODING_STRING = 'coding';

    const SKILL_BLACKHAT_STRING = 'blackhat';

    const SKILL_WHITEHAT_STRING = 'whitehat';

    const SKILL_NETWORKING_STRING = 'networking';

    const SKILL_COMPUTING_STRING = 'computing';

    const SKILL_DATABASES_STRING = 'databases';

    const SKILL_ELECTRONICS_STRING = 'electronics';

    const SKILL_FORENSICS_STRING = 'forensics';

    const SKILL_SOCIAL_ENGINEERING_STRING = 'social engineering';

    const SKILL_CRYPTOGRAPHY_STRING = 'cryptography';

    const SKILL_REVERSE_ENGINEERING_STRING = 'reverse engineering';

    const SKILL_ADVANCED_NETWORKING_STRING = 'advanced networking';

    const SKILL_ADVANCED_CODING_STRING = 'advanced coding';

    const SKILL_BLADES_STRING = 'blades';

    const SKILL_CODE_BLADES_STRING = 'bladecoding';

    const SKILL_BLASTERS_STRING = 'blasters';

    const SKILL_CODE_BLASTERS_STRING = 'blastercoding';

    const SKILL_SHIELDS_STRING = 'shields';

    const SKILL_CODE_SHIELDS_STRING = 'shieldcoding';

    const SCORE_CREDITS_STRING = 'credits';

    const SCORE_SNIPPETS_STRING = 'snippets';

    const DEFAULT_STARTING_CREDITS = 1000;

    const DEFAULT_STARTING_SNIPPETS = 1000;

    const DEFAULT_SKILL_POINTS = 20;


    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showScore($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::SCORE_CREDITS_STRING, $profile->getCredits());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::SCORE_SNIPPETS_STRING, $profile->getSnippets());
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showSkills($resourceId)
    {
        $skillRepo = $this->entityManager->getRepository('Netrunners\Entity\Skill');
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $skills = $skillRepo->findAll();
        foreach ($skills as $skill) {
            /** @var Skill $skill */
            $skillRatingObject = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
            $skillRating = $skillRatingObject->getRating();
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%-20s: %-7s</pre>', $skill->getName(), $skillRating);
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $jobs
     * @return array|bool
     */
    public function showJobs($resourceId, $jobs)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $userJobs = [];
        foreach ($jobs as $jobId => $jobData) {
            if ($jobData['socketId'] == $clientData->socketId) {
                $userJobs[] = $jobData;
            }
        }
        $returnMessage = array();
        if (empty($userJobs)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">No running jobs</pre>')
            );
        }
        else {
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-4s|%-10s|%-20s|%-20s|%s</pre>', 'id', 'type', 'name', 'time', 'difficulty');
            foreach ($userJobs as $jobId => $jobData) {
                $type = $jobData['type'];
                $typeId = $jobData['typeId'];
                $completionDate = $jobData['completionDate'];
                $difficulty = $jobData['difficulty'];
                if ($type == 'program') {
                    $newCode = $this->entityManager->find('Netrunners\Entity\FileType', $typeId);
                }
                else {
                    $newCode = $this->entityManager->find('Netrunners\Entity\FilePart', $typeId);
                }
                $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%-4s|%-10s|%-20s|%-20s|%s</pre>', $jobId, $type, $newCode->getName(), $completionDate->format('y/m/d H:i:s'), $difficulty);
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showFilePartInstances($resourceId)
    {
        $filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
        /** @var FilePartInstanceRepository $filePartInstanceRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $filePartInstances = $filePartInstanceRepo->findForPartsCommand($profile);
        if (empty($filePartInstances)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have no file parts</pre>')
            );
        }
        else {
            foreach ($filePartInstances as $data) {
                $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%-20s: %-10s level-range: %s-%s</pre>', $data['fpname'], $data['fpicount'], $data['minlevel'], $data['maxlevel']);
            }
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showInventory($resourceId)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $files = $fileRepo->findByProfile($profile);
        $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-6s|%-10s|%-20s|%-3s|%-3s|%-3s|%s|%s|%-32s|%-32s</pre>', 'id', 'type', 'name', 'int', 'lvl', 'sze', 'r', 's', 'system', 'node');
        foreach ($files as $file) {
            /** @var File $file */
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%-6s|%-10s|%-20s|%-3s|%-3s|%-3s|%s|%s|%-32s|%-32s</pre>',
                $file->getId(),
                $file->getFileType()->getName(),
                $file->getName(),
                $file->getIntegrity(),
                $file->getLevel(),
                $file->getSize(),
                ($file->getRunning()) ? 'Y' : 'N',
                $file->getSlots(),
                ($file->getSystem()) ? $file->getSystem()->getName() : '',
                ($file->getNode()) ? $file->getNode()->getName() : ''
            );
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

}
