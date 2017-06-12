<?php

/**
 * Coding Service.
 * The service supplies methods that involve coding of programs.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartInstance;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FilePartRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use TmoAuth\Entity\User;

class CodingService extends BaseService
{

    /**
     * @const MIN
     */
    const MIN_LEVEL = 1;

    const MAX_LEVEL = 100;

    public function enterCodeMode($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $message = "NeoCode - version 0.1 - '?' for help, 'q' to quit";
        $response = array(
            'command' => 'enterCodeMode',
            'type' => 'sysmsg',
            'message' => $message
        );
        return $response;
    }

    public function switchCodeMode($clientData, $contentArray)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        // get parameter
        $parameter = array_shift($contentArray);
        // init message
        switch ($parameter) {
            default:
            case 'resource':
                $command = 'setCodeMode';
                $value = 'resource';
                break;
            case 'program':
                $command = 'setCodeMode';
                $value = 'program';
                break;
        }
        $message = sprintf('mode set to [%s]', $value);
        $response = array(
            'command' => $command,
            'value' => $value,
            'type' => 'sysmsg',
            'message' => $message
        );
        return $response;
    }

    public function commandLevel($clientData, $contentArray)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        // init message
        $message = '';
        if (!$parameter) {
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "Choose a number between 1 and 100.");
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => $returnMessage
            );
        }
        else {
            $value = false;
            $parameter = (int)$parameter;
            if ($parameter < 1 || $parameter > 100) {
                $command = 'showMessage';
                $message = sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "Choose a number between 1 and 100.");
            }
            else {
                $command = 'setCodeLevel';
                $value = $parameter;
                $message = sprintf('level set to [%s]', $parameter);
            }
            $response = array(
                'command' => $command,
                'value' => $value,
                'type' => 'sysmsg',
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    public function commandOptions($clientData, $contentArray, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $message = '';
        foreach ($codeOptions as $optionLabel => $optionValue)
        {
            $message .= sprintf('<pre style="white-space: pre-wrap;">%-10s: %s</pre>', $optionLabel, $optionValue);
        }
        if ($codeOptions->mode == 'program') {
            // add required parts to the output
            switch ($codeOptions->fileType) {
                default:
                    // TODO show error message
                    return true;
                    break;
                case 'chatclient':
                    $typeId = FileType::ID_CHATCLIENT;
                    break;
                case 'dataminer':
                    $typeId = FileType::ID_DATAMINER;
                    break;
            }
            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', $typeId);
            /** @var FileType $fileType*/
            $partsString = '';
            $filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
            /** @var FilePartInstanceRepository $filePartInstanceRepo */
            foreach ($fileType->getFileParts() as $filePart) {
                /** @var FilePart $filePart */
                $filePartInstances = $filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $filePart, ($codeOptions->fileLevel) ? $codeOptions->fileLevel : 1);
                $name = $filePart->getName();
                $shortName = explode(' ', $name);
                if (empty($filePartInstances)) {
                    $partsString .= '<span class="text-danger">' . $shortName[0] . '</span> ';
                }
                else {
                    $partsString .= '<span class="text-success">' . $shortName[0] . '</span> ';
                }
            }
            $message .= sprintf('<pre style="white-space: pre-wrap;">%-10s: %s</pre>', "resources", $partsString);
            // add optional parts to the ouput
            $partsString = '';
            foreach ($fileType->getOptionalFileParts() as $filePart) {
                /** @var FilePart $filePart */
                $name = $filePart->getName();
                $shortName = explode(' ', $name);
                $partsString .= $shortName[0] . ' ';
            }
            $message .= sprintf('<pre style="white-space: pre-wrap;">%-10s: %s</pre>', "optional", $partsString);
        }
        else {
            /* resource */
            $message .= sprintf('<pre style="white-space: pre-wrap;">%-10s: %s</pre>', "snippets", ($codeOptions->fileLevel) ? $codeOptions->fileLevel : 'unknown');
        }
        // build response
        $response = array(
            'command' => 'showMessage',
            'type' => 'sysmsg',
            'message' => $message
        );
        // init response
        return $response;
    }

    public function commandType($clientData, $contentArray, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        // init message
        $message = '';
        if (!$parameter) {
            $codeMode = $codeOptions->mode;
            switch ($codeMode) {
                default:
                case 'resource':
                    $typeRepository = $this->entityManager->getRepository('Netrunners\Entity\FilePart');
                    /** @var FilePartRepository $typeRepository */
                    break;
                case 'program':
                    $typeRepository = $this->entityManager->getRepository('Netrunners\Entity\FileType');
                    /** @var FileTypeRepository $typeRepository */
                    break;
            }
            $fileTypes = $typeRepository->findForCoding();
            foreach ($fileTypes as $fileType) {
                /** @var FileType $fileType */
                $name = $fileType->getName();
                $shortName = explode(' ', $name);
                $message .= $shortName[0] . ' ';
            }
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;">%s</pre>', $message);
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => $returnMessage
            );
        }
        else {
            $value = false;
            switch ($parameter) {
                default:
                    $command = 'showMessage';
                    $message = 'Invalid type given';
                    break;
                case 'chatclient':
                case 'dataminer':
                case 'controller':
                case 'frontend':
                case 'whitehat':
                case 'blackhat':
                case 'crypto':
                case 'database':
                case 'electronics':
                case 'forensics':
                case 'network':
                case 'reverse':
                case 'social':
                    $command = 'setCodeType';
                    $value = $parameter;
                    $message = sprintf('type set to [%s]', $parameter);
                    break;
            }
            $response = array(
                'command' => $command,
                'value' => $value,
                'type' => 'sysmsg',
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    public function commandCode($clientData, $codeOptions)
    {
        $mode = $codeOptions->mode;
        switch ($mode) {
            default:
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'warning',
                    'message' => sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "Invalid code mode")
                );
                break;
            case 'program':
                $response = $this->codeProgram($clientData, $codeOptions);
                break;
            case 'resource':
                $response = $this->codeResource($clientData, $codeOptions);
                break;
        }
        return $response;
    }

    protected function codeResource($clientData, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = false;
        $type = $codeOptions->fileType;
        if ($type === 0) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need to specify a type first</pre>')
            );
        }
        $level = $codeOptions->fileLevel;
        if (!$response && $level === 0) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need to specify a level first</pre>')
            );
        }
        $filePart = NULL;
        if (!$response) {
            $filePart = $this->entityManager->find('Netrunners\Entity\FilePart', FilePart::$revLookup[$type]);
            if ($level > $profile->getSnippets()) {
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'warning',
                    'message' => sprintf('<pre style="white-space: pre-wrap;">You need %s snippets to code the %s</pre>', $level, $type)
                );
            }
        }
        /* checks passed, we can now create the file part */
        if (!$response) {
            /** @var FilePart $filePart */
            $difficulty = $level;
            $skillCoding = $profile->getSkillCoding();
            $skillModifier = $this->getSkillModifier($type, $profile);
            $skillList = $this->getSkillListForType($type);
            $modifier = floor(($skillCoding + $skillModifier)/2);

            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . $difficulty . 'S'));
            $filePartId = $filePart->getId();

            $clientData->jobs[] = [
                'difficulty' => $difficulty,
                'modifier' => $modifier,
                'completionDate' => $completionDate,
                'typeId' => $filePartId,
                'type' => 'resource',
                'mode' => 'resource',
                'skills' => $skillList
            ];

            $response = array(
                'command' => 'updateClientData',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You start coding the %s for %s snippets</pre>', $filePart->getName(), $level),
                'clientData' => $clientData
            );

            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - $level);
            $this->entityManager->flush($profile);

        }
        return $response;
    }

    public function resolveCoding($jobId, $clientData)
    {
        $jobData = $clientData['jobs'][$jobId];
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
        // get user and profile
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData['userId']);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
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
                // check if a file with that name already exists in this directory
                $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepository */
                $fileNameFound = false;
                $fileNameCounter = 0;
                $newFileName = $basePart->getName();
                while (!$fileNameFound) {
                    $targetFile = $fileRepository->findFileInSystemByName(
                        $profile->getCurrentDirectory()->getSystem(),
                        $profile->getCurrentDirectory(),
                        $newFileName,
                        false
                    );
                    if (!empty($targetFile)) {
                        $fileNameCounter++;
                        $newFileName = $newFileName . '' . $fileNameCounter;
                    }
                    else {
                        $fileNameFound = true;
                    }
                }
                $rootDirectory = $profile->getCurrentDirectory();
                $system = $rootDirectory->getSystem();
                /** @var System $system */
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
                $newCode->setParent($profile->getCurrentDirectory());
                $newCode->setRunning(NULL);
                $newCode->setSize($basePart->getSize());
                $newCode->setSlots(1);
                $newCode->setSystem($profile->getCurrentDirectory()->getSystem());
                $newCode->setVersion(1);
                $this->entityManager->persist($newCode);
                $system->addFile($newCode);
                $rootDirectory->addChild($newCode);
            }
            $this->entityManager->flush();
            $this->learnFromSuccess($profile, $jobData, $roll);
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You code the %s</pre>', $basePart->getName())
            );
        }
        else {
            $this->learnFromFailure($profile, $jobData, $roll);
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You fail to code the %s</pre>', $basePart->getName())
            );
        }
        return $response;
    }

    protected function codeProgram($clientData, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = false;
        $type = $codeOptions->fileType;
        if ($type === 0) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need to specify a type first</pre>')
            );
        }
        $level = $codeOptions->fileLevel;
        if (!$response && $level === 0) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You need to specify a level first</pre>')
            );
        }
        $fileType = NULL;
        if (!$response) {
            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::$revLookup[$type]);
            if ($level > $profile->getSnippets()) {
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'warning',
                    'message' => sprintf('<pre style="white-space: pre-wrap;">You need %s snippets to code the %s</pre>', $level, $type)
                );
            }
        }
        // now we check if the player has all the needed resources
        if (!$response) {
            /** @var FileType $fileType */
            $filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
            /** @var FilePartInstanceRepository $filePartInstanceRepo */
            $neededResources = $fileType->getFileParts();
            foreach ($neededResources as $neededResource) {
                /** @var FilePart $neededResource */
                $filePartInstances = $filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level);
                if (empty($filePartInstances)) {
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'warning',
                        'message' => sprintf('<pre style="white-space: pre-wrap;">You need this resource with at least level %s to code the %s : [%s]</pre>', $level, $type, $neededResource->getName())
                    );
                    break;
                }
            }
        }
        /* checks passed, we can now create the file */
        if (!$response) {
            $difficulty = $level;
            $skillCoding = $profile->getSkillCoding();
            $skillModifier = $this->getSkillModifier($type, $profile);
            $skillList = $this->getSkillListForType($type);
            $modifier = floor(($skillCoding + $skillModifier)/2);

            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . ($difficulty*2) . 'S'));
            $fileTypeId = $fileType->getId();

            $clientData->jobs[] = [
                'difficulty' => $difficulty,
                'modifier' => $modifier,
                'completionDate' => $completionDate,
                'typeId' => $fileTypeId,
                'type' => 'program',
                'mode' => 'program',
                'skills' => $skillList
            ];

            $response = array(
                'command' => 'updateClientData',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">You start coding the %s for %s snippets</pre>', $fileType->getName(), $level),
                'clientData' => $clientData
            );

            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - $level);
            $this->entityManager->flush($profile);

        }
        return $response;
    }

    public function exitCodeMode()
    {
        $response = array(
            'command' => 'exitCodeMode'
        );
        return $response;
    }

    protected function getSkillModifier($type, Profile $profile)
    {
        switch ($type) {
            default:
                $skillModifier = 0;
                break;
            case 'controller':
                $skillModifier = $profile->getSkillCoding();
                break;
            case 'frontend':
                $skillModifier = $profile->getSkillAdvancedCoding();
                break;
            case 'whitehat':
                $skillModifier = $profile->getSkillWhitehat();
                break;
            case 'blackhat':
                $skillModifier = $profile->getSkillBlackhat();
                break;
            case 'crypto':
                $skillModifier = $profile->getSkillCryptography();
                break;
            case 'database':
                $skillModifier = $profile->getSkillDatabases();
                break;
            case 'electronics':
                $skillModifier = $profile->getSkillElectronics();
                break;
            case 'forensics':
                $skillModifier = $profile->getSkillForensics();
                break;
            case 'network':
                $skillModifier = $profile->getSkillNetworking();
                break;
            case 'reverse':
                $skillModifier = $profile->getSkillReverseEngineering();
                break;
            case 'social':
                $skillModifier = $profile->getSkillSocialEngineering();
                break;
            case 'chatclient':
                $skillModifier = $profile->getSkillNetworking();
                break;
            case 'dataminer':
                $skillModifier = floor(($profile->getSkillForensics() + $profile->getSkillNetworking())/2);
                break;
        }
        return $skillModifier;
    }

    protected function getSkillListForType($type)
    {
        $skillList = [];
        switch ($type) {
            default:
                break;
            case 'controller':
                $skillList[] = 'coding';
                break;
            case 'frontend':
                $skillList[] = 'advancedcoding';
                break;
            case 'whitehat':
                $skillList[] = 'whitehat';
                break;
            case 'blackhat':
                $skillList[] = 'blackhat';
                break;
            case 'crypto':
                $skillList[] = 'crypto';
                break;
            case 'database':
                $skillList[] = 'database';
                break;
            case 'electronics':
                $skillList[] = 'electronics';
                break;
            case 'forensics':
                $skillList[] = 'forensics';
                break;
            case 'network':
                $skillList[] = 'networking';
                break;
            case 'reverse':
                $skillList[] = 'reverse';
                break;
            case 'social':
                $skillList[] = 'social';
                break;
            case 'chatclient':
                $skillList[] = 'networking';
                break;
            case 'dataminer':
                $skillList[] = 'networking';
                $skillList[] = 'forensics';
                break;
        }
        return $skillList;
    }

    public function learnFromSuccess(Profile $profile, $jobData, $roll)
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

    public function learnFromFailure(Profile $profile, $jobData, $roll)
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

    public function getSkillRating(Profile $profile, $skillName)
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
