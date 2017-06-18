<?php

/**
 * Coding Service.
 * The service supplies methods that involve coding of programs.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FilePartRepository;
use Netrunners\Repository\FileTypeRepository;
use TmoAuth\Entity\User;

class CodingService extends BaseService
{

    /**
     * @const MIN_LEVEL
     */
    const MIN_LEVEL = 1;

    /**
     * @const MAX_LEVEL
     */
    const MAX_LEVEL = 100;

    /**
     * @var LoopService
     */
    protected $loopService;


    /**
     * CodingService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     * @param LoopService $loopService
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer,
        LoopService $loopService
    )
    {
        parent::__construct($entityManager, $viewRenderer);
        $this->loopService = $loopService;
    }

    /**
     * @param $clientData
     * @return array|bool
     */
    public function enterCodeMode($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = false;
        if ($profile->getCurrentNode()->getType() != Node::ID_CODING) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You must be in a coding node to enter coding mode</pre>')
            );
        }
        if (!$response) {
            $message = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">NeoCode - version 0.1 - \'?\' for help, \'q\' to quit</pre>');
            $response = array(
                'command' => 'enterCodeMode',
                'message' => $message
            );
        }
        return $response;
    }

    /**
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
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
                $command = 'setcodemode';
                $value = 'resource';
                break;
            case 'program':
                $command = 'setcodemode';
                $value = 'program';
                break;
        }
        $message = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">mode set to [%s]</pre>', $value);
        $response = array(
            'command' => $command,
            'value' => $value,
            'message' => $message
        );
        return $response;
    }

    /**
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
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
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Choose a number between 1 and 100</pre>')
            );
        }
        else {
            $value = false;
            $parameter = (int)$parameter;
            if ($parameter < 1 || $parameter > 100) {
                $command = 'showmessage';
                $message = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Choose a number between 1 and 100</pre>');
            }
            else {
                $command = 'setCodeLevel';
                $value = $parameter;
                $message = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">level set to [%s]</pre>', $parameter);
            }
            $response = array(
                'command' => $command,
                'value' => $value,
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    /**
     * @param $clientData
     * @param $contentArray
     * @param $codeOptions
     * @return array|bool
     */
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
                case 'coinminer':
                    $typeId = FileType::ID_COINMINER;
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
            $message .= sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-10s: %s</pre>', "resources", $partsString);
            // add optional parts to the ouput
            $partsString = '';
            foreach ($fileType->getOptionalFileParts() as $filePart) {
                /** @var FilePart $filePart */
                $name = $filePart->getName();
                $shortName = explode(' ', $name);
                $partsString .= $shortName[0] . ' ';
            }
            $message .= sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-10s: %s</pre>', "optional", $partsString);
        }
        else {
            /* resource */
            $message .= sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%-10s: %s</pre>', "snippets", ($codeOptions->fileLevel) ? $codeOptions->fileLevel : 'unknown');
        }
        // build response
        $response = array(
            'command' => 'showmessage',
            'message' => $message
        );
        // init response
        return $response;
    }

    /**
     * @param $clientData
     * @param $contentArray
     * @param $codeOptions
     * @return array|bool
     */
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
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>', $message);
            $response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        else {
            $value = false;
            switch ($parameter) {
                default:
                    $command = 'showmessage';
                    $message = '<pre style="white-space: pre-wrap;" class="text-warning">Invalid type given</pre>';
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
                case 'coinminer':
                    $command = 'setcodetype';
                    $value = $parameter;
                    $message = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">type set to [%s]</pre>', $parameter);
                    break;
            }
            $response = array(
                'command' => $command,
                'value' => $value,
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    /**
     * @param $clientData
     * @param $codeOptions
     * @return array|bool
     */
    public function commandCode($clientData, $codeOptions)
    {
        $mode = $codeOptions->mode;
        switch ($mode) {
            default:
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>', "Invalid code mode")
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

    /**
     * @param $clientData
     * @param $codeOptions
     * @return array|bool
     */
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
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need to specify a type first</pre>')
            );
        }
        $level = $codeOptions->fileLevel;
        if (!$response && $level === 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need to specify a level first</pre>')
            );
        }
        $filePart = NULL;
        if (!$response) {
            $filePart = $this->entityManager->find('Netrunners\Entity\FilePart', FilePart::$revLookup[$type]);
            if ($level > $profile->getSnippets()) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need %s snippets to code the %s</pre>', $level, $type)
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

            $this->loopService->addJob([
                'difficulty' => $difficulty,
                'modifier' => $modifier,
                'completionDate' => $completionDate,
                'typeId' => $filePartId,
                'type' => 'resource',
                'mode' => 'resource',
                'skills' => $skillList,
                'profileId' => $profile->getId(),
                'socketId' => $clientData->socketId,
                'nodeId' => $profile->getCurrentNode()->getId()
            ]);
            $response = array(
                'command' => 'updateClientData',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You start coding the %s for %s snippets</pre>', $filePart->getName(), $level),
                'clientData' => $clientData
            );
            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - $level);
            $this->entityManager->flush($profile);

        }
        return $response;
    }

    /**
     * @param $clientData
     * @param $codeOptions
     * @return array|bool
     */
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
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need to specify a type first</pre>')
            );
        }
        $level = $codeOptions->fileLevel;
        if (!$response && $level === 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need to specify a level first</pre>')
            );
        }
        $fileType = NULL;
        if (!$response) {
            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::$revLookup[$type]);
            if ($level > $profile->getSnippets()) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need %s snippets to code the %s</pre>', $level, $type)
                );
            }
        }
        // get the fpi-repo, we'll need it from now on
        $filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
        /** @var FilePartInstanceRepository $filePartInstanceRepo */
        // now we check if the player has all the needed resources
        if (!$response) {
            /** @var FileType $fileType */
            $neededResources = $fileType->getFileParts();
            $missingResources = [];
            foreach ($neededResources as $neededResource) {
                /** @var FilePart $neededResource */
                $filePartInstances = $filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level);
                if (empty($filePartInstances)) {
                    $missingResources[] = sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need this resource with at least level %s to code the %s : [%s]</pre>', $level, $type, $neededResource->getName());
                }
            }
            if (!empty($missingResources)) {
                $response = array(
                    'command' => 'showoutput',
                    'message' => $missingResources
                );
            }
        }
        // check if the player can store the file in his total storage
        if (!$response && !$this->canStoreFileOfSize($profile, $fileType->getSize())) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You do not have storage to code the %s - you need %s more storage units - build more storage nodes</pre>', $type, $fileType->getSize())
            );
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

            $this->loopService->addJob([
                'difficulty' => $difficulty,
                'modifier' => $modifier,
                'completionDate' => $completionDate,
                'typeId' => $fileTypeId,
                'type' => 'program',
                'mode' => 'program',
                'skills' => $skillList,
                'profileId' => $profile->getId(),
                'socketId' => $clientData->socketId,
                'nodeId' => $profile->getCurrentNode()->getId()
            ]);

            $response = array(
                'command' => 'updateClientData',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You start coding the %s for %s snippets</pre>', $fileType->getName(), $level),
                'clientData' => $clientData
            );

            $neededResources = $fileType->getFileParts();
            foreach ($neededResources as $neededResource) {
                /** @var FilePart $neededResource */
                $filePartInstances = $filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level, true);
                $filePartInstance = array_shift($filePartInstances);
                $this->entityManager->remove($filePartInstance);
            }

            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - $level);
            $this->entityManager->flush();

        }
        return $response;
    }

    /**
     * @return array
     */
    public function exitCodeMode()
    {
        $response = array(
            'command' => 'exitCodeMode'
        );
        return $response;
    }

    /**
     * @param $type
     * @param Profile $profile
     * @return int
     */
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

    /**
     * @param $type
     * @return array
     */
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

}
