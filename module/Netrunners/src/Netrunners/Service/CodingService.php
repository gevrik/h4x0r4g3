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
use Netrunners\Entity\File;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartInstance;
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileFileTypeRecipe;
use Netrunners\Entity\Skill;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FileModRepository;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FilePartRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use Netrunners\Repository\ProfileFileTypeRecipeRepository;
use Zend\Mvc\I18n\Translator;

class CodingService extends BaseService
{
    const MIN_ADV_SKILL_RATING = 80;

    /**
     * @const MIN_LEVEL
     */
    const MIN_LEVEL = 1;

    /**
     * @const MAX_LEVEL
     */
    const MAX_LEVEL = 100;

    const CODING_TIME_MULTIPLIER_PROGRAM = 15;

    const CODING_TIME_MULTIPLIER_MOD = 10;

    const CODING_TIME_MULTIPLIER_RESOURCE = 5;

    /**
     * @var FilePartInstanceRepository
     */
    protected $filePartInstanceRepo;

    /**
     * @var FileModInstanceRepository
     */
    protected $fileModInstanceRepo;

    /**
     * @var FilePartRepository
     */
    protected $filePartRepo;

    /**
     * @var FileTypeRepository
     */
    protected $fileTypeRepo;

    /**
     * @var FileModRepository
     */
    protected $fileModRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;


