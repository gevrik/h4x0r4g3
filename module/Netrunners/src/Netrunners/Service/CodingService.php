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
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FilePartRepository;
use Netrunners\Repository\FileTypeRepository;
use TmoAuth\Entity\User;
use Zend\Mvc\I18n\Translator;

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

    const CODING_TIME_MULTIPLIER_PROGRAM = 15;

    const CODING_TIME_MULTIPLIER_RESOURCE = 5;

    /**
     * @var LoopService
     */
    protected $loopService;


    /**
     * CodingService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     * @param LoopService $loopService
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer,
        LoopService $loopService,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->loopService = $loopService;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function enterCodeMode($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = $this->isActionBlocked($resourceId);
        if (!$response && $profile->getCurrentNode()->getNodeType()->getId() != NodeType::ID_CODING) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('You must be in a coding node to enter coding mode')
                )
            );
        }
        if (!$response) {
            $message = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('NeoCode - version 0.1 - "?" for help, "q" to quit')
            );
            $response = array(
                'command' => 'entercodemode',
                'message' => $message
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function switchCodeMode($resourceId, $contentArray)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        // init message
        switch ($parameter) {
            default:
            case 'resource':
                $value = 'resource';
                break;
            case 'program':
                $value = 'program';
                break;
        }
        $this->getWebsocketServer()->setCodingOption($resourceId, 'mode', $value);
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">mode set to [%s]</pre>'),
            $value
        );
        $response = array(
            'command' => 'showmessage',
            'message' => $message
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function commandLevel($resourceId, $contentArray)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true, false, true);
        // init message
        if (!$parameter) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Choose a number between 1 and 100')
                )
            );
        }
        else {
            if ($parameter < 1 || $parameter > 100) {
                $command = 'showmessage';
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Choose a number between 1 and 100')
                );
            }
            else {
                $command = 'showmessage';
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">level set to [%s]</pre>'),
                    $parameter
                );
                $this->getWebsocketServer()->setCodingOption($resourceId, 'fileLevel', $parameter);
            }
            $response = array(
                'command' => $command,
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $codeOptions
     * @return array|bool
     */
    public function commandOptions($resourceId, $codeOptions)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $message = '';
        $message .= sprintf(
            '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
            $this->translate('mode'),
            $codeOptions->mode
        );
        $message .= sprintf(
            '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
            $this->translate('level'),
            ($codeOptions->fileLevel) ? $codeOptions->fileLevel : $this->translate('not set')
        );
        /* options are different depending on if we are in program or resource mode */
        // if we are in program mode
        if ($codeOptions->mode == 'program') {
            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', $codeOptions->fileType);
            /** @var FileType $fileType*/
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate('type'),
                ($fileType) ? $fileType->getName() : $this->translate('not set')
            );
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate('snippets'),
                ($codeOptions->fileLevel) ? $codeOptions->fileLevel : $this->translate('unknown')
            );
            $partsString = '';
            $filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
            /** @var FilePartInstanceRepository $filePartInstanceRepo */
            if ($fileType) {
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
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                    $this->translate('resources'),
                    $partsString
                );
                // add optional parts to the ouput
                $partsString = '';
                foreach ($fileType->getOptionalFileParts() as $filePart) {
                    /** @var FilePart $filePart */
                    $name = $filePart->getName();
                    $shortName = explode(' ', $name);
                    $partsString .= $shortName[0] . ' ';
                }
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                    $this->translate('optional'),
                    $partsString
                );
            }
        }
        else {
            /* resource mode */
            $fileType = $this->entityManager->find('Netrunners\Entity\FilePart', $codeOptions->fileType);
            /** @var FilePart $fileType*/
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate('type'),
                ($fileType) ? $fileType->getName() : $this->translate('not set')
            );
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate('snippets'),
                ($codeOptions->fileLevel) ? $codeOptions->fileLevel : $this->translate('unknown')
            );
        }
        // if level and type have been set, show the needed skills and chance of success
        if ($codeOptions->fileLevel && $codeOptions->fileType) {
            $skillList = $this->getSkillListForType($codeOptions);
            $chance = $this->calculateCodingSuccessChance($profile, $codeOptions);
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate("skills"),
                implode(' ', $skillList)
            );
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate('chance'),
                $chance
            );
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
     * @param int $resourceId
     * @param $contentArray
     * @param $codeOptions
     * @return array|bool
     */
    public function commandType($resourceId, $contentArray, $codeOptions)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
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
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                wordwrap($message, 120)
            );
            $response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        else {
            $message = false;
            $value = false;
            switch ($parameter) {
                default:
                    $message = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid type given')
                    );
                    break;
                case FileType::STRING_CHATCLIENT:
                    $value = FileType::ID_CHATCLIENT;
                    break;
                case FileType::STRING_DATAMINER:
                    $value = FileType::ID_DATAMINER;
                    break;
                case FilePart::STRING_CONTROLLER:
                    $value = FilePart::ID_CONTROLLER;
                    break;
                case FilePart::STRING_FRONTEND:
                    $value = FilePart::ID_FRONTEND;
                    break;
                case FilePart::STRING_WHITEHAT:
                    $value = FilePart::ID_WHITEHAT;
                    break;
                case FilePart::STRING_BLACKHAT:
                    $value = FilePart::ID_BLACKHAT;
                    break;
                case FilePart::STRING_CRYPTO:
                    $value = FilePart::ID_CRYPTO;
                    break;
                case FilePart::STRING_DATABASE:
                    $value = FilePart::ID_DATABASE;
                    break;
                case FilePart::STRING_ELECTRONICS:
                    $value = FilePart::ID_ELECTRONICS;
                    break;
                case FilePart::STRING_FORENSICS:
                    $value = FilePart::ID_FORENSICS;
                    break;
                case FilePart::STRING_NETWORK:
                    $value = FilePart::ID_NETWORK;
                    break;
                case FilePart::STRING_REVERSE:
                    $value = FilePart::ID_REVERSE;
                    break;
                case FilePart::STRING_SOCIAL:
                    $value = FilePart::ID_SOCIAL;
                    break;
                case FileType::STRING_COINMINER:
                    $value = FileType::ID_COINMINER;
                    break;
                case FileType::STRING_PORTSCANNER:
                    $value = FileType::ID_PORTSCANNER;
                    break;
                case FileType::STRING_JACKHAMMER:
                    $value = FileType::ID_JACKHAMMER;
                    break;
                case FileType::STRING_WORMER:
                    $value = FileType::ID_WORMER;
                    break;
            }
            // add message if not already set
            if (!$message) $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">type set to [%s]</pre>'),
                $parameter
            );
            // set coding options on client data
            $this->getWebsocketServer()->setCodingOption($resourceId, 'fileType', $value);
            $response = array(
                'command' => 'showmessage',
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $codeOptions
     * @return array|bool
     */
    public function commandCode($resourceId, $codeOptions)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $mode = $codeOptions->mode;
        switch ($mode) {
            default:
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid code mode')
                    )
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
        $type = (int)$codeOptions->fileType;
        if ($type === 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a type first')
                )
            );
        }
        $level = $codeOptions->fileLevel;
        if (!$response && $level === 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a level first')
                )
            );
        }
        $filePart = NULL;
        if (!$response) {
            $filePart = $this->entityManager->find('Netrunners\Entity\FilePart', $type);
            if (!$filePart) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Invalid file part: %s</pre>'),
                        htmLawed($type,['safe'=>1,'elements'=>'strong'])
                    )
                );
            }
            if (!$response && $level > $profile->getSnippets()) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s snippets to code the %s</pre>'),
                        $level,
                        $filePart->getName()
                    )
                );
            }
        }
        /* checks passed, we can now create the file part */
        if (!$response) {
            /** @var FilePart $filePart */
            $difficulty = $level;
            $skillCoding = $this->entityManager->find('Netrunners\Entity\Skill', Skill::ID_CODING);
            /** @var Skill $skillCoding */
            $skillRating = $this->getSkillRating($profile, $skillCoding);
            $skillModifier = $this->getSkillModifierForFilePart($filePart, $profile);
            $skillList = $this->getSkillListForType($codeOptions);
            $modifier = floor(($skillRating + $skillModifier)/2);
            $modifier = (int)$modifier;
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . ($difficulty*self::CODING_TIME_MULTIPLIER_RESOURCE) . 'S'));
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
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You start coding the %s for %s snippets</pre>'),
                    $filePart->getName(),
                    $level
                ),
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
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a type first')
                )
            );
        }
        $level = (int)$codeOptions->fileLevel;
        if (!$response && $level === 0) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a level first')
                )
            );
        }
        $fileType = NULL;
        if (!$response) {
            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', $type);
            if (!$fileType) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Unknown file type: %s</pre>'),
                        htmLawed($type,['safe'=>1,'elements'=>'strong'])
                    )
                );
            }
            if (!$response && $level > $profile->getSnippets()) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s snippets to code: %s</pre>'),
                        $level,
                        $fileType->getName()
                    )
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
                    $missingResources[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need this resource with at least level %s to code the %s : [%s]</pre>'),
                        $level,
                        $fileType->getName(),
                        $neededResource->getName()
                    );
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
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have storage to code the %s - you need %s more storage units - build more storage nodes</pre>'),
                    $fileType->getName(),
                    $fileType->getSize()
                )
            );
        }
        /* checks passed, we can now create the file */
        if (!$response) {
            $difficulty = $level;
            $skillCoding = $this->entityManager->find('Netrunners\Entity\Skill', Skill::ID_CODING);
            /** @var Skill $skillCoding */
            $skillRating = $this->getSkillRating($profile, $skillCoding);
            $skillModifier = $this->getSkillModifierForFileType($fileType, $profile);
            $skillList = $this->getSkillListForType($codeOptions);
            $modifier = floor(($skillRating + $skillModifier)/2);
            $modifier = (int)$modifier;
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . ($difficulty*self::CODING_TIME_MULTIPLIER_PROGRAM) . 'S'));
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
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You start coding the %s for %s snippets</pre>'),
                    $fileType->getName(),
                    $level
                ),
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
     * @param $resourceId
     * @return array
     */
    public function exitCodeMode($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $response = array(
            'command' => 'exitcodemode',
            'prompt' => $this->getWebsocketServer()->getUtilityService()->showPrompt($clientData)
        );
        return $response;
    }

    /**
     * @param $codeOptions
     * @return array
     */
    protected function getSkillListForType($codeOptions)
    {
        $skillList = [];
        switch ($codeOptions->mode) {
            default:
                $object = $this->entityManager->find('Netrunners\Entity\FilePart', $codeOptions->fileType);
                $repo = $this->entityManager->getRepository('Netrunners\Entity\FilePartSkill');
                $results = $repo->findBy([
                    'filePart' => $object
                ]);
                break;
            case 'program':
                $object = $this->entityManager->find('Netrunners\Entity\FileType', $codeOptions->fileType);
                $repo = $this->entityManager->getRepository('Netrunners\Entity\FileTypeSkill');
                $results = $repo->findBy([
                    'fileType' => $object
                ]);
                break;
        }
        foreach ($results as $result) {
            /** @var FilePartSkill|FileTypeSkill $result */
            $skillList[] = $this->getNameWithoutSpaces($result->getSkill()->getName(), '-');
        }
        return $skillList;
    }

}
