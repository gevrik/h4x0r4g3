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
use Netrunners\Entity\Notification;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileFileTypeRecipe;
use Netrunners\Entity\Skill;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FileModRepository;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FilePartRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use Netrunners\Repository\ProfileFileTypeRecipeRepository;
use TmoAuth\Entity\Role;
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
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function enterCodeMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($currentNode->getNodeType()->getId() != NodeType::ID_CODING) {
            return $this->gameClientResponse
                ->addMessage($this->translate('You must be in a coding node to enter coding mode'))->send();
        }
        $this->gameClientResponse->addMessage(
            $this->translate('NeoCode - version 0.1 - "?" for help, "q" to quit'),
            GameClientResponse::CLASS_SYSMSG
        );
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCODEMODE);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has entered coding mode'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew(
            $currentNode,
            $message,
            GameClientResponse::CLASS_MUTED,
            $profile,
            $profile->getId()
        );
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
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
            $this->translate('mode set to [%s]'),
            $value
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function commandLevel($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        // init message
        if (!$parameter) {
            $this->gameClientResponse->addMessage($this->translate('Choose a number between 1 and 100'));
        }
        else {
            if ($parameter < 1 || $parameter > 100) {
                $message = $this->translate('Choose a number between 1 and 100');
                $this->gameClientResponse->addMessage($message);
            }
            else {
                $message = sprintf(
                    $this->translate('level set to [%s]'),
                    $parameter
                );
                $this->getWebsocketServer()->setCodingOption($resourceId, 'fileLevel', $parameter);
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            }
        }
        // init response
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $codeOptions
     * @return bool|GameClientResponse
     */
    public function commandOptions($resourceId, $codeOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $message = sprintf(
            '%-10s: %s',
            $this->translate('mode'),
            $codeOptions->mode
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            '%-10s: %s',
            $this->translate('level'),
            ($codeOptions->fileLevel) ? $codeOptions->fileLevel : $this->translate('not set')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        /* options are different depending on if we are in program or resource mode */
        // if we are in program mode
        if ($codeOptions->mode == 'program') {
            $fileType = $this->fileTypeRepo->find($codeOptions->fileType);
            /** @var FileType $fileType*/
            $message = sprintf(
                '%-10s: %s',
                $this->translate('type'),
                ($fileType) ? $fileType->getName() : $this->translate('not set')
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            $message = sprintf(
                '%-10s: %s',
                $this->translate('snippets'),
                ($codeOptions->fileLevel) ? $codeOptions->fileLevel : $this->translate('unknown')
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
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
                $message = sprintf(
                    '%-10s: %s',
                    $this->translate('resources'),
                    $partsString
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                // add optional parts to the ouput
                $partsString = '';
                foreach ($fileType->getOptionalFileParts() as $filePart) {
                    /** @var FilePart $filePart */
                    $name = $filePart->getName();
                    $shortName = explode(' ', $name);
                    $partsString .= $shortName[0] . ' ';
                }
                $message = sprintf(
                    '%-10s: %s',
                    $this->translate('optional'),
                    $partsString
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            }
        }
        else if ($codeOptions->mode == 'mod') {
            /* filemod mode */
            $fileType = $this->fileModRepo->find($codeOptions->fileType);
            /** @var FileMod $fileType*/
            $message = sprintf(
                '%-10s: %s',
                $this->translate('type'),
                ($fileType) ? $fileType->getName() : $this->translate('not set')
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
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
                $message = sprintf(
                    '%-10s: %s',
                    $this->translate('resources'),
                    $partsString
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            }
        }
        else {
            /* resource mode */
            $fileType = $this->filePartRepo->find($codeOptions->fileType);
            /** @var FilePart $fileType*/
            $message = sprintf(
                '%-10s: %s',
                $this->translate('type'),
                ($fileType) ? $fileType->getName() : $this->translate('not set')
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            $message = sprintf(
                '%-10s: %s',
                $this->translate('snippets'),
                ($codeOptions->fileLevel) ? $codeOptions->fileLevel : $this->translate('unknown')
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        }
        // if level and type have been set, show the needed skills and chance of success
        if ($codeOptions->fileLevel && $codeOptions->fileType) {
            $skillList = $this->getSkillListForType($codeOptions);
            $chance = $this->calculateCodingSuccessChance($profile, $codeOptions);
            $message = sprintf(
                '%-10s: %s',
                $this->translate("skills"),
                implode(' ', $skillList)
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            $message = sprintf(
                '%-10s: %s',
                $this->translate('chance'),
                $chance
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        }
        // return response
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @param $codeOptions
     * @return bool|GameClientResponse
     */
    public function commandType($resourceId, $contentArray, $codeOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        // init message
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
            $message = '';
            foreach ($fileTypes as $fileType) {
                /** @var FileType|FilePart $fileType */
                $message .= $fileType->getName() . ' ';
            }
            return $this->gameClientResponse->addMessage(wordwrap($message, 120), GameClientResponse::CLASS_WHITE)->send();
        }
        else {
            /* param was given - we need to check if this is a valid filetype, filepart or mod */
            $entity = $typeRepository->findLikeName($parameter);
            if (!$entity instanceof FilePart && !$entity instanceof FileType && !$entity instanceof FileMod) {
                /** @var FilePart|FileType|FileMod $entity */
                return $this->gameClientResponse->addMessage($this->translate('Invalid type given'))->send();
            }
            // check if they should not be able to code this
            if ($entity instanceof FileType) {
                if (!$entity->getCodable()) {
                    return $this->gameClientResponse->addMessage($this->translate('Invalid type given'))->send();
                }
                if ($entity->getNeedRecipe()) {
                    $message = $this->checkForRecipe($profile, $entity);
                    if ($message) {
                        return $this->gameClientResponse->addMessage($message)->send();
                    }
                }
            }
            // add message if not already set
            $value = $entity->getId();
            $message = sprintf(
                $this->translate('type set to [%s]'),
                $parameter
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            // set coding options on client data
            $this->getWebsocketServer()->setCodingOption($resourceId, 'fileType', $value);
        }
        // init response
        return $this->gameClientResponse->send();
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
        // now check, admins do not need recipes
        if (!$profileFileTypeRecipeRepo->findOneByProfileAndFileType($profile, $fileType) && !$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            $message = $this->translate('You do not have the needed recipe');
        }
        return $message;
    }

    /**
     * @param $resourceId
     * @param $codeOptions
     * @param $contentArray
     * @return bool|GameClientResponse|string
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
                return $this->gameClientResponse->addMessage($this->translate('Invalid code mode'))->send();
            case 'mod':
                return $this->codeFileMod($codeOptions);
            case 'program':
                return $this->codeProgram($codeOptions);
            case 'resource':
                return $this->codeResource($codeOptions, $amount);
        }
    }

    /**
     * @param $codeOptions
     * @param int $amount
     * @return GameClientResponse
     */
    private function codeResource($codeOptions, $amount = 1)
    {
        $profile = $this->user->getProfile();
        $type = (int)$codeOptions->fileType;
        // check if a type has been set
        if ($type === 0) {
            return $this->gameClientResponse->addMessage($this->translate('You need to specify a type first'))->send();
        }
        $level = (int)$codeOptions->fileLevel;
        $totalSnippets = $level * $amount;
        if ($level === 0) {
            return $this->gameClientResponse->addMessage($this->translate('You need to specify a level first'))->send();
        }
        $filePart = NULL;
        $filePart = $this->entityManager->find('Netrunners\Entity\FilePart', $type);
        if (!$filePart) {
            $message = sprintf(
                $this->translate('Invalid file part: %s'),
                htmLawed($type,['safe'=>1,'elements'=>'strong'])
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($level > $profile->getSnippets()) {
            $message = sprintf(
                $this->translate('You need %s snippets to code %s %s'),
                $totalSnippets,
                $amount,
                $filePart->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* now check if advanced coding is involved and check skill rating requirements */
        $skillList = $this->getSkillListForType($codeOptions);
        if (in_array('advanced-coding', $skillList)) {
            $message = $this->checkAdvancedCoding($profile, Skill::ID_CODING);
            if ($message) {
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        if (in_array('advanced-networking', $skillList)) {
            $message = $this->checkAdvancedCoding($profile, Skill::ID_NETWORKING);
            if ($message) {
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        /* checks passed, we can now create the file part */
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
        $message = sprintf(
            $this->translate('You start coding %s %s for %s snippets'),
            $amount,
            $filePart->getName(),
            $totalSnippets
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $currentSnippets = $profile->getSnippets();
        $profile->setSnippets($currentSnippets - $totalSnippets);
        $this->entityManager->flush($profile);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $codeOptions
     * @return GameClientResponse|string
     */
    private function codeFileMod($codeOptions)
    {
        $profile = $this->user->getProfile();
        $type = $codeOptions->fileType;
        if ($type === 0) {
            return $this->gameClientResponse->addMessage($this->translate('You need to specify a type first'))->send();
        }
        $level = (int)$codeOptions->fileLevel;
        if ($level === 0) {
            return $this->gameClientResponse->addMessage($this->translate('You need to specify a level first'))->send();
        }
        /* check if the given type is valid */
        $fileMod = NULL;
        $fileMod = $this->fileModRepo->find($type);
        if (!$fileMod) {
            $message = sprintf(
                $this->translate('Unknown file mod: %s'),
                htmLawed($type,['safe'=>1,'elements'=>'strong'])
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now we check if the player has all the needed resources
        /** @var FileMod $fileMod */
        $neededResources = $fileMod->getFileParts();
        $missingResources = [];
        foreach ($neededResources as $neededResource) {
            /** @var FilePart $neededResource */
            $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level);
            if (empty($filePartInstances)) {
                $missingResources[] = sprintf(
                    $this->translate('You need [%s] with at least level %s to code the [%s]'),
                    $neededResource->getName(),
                    $level,
                    $fileMod->getName()
                );
            }
        }
        if (!empty($missingResources)) {
            foreach ($missingResources as $missingResource) {
                $this->gameClientResponse->addMessage($missingResource);
            }
            return $this->gameClientResponse->send();
        }
        // check if advanced coding is involved and check for skill rating requirements
        $skillList = $this->getSkillListForType($codeOptions);
        $message = $this->checkAdvancedCoding($profile, Skill::ID_CODING);
        if ($message) {
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* checks passed, we can now create the mod */
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
        $message = sprintf(
            $this->translate('You start coding the %s'),
            $fileMod->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->entityManager->flush();
        return $this->gameClientResponse->send();
    }


    private function codeProgram($codeOptions)
    {
        $profile = $this->user->getProfile();
        $type = $codeOptions->fileType;
        if ($type === 0) {
            return $this->gameClientResponse->addMessage($this->translate('You need to specify a type first'));
        }
        $level = (int)$codeOptions->fileLevel;
        if ($level === 0) {
            return $this->gameClientResponse->addMessage($this->translate('You need to specify a level first'));
        }
        /* check if the given type is valid and if they have enough snippets */
        $fileType = NULL;
        $fileType = $this->fileTypeRepo->find($type);
        if (!$fileType) {
            $message = sprintf(
                $this->translate('Unknown file type: %s'),
                htmLawed($type,['safe'=>1,'elements'=>'strong'])
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($level > $profile->getSnippets()) {
            $message = sprintf(
                $this->translate('You need %s snippets to code: %s'),
                $level,
                $fileType->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now we check if the player has all the needed resources
        /** @var FileType $fileType */
        // check if a recipe is needed
        if ($fileType->getNeedRecipe()) {
            $message = $this->checkForRecipe($profile, $fileType);
            if ($message) {
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        // if they have a recipe
        $neededResources = $fileType->getFileParts();
        $missingResources = [];
        foreach ($neededResources as $neededResource) {
            /** @var FilePart $neededResource */
            $filePartInstances = $this->filePartInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $neededResource, $level);
            if (empty($filePartInstances)) {
                $missingResources[] = sprintf(
                    $this->translate('You need [%s] with at least level %s to code the [%s]'),
                    $neededResource->getName(),
                    $level,
                    $fileType->getName()
                );
            }
        }
        if (!empty($missingResources)) {
            foreach ($missingResources as $missingResource) {
                $this->gameClientResponse->addMessage($missingResource);
            }
            return $this->gameClientResponse->send();
        }
        // check if the player can store the file in his total storage
        if (!$this->canStoreFileOfSize($profile, $fileType->getSize())) {
            $message = sprintf(
                $this->translate('You do not have enough storage space to code the %s - you need %s more storage units - build more storage nodes'),
                $fileType->getName(),
                $fileType->getSize()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if advanced coding is involved and check for skill rating requirements
        $skillList = $this->getSkillListForType($codeOptions);
        if (in_array('advanced-coding', $skillList)) {
            $message = $this->checkAdvancedCoding($profile, Skill::ID_CODING);
            if ($message) {
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        if (in_array('advanced-networking', $skillList)) {
            $message = $this->checkAdvancedCoding($profile, Skill::ID_NETWORKING);
            if ($message) {
                return $this->gameClientResponse->addMessage($message)->send();
            }
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
        if ($systemJobAmount >= $this->getTotalSystemValueByNodeType($currentSystem, self::VALUE_TYPE_CODINGNODELEVELS)) {
            $message = $this->translate('The system does not have enough coding rating to accept another coding job - please wait until another job has finished');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* checks passed, we can now create the file */
        $difficulty = $level;
        $skillRating = $this->getSkillRating($profile, Skill::ID_CODING);
        $skillModifier = $this->getSkillModifierForFileType($fileType, $profile);
        $modifier = floor(($skillRating + $skillModifier)/2);
        $modifier = (int)$modifier;
        $completionDate = new \DateTime();
        // calculate coding time - for admins this is always 1s
        $codingTime = ($this->hasRole(NULL, Role::ROLE_ID_ADMIN)) ? 1 : $difficulty*self::CODING_TIME_MULTIPLIER_PROGRAM;
        $completionDate->add(new \DateInterval('PT' . $codingTime . 'S'));
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
        $message = sprintf(
            $this->translate('You start coding the %s for %s snippets'),
            $fileType->getName(),
            $level
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $currentSnippets = $profile->getSnippets();
        $profile->setSnippets($currentSnippets - $level);
        $this->entityManager->flush();
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function exitCodeMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        return $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_EXITCODEMODE)->send();
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
     * @return bool|string
     */
    private function checkAdvancedCoding(Profile $profile, $skillId)
    {
        $skill = $this->entityManager->find('Netrunners\Entity\Skill', $skillId);
        /** @var Skill $skill */
        $skillRating = $this->getSkillRating($profile, $skill->getId());
        $message = false;
        if ($skillRating < self::MIN_ADV_SKILL_RATING) {
            $tempMessage = 'Your rating in [%s] is not high enough (need %s skill rating)';
            $message = sprintf(
                $this->translate($tempMessage),
                $skill->getName(),
                self::MIN_ADV_SKILL_RATING
            );
        }
        return $message;
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
                        'severity' => Notification::SEVERITY_DANGER,
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
                    'severity' => Notification::SEVERITY_SUCCESS,
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
                    'severity' => Notification::SEVERITY_WARNING,
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
     * @return bool|GameClientResponse
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
        $returnMessage = sprintf(
            '%-19s|%-32s|%-4s',
            $this->translate('ADDED'),
            $this->translate('FILETYPE'),
            $this->translate('RUNS')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($recipes as $recipe) {
            /** @var ProfileFileTypeRecipe $recipe */
            $returnMessage = sprintf(
                '%-19s|%-32s|%-4s',
                $recipe->getAdded()->format('Y/m/d H:i:s'),
                $recipe->getFileType()->getName(),
                $recipe->getRuns()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
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