    /**
     * CodingService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
        $this->filePartRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePart');
        $this->fileTypeRepo = $this->entityManager->getRepository('Netrunners\Entity\FileType');
        $this->fileModRepo = $this->entityManager->getRepository('Netrunners\Entity\FileMod');
        $this->fileModInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FileModInstance');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function enterCodeMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response && $currentNode->getNodeType()->getId() != NodeType::ID_CODING) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You must be in a coding node to enter coding mode')
                )
            );
        }
        if (!$this->response) {
            $message = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('NeoCode - version 0.1 - "?" for help, "q" to quit')
            );
            $this->response = array(
                'command' => 'entercodemode',
                'message' => $message
            );
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has entered coding mode</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function switchCodeMode($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
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
            case 'mod':
                $value = 'mod';
                break;
        }
        $this->getWebsocketServer()->setCodingOption($resourceId, 'mode', $value);
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">mode set to [%s]</pre>'),
            $value
        );
        $this->response = array(
            'command' => 'showmessage',
            'message' => $message
        );
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function commandLevel($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true, false, true);
        // init message
        if (!$parameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Choose a number between 1 and 100')
                )
            );
        }
        else {
            if ($parameter < 1 || $parameter > 100) {
                $command = 'showmessage';
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Choose a number between 1 and 100')
                );
            }
            else {
                $command = 'showmessage';
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">level set to [%s]</pre>'),
                    $parameter
                );
                $this->getWebsocketServer()->setCodingOption($resourceId, 'fileLevel', $parameter);
            }
            $this->response = array(
                'command' => $command,
                'message' => $message
            );
        }
        // init response
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $codeOptions
     * @return array|bool
     */
    public function commandOptions($resourceId, $codeOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
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
            $fileType = $this->fileTypeRepo->find($codeOptions->fileType);
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
            if ($fileType) {
                foreach ($fileType->getFileParts() as $filePart) {
                    /** @var FilePart $filePart */
                    $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $filePart, ($codeOptions->fileLevel) ? $codeOptions->fileLevel : 1);
                    if (empty($filePartInstances)) {
                        $partsString .= '<span class="text-danger">' . $filePart->getName() . '</span> ';
                    }
                    else {
                        $partsString .= '<span class="text-success">' . $filePart->getName() . '</span> ';
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
        else if ($codeOptions->mode == 'mod') {
            /* filemod mode */
            $fileType = $this->fileModRepo->find($codeOptions->fileType);
            /** @var FileMod $fileType*/
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                $this->translate('type'),
                ($fileType) ? $fileType->getName() : $this->translate('not set')
            );
            $partsString = '';
            if ($fileType) {
                foreach ($fileType->getFileParts() as $filePart) {
                    /** @var FilePart $filePart */
                    $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $filePart, ($codeOptions->fileLevel) ? $codeOptions->fileLevel : 1);
                    if (empty($filePartInstances)) {
                        $partsString .= '<span class="text-danger">' . $filePart->getName() . '</span> ';
                    }
                    else {
                        $partsString .= '<span class="text-success">' . $filePart->getName() . '</span> ';
                    }
                }
                $message .= sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-10s: %s</pre>',
                    $this->translate('resources'),
                    $partsString
                );
            }
        }
        else {
            /* resource mode */
            $fileType = $this->filePartRepo->find($codeOptions->fileType);
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
        $this->response = array(
            'command' => 'showmessage',
            'message' => $message
        );
        // init response
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @param $codeOptions
     * @return array|bool
     */
    public function commandType($resourceId, $contentArray, $codeOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        // init message
        $message = '';
        $codeMode = $codeOptions->mode;
        switch ($codeMode) {
            default:
            case 'resource':
                $typeRepository = $this->filePartRepo;
                /** @var FilePartRepository $typeRepository */
                break;
            case 'program':
                $typeRepository = $this->fileTypeRepo;
                /** @var FileTypeRepository $typeRepository */
                break;
            case 'mod':
                $typeRepository = $this->fileModRepo;
                /** @var FileModRepository $typeRepository */
                break;
        }
        if (!$parameter) {
            /* if no param was given we return a list of possible options */
            $fileTypes = $typeRepository->findForCoding($this->user->getProfile());
            foreach ($fileTypes as $fileType) {
                /** @var FileType|FilePart $fileType */
                $message .= $fileType->getName() . ' ';
            }
            $returnMessage = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                wordwrap($message, 120)
            );
            $this->response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        else {
            /* param was given - we need to check if this is a valid filetype, filepart or mod */
            $message = false;
            $entity = $typeRepository->findLikeName($parameter);
            if (!$entity instanceof FilePart && !$entity instanceof FileType && !$entity instanceof FileMod) {
                /** @var FilePart|FileType|FileMod $entity */
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid type given')
                );
            }
            // check if they should not be able to code this
            if ($entity instanceof FileType) {
                if (!$entity->getCodable()) {
                    $message = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid type given')
                    );
                }
                if ($entity->getNeedRecipe()) {
                    $message = $this->checkForRecipe($profile, $entity);
                }
            }
            // add message if not already set
            if (!$message) {
                $value = $entity->getId();
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">type set to [%s]</pre>'),
                    $parameter
                );
                // set coding options on client data
                $this->getWebsocketServer()->setCodingOption($resourceId, 'fileType', $value);
            }
            $this->response = array(
                'command' => 'showmessage',
                'message' => $message
            );
        }
        // init response
        return $this->response;
    }

    /**
     * @param Profile $profile
     * @param FileType $fileType
     * @return bool|string
     */
    private function checkForRecipe(Profile $profile, FileType $fileType)
    {
        $profileFileTypeRecipeRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFileTypeRecipe');
        /** @var ProfileFileTypeRecipeRepository $profileFileTypeRecipeRepo */
        $message = false;
        if (!$profileFileTypeRecipeRepo->findOneByProfileAndFileType($profile, $fileType)) {
            $message = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('You do not have the needed recipe')
            );
        }
        return $message;
    }

    /**
     * @param int $resourceId
     * @param $codeOptions
     * @param $contentArray
     * @return array|bool
     */
    public function commandCode($resourceId, $codeOptions, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $mode = $codeOptions->mode;
        $amount = $this->getNextParameter($contentArray, false, true );
        if (!$amount) $amount = 1;
        if ($amount > 5) $amount = 5;
        switch ($mode) {
            default:
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid code mode')
                    )
                );
                break;
            case 'mod':
                $this->response = $this->codeFileMod($codeOptions);
                break;
            case 'program':
                $this->response = $this->codeProgram($codeOptions);
                break;
            case 'resource':
                $this->response = $this->codeResource($codeOptions, $amount);
                break;
        }
        return $this->response;
    }

    /**
     * @param $codeOptions
     * @param int $amount
     * @return array|bool
     */
    private function codeResource($codeOptions, $amount = 1)
    {
        $profile = $this->user->getProfile();
        $type = (int)$codeOptions->fileType;
        // check if a type has been set
        if ($type === 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a type first')
                )
            );
        }
        // check if a level has been set
        $level = $codeOptions->fileLevel;
        $totalSnippets = $level * $amount;
        if (!$this->response && $level === 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a level first')
                )
            );
        }
        $filePart = NULL;
        if (!$this->response) {
            $filePart = $this->entityManager->find('Netrunners\Entity\FilePart', $type);
            if (!$filePart) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Invalid file part: %s</pre>'),
                        htmLawed($type,['safe'=>1,'elements'=>'strong'])
                    )
                );
            }
            if (!$this->response && $level > $profile->getSnippets()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s snippets to code %s %s</pre>'),
                        $totalSnippets,
                        $amount,
                        $filePart->getName()
                    )
                );
            }
        }
        /* now check if advanced coding is involved and check skill rating requirements */
        $skillList = $this->getSkillListForType($codeOptions);
        if (!$this->response && in_array('advanced-coding', $skillList)) {
            $this->checkAdvancedCoding($profile, Skill::ID_CODING);
        }
        if (!$this->response && in_array('advanced-networking', $skillList)) {
            $this->checkAdvancedCoding($profile, Skill::ID_NETWORKING);
        }
        /* checks passed, we can now create the file part */
        if (!$this->response) {
            /** @var FilePart $filePart */
            $difficulty = $level;
            $skillRating = $this->getSkillRating($profile, Skill::ID_CODING);
            $skillModifier = $this->getSkillModifierForFilePart($filePart, $profile);
            $modifier = floor(($skillRating + $skillModifier)/2);
            $modifier = (int)$modifier;
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . ($difficulty*self::CODING_TIME_MULTIPLIER_RESOURCE) . 'S'));
            $filePartId = $filePart->getId();
            for ($x = 1; $x<=$amount; $x++) {
                $this->getWebsocketServer()->addJob([
                    'difficulty' => $difficulty,
                    'modifier' => $modifier,
                    'completionDate' => $completionDate,
                    'typeId' => $filePartId,
                    'type' => 'resource',
                    'mode' => 'resource',
                    'skills' => $skillList,
                    'profileId' => $profile->getId(),
                    'socketId' => $this->clientData->socketId,
                    'nodeId' => $profile->getCurrentNode()->getId()
                ]);
            }
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You start coding %s %s for %s snippets</pre>'),
                    $amount,
                    $filePart->getName(),
                    $totalSnippets
                )
            );
            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - $totalSnippets);
            $this->entityManager->flush($profile);
        }
        return $this->response;
    }

    /**
     * @param $codeOptions
     * @return array|false
     */
    private function codeFileMod($codeOptions)
    {
        $profile = $this->user->getProfile();
        $type = $codeOptions->fileType;
        if ($type === 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a type first')
                )
            );
        }
        $level = (int)$codeOptions->fileLevel;
        if (!$this->response && $level === 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a level first')
                )
            );
        }
        /* check if the given type is valid */
        $fileMod = NULL;
        if (!$this->response) {
            $fileMod = $this->fileModRepo->find($type);
            if (!$fileMod) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Unknown file mod: %s</pre>'),
                        htmLawed($type,['safe'=>1,'elements'=>'strong'])
                    )
                );
            }
        }
        // now we check if the player has all the needed resources
        if (!$this->response) {
            /** @var FileMod $fileMod */
            $neededResources = $fileMod->getFileParts();
            $missingResources = [];
            foreach ($neededResources as $neededResource) {
                /** @var FilePart $neededResource */
                $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level);
                if (empty($filePartInstances)) {
                    $missingResources[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need [%s] with at least level %s to code the [%s]</pre>'),
                        $neededResource->getName(),
                        $level,
                        $fileMod->getName()
                    );
                }
            }
            if (!empty($missingResources)) {
                $this->response = array(
                    'command' => 'showoutput',
                    'message' => $missingResources
                );
            }
        }
        // check if advanced coding is involved and check for skill rating requirements
        $skillList = $this->getSkillListForType($codeOptions);
        $this->checkAdvancedCoding($profile, Skill::ID_CODING);
        /* checks passed, we can now create the mod */
        if (!$this->response) {
            $difficulty = $level;
            $skillRating = $this->getSkillRating($profile, Skill::ID_CODING);
            $skillModifier = $this->getSkillRating($profile, Skill::ID_ADVANCED_CODING);
            $modifier = floor(($skillRating + $skillModifier)/2);
            $modifier = (int)$modifier;
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . ($difficulty*self::CODING_TIME_MULTIPLIER_MOD) . 'S'));
            $fileTypeId = $fileMod->getId();
            foreach ($fileMod->getFileParts() as $neededResource) {
                /** @var FilePart $neededResource */
                $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level, true);
                $filePartInstance = array_shift($filePartInstances);
                $modifier += $filePartInstance->getLevel() - $level;
                $this->entityManager->remove($filePartInstance);
            }
            // add the coding job to the loop service
            $this->getWebsocketServer()->addJob([
                'difficulty' => $difficulty,
                'modifier' => $modifier,
                'completionDate' => $completionDate,
                'typeId' => $fileTypeId,
                'type' => 'mod',
                'mode' => 'mod',
                'skills' => $skillList,
                'profileId' => $profile->getId(),
                'socketId' => $this->clientData->socketId,
                'nodeId' => $profile->getCurrentNode()->getId()
            ]);
            // prepare response message and add clientdata
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You start coding the %s</pre>'),
                    $fileMod->getName()
                )
            );
            $this->entityManager->flush();

        }
        return $this->response;
    }

    /**
     * @param $codeOptions
     * @return array|bool
     */
    private function codeProgram($codeOptions)
    {
        $profile = $this->user->getProfile();
        $type = $codeOptions->fileType;
        if ($type === 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a type first')
                )
            );
        }
        $level = (int)$codeOptions->fileLevel;
        if (!$this->response && $level === 0) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to specify a level first')
                )
            );
        }
        /* check if the given type is valid and if they have enough snippets */
        $fileType = NULL;
        if (!$this->response) {
            $fileType = $this->fileTypeRepo->find($type);
            if (!$fileType) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Unknown file type: %s</pre>'),
                        htmLawed($type,['safe'=>1,'elements'=>'strong'])
                    )
                );
            }
            if (!$this->response && $level > $profile->getSnippets()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s snippets to code: %s</pre>'),
                        $level,
                        $fileType->getName()
                    )
                );
            }
        }
        // now we check if the player has all the needed resources
        if (!$this->response) {
            /** @var FileType $fileType */
            // check if a recipe is needed
            if ($fileType->getNeedRecipe()) {
                $message = $this->checkForRecipe($profile, $fileType);
                if ($message) {
                    $this->response = [
                        'command' => 'showmessage',
                        'message' => $message
                    ];
                }
            }
            // if they have a recipe
            if (!$this->response) {
                $neededResources = $fileType->getFileParts();
                $missingResources = [];
                foreach ($neededResources as $neededResource) {
                    /** @var FilePart $neededResource */
                    $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level);
                    if (empty($filePartInstances)) {
                        $missingResources[] = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need [%s] with at least level %s to code the [%s]</pre>'),
                            $neededResource->getName(),
                            $level,
                            $fileType->getName()
                        );
                    }
                }
                if (!empty($missingResources)) {
                    $this->response = array(
                        'command' => 'showoutput',
                        'message' => $missingResources
                    );
                }
            }
        }
        // check if the player can store the file in his total storage
        if (!$this->response && !$this->canStoreFileOfSize($profile, $fileType->getSize())) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough storage space to code the %s - you need %s more storage units - build more storage nodes</pre>'),
                    $fileType->getName(),
                    $fileType->getSize()
                )
            );
        }
        // check if advanced coding is involved and check for skill rating requirements
        $skillList = $this->getSkillListForType($codeOptions);
        if (!$this->response && in_array('advanced-coding', $skillList)) {
            $this->checkAdvancedCoding($profile, Skill::ID_CODING);
        }
        if (!$this->response && in_array('advanced-networking', $skillList)) {
            $this->checkAdvancedCoding($profile, Skill::ID_NETWORKING);
        }
        // check if the system has enough coding levels to support this job
        $alljobs = $this->getWebsocketServer()->getJobs();
        $systemJobAmount = 0;
        $currentSystem = $profile->getCurrentNode()->getSystem();
        foreach ($alljobs as $alljobId => $jobData) {
            if ($jobData['mode'] != 'program') continue;
            $codeNode = $this->entityManager->find('Netrunners\Entity\Node', $jobData['nodeId']);
            if ($codeNode->getSystem() == $currentSystem) {
                $systemJobAmount++;
            }
        }
        if (!$this->response && $systemJobAmount >= $this->getTotalSystemValueByNodeType($currentSystem, self::VALUE_TYPE_CODINGNODELEVELS)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('The system does not have enough coding rating to accept another coding job - please wait until another job has finished')
                )
            );
        }
        /* checks passed, we can now create the file */
        if (!$this->response) {
            $difficulty = $level;
            $skillRating = $this->getSkillRating($profile, Skill::ID_CODING);
            $skillModifier = $this->getSkillModifierForFileType($fileType, $profile);
            $modifier = floor(($skillRating + $skillModifier)/2);
            $modifier = (int)$modifier;
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . ($difficulty*self::CODING_TIME_MULTIPLIER_PROGRAM) . 'S'));
            $fileTypeId = $fileType->getId();
            foreach ($fileType->getFileParts() as $neededResource) {
                /** @var FilePart $neededResource */
                $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level, true);
                $filePartInstance = array_shift($filePartInstances);
                $modifier += $filePartInstance->getLevel();
                $this->entityManager->remove($filePartInstance);
            }
            // add the coding job to the loop service
            $this->getWebsocketServer()->addJob([
                'difficulty' => $difficulty,
                'modifier' => $modifier,
                'completionDate' => $completionDate,
                'typeId' => $fileTypeId,
                'type' => 'program',
                'mode' => 'program',
                'skills' => $skillList,
                'profileId' => $profile->getId(),
                'socketId' => $this->clientData->socketId,
                'nodeId' => $profile->getCurrentNode()->getId()
            ]);
            // prepare response message and add clientdata
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You start coding the %s for %s snippets</pre>'),
                    $fileType->getName(),
                    $level
                )
            );
            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - $level);
            $this->entityManager->flush();

        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool
     */
    public function exitCodeMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = array(
            'command' => 'exitcodemode',
            'prompt' => $this->getWebsocketServer()->getUtilityService()->showPrompt($this->clientData)
        );
        return $this->response;
    }

    /**
     * @param $codeOptions
     * @return array
     */
    private function getSkillListForType($codeOptions)
    {
        $skillList = [];
        switch ($codeOptions->mode) {
            default:
                $object = $this->filePartRepo->find($codeOptions->fileType);
                $repo = $this->entityManager->getRepository('Netrunners\Entity\FilePartSkill');
                $results = $repo->findBy([
                    'filePart' => $object
                ]);
                break;
            case 'mod':
                $advancedCodingSkill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->find(Skill::ID_ADVANCED_CODING);
                $results = [$advancedCodingSkill];
                break;
            case 'program':
                $object = $this->fileTypeRepo->find($codeOptions->fileType);
                $repo = $this->entityManager->getRepository('Netrunners\Entity\FileTypeSkill');
                $results = $repo->findBy([
                    'fileType' => $object
                ]);
                break;
        }
        foreach ($results as $result) {
            /** @var FilePartSkill|FileTypeSkill $result */
            $skillList[] = ($result instanceof Skill) ? $this->getNameWithoutSpaces($result->getName()) : $this->getNameWithoutSpaces($result->getSkill()->getName(), '-');
        }
        return $skillList;
    }

    /**
     * @param Profile $profile
     * @param $skillId
     */
    private function checkAdvancedCoding(Profile $profile, $skillId)
    {
        $skill = $this->entityManager->find('Netrunners\Entity\Skill', $skillId);
        /** @var Skill $skill */
        $skillRating = $this->getSkillRating($profile, $skill->getId());
        if ($skillRating < self::MIN_ADV_SKILL_RATING) {
            $message = '<pre style="white-space: pre-wrap;" class="text-warning">Your rating in [%s] is not high enough (need %s skill rating)</pre>';
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate($message),
                    $skill->getName(),
                    self::MIN_ADV_SKILL_RATING
                )
            );
        }
    }

    /**
     * @param $jobData
     * @return array|bool
     */
    public function resolveCoding($jobData)
    {
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $jobData['profileId']);
        if (!$profile) return false;
        /** @var Profile $profile */
        $response = false;
        $modifier = $jobData['modifier'];
        $difficulty = $jobData['difficulty'];
        $roll = mt_rand(1, 100);
        $chance = $modifier - $difficulty;
        $typeId = $jobData['typeId'];
        $recipe = false;
        // get bonus from custom-ide-file in node
        $ideFile = $this->fileRepo->findOneRunningInNodeByTypeAndProfile($profile->getCurrentNode(), $profile, FileType::ID_CUSTOM_IDE);
        if ($ideFile) {
            $chance += $this->getBonusForFileLevel($ideFile);
        }
        if ($jobData['mode'] == 'resource') {
            $basePart = $this->entityManager->find('Netrunners\Entity\FilePart', $typeId);
        }
        else if ($jobData['mode'] == 'mod') {
            $basePart = $this->entityManager->find('Netrunners\Entity\FileMod', $typeId);
        }
        else {
            $basePart = $this->entityManager->find('Netrunners\Entity\FileType', $typeId);
            /** @var FileType $basePart */
            if ($basePart->getNeedRecipe()) {
                $recipe = $this->getRecipe($profile, $basePart);
                if (!$recipe) {
                    $response = [
                        'severity' => 'danger',
                        'message' => sprintf(
                            $this->translate('Coding project failed: no valid recipe for [%s]'),
                            $basePart->getName()
                        )
                    ];
                }
            }
        }
        if (!$response) {
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
                else if ($jobData['mode'] == 'mod') {
                    // create the file mod instance
                    $newCode = new FileModInstance();
                    $newCode->setCoder($profile);
                    $newCode->setFileMod($basePart);
                    $newCode->setAdded(new \DateTime());
                    $newCode->setLevel($difficulty);
                    $newCode->setProfile($profile);
                    $this->entityManager->persist($newCode);
                }
                else {
                    $integrity = $chance - $roll;
                    if ($integrity > 100) $integrity = 100;
                    // programs
                    $newFileName = $basePart->getName();
                    $newCode = new File();
                    $newCode->setProfile($profile);
                    $newCode->setCoder($profile);
                    $newCode->setLevel($difficulty);
                    $newCode->setFileType($basePart);
                    $newCode->setCreated(new \DateTime());
                    $newCode->setExecutable($basePart->getExecutable());
                    $newCode->setIntegrity($integrity);
                    $newCode->setMaxIntegrity($integrity);
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
                // modify recipe if needed
                if ($recipe) {
                    /** @var ProfileFileTypeRecipe $recipe */
                    $newRuns = $recipe->getRuns()-1;
                    if ($newRuns < 1) {
                        $this->entityManager->remove($recipe);
                    }
                    else {
                        $recipe->setRuns($recipe->getRuns()-1);
                    }
                }
                $this->entityManager->flush();
            }
            else {
                $message = '';
                $this->learnFromFailure($profile, $jobData);
                if ($basePart instanceof FileType || $basePart instanceof FileMod) {
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
                if ($ideFile) $this->lowerIntegrityOfFile($ideFile);
                $this->entityManager->flush();
            }
        }
        return $response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showRecipes($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $profileFileTypeRecipeRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFileTypeRecipe');
        /** @var ProfileFileTypeRecipeRepository $profileFileTypeRecipeRepo */
        $recipes = $profileFileTypeRecipeRepo->findBy([
            'profile' => $profile
        ]);
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-19s|%-32s|%-4s</pre>',
            $this->translate('ADDED'),
            $this->translate('FILETYPE'),
            $this->translate('RUNS')
        );
        foreach ($recipes as $recipe) {
            /** @var ProfileFileTypeRecipe $recipe */
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-19s|%-32s|%-4s</pre>',
                $recipe->getAdded()->format('Y/m/d H:i:s'),
                $recipe->getFileType()->getName(),
                $recipe->getRuns()
            );
        }
        $this->response = [
            'command' => 'showoutput',
            'message' => $returnMessage
        ];
        return $this->response;
    }

    /**
     * @param Profile $profile
     * @param FileType $fileType
     * @return mixed
     */
    private function getRecipe(Profile $profile, FileType $fileType)
    {
        $profileFileTypeRecipeRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFileTypeRecipe');
        /** @var ProfileFileTypeRecipeRepository $profileFileTypeRecipeRepo */
        return $profileFileTypeRecipeRepo->findOneByProfileAndFileType($profile, $fileType);
    }

}
