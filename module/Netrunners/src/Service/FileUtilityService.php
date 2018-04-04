<?php

/**
 * FileUtility Service.
 * The service supplies methods that resolve utility logic around File objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\FileCategory;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\FileType;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\NpcInstance;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FileModRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\MissionRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class FileUtilityService extends BaseService
{

    const PASSKEY_COST = 10;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var FileModInstanceRepository
     */
    protected $fileModInstanceRepo;

    /**
     * @var FileModRepository
     */
    protected $fileModRepo;

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;


    /**
     * FileService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->fileModRepo = $this->entityManager->getRepository('Netrunners\Entity\FileMod');
        $this->fileModInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FileModInstance');
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function changeFileName($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        $file = array_shift($targetFiles);
        if (!$file) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        // now get the new name
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$newName) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a new name (alpha-numeric only, 32-chars-max)'))->send();
        }
        // check if they can change the type
        if ($profile != $file->getProfile()) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // string check
        $checkResult = $this->stringChecker($newName);
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        /* all checks passed, we can rename the file now */
        $newName = str_replace(' ', '_', $newName);
        $file->setName($newName);
        $this->entityManager->flush($file);
        $message = sprintf('File name changed to %s', $newName);
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has edited [%s]'),
            $this->user->getUsername(),
            $newName
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function updateFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, false, true, true);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        $file = array_shift($targetFiles);
        if (!$file) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        /** @var File $file */
        if ($file->getIntegrity() >= $file->getMaxIntegrity()) {
            return $this->gameClientResponse->addMessage($this->translate('File is already at max integrity'))->send();
        }
        /* all checks passed, we can update the file now */
        $currentIntegrity = $file->getIntegrity();
        $maxIntegrity = $file->getMaxIntegrity();
        $neededIntegrity = $maxIntegrity - $currentIntegrity;
        if ($neededIntegrity > $profile->getSnippets()) $neededIntegrity = $profile->getSnippets();
        $file->setIntegrity($file->getIntegrity() + $neededIntegrity);
        $this->entityManager->flush($file);
        $profile->setSnippets($profile->getSnippets() - $neededIntegrity);
        $this->entityManager->flush($profile);
        $message = sprintf(
            '[%s] updated with %s snippets',
            $file->getName(),
            $neededIntegrity
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has updated [%s]</pre>'),
            $this->user->getUsername(),
            $file->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function downloadFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // init response
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // try to get target file via repo method
        $targetFile = $this->fileRepo->findOneBy([
            'name' => $parameter,
            'node' => $profile->getCurrentNode()
        ]);
        if (!$targetFile) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        /** @var File $targetFile */
        // check for mission
        if ($targetFile->getFileType()->getId() == FileType::ID_TEXT) {
            $missionFileCheck = $this->executeMissionFile($targetFile);
            if ($missionFileCheck instanceof GameClientResponse) {
                return $missionFileCheck->send();
            }
        }
        // can only download files that do not belong to themselves in owned systems
        $targetFileSystem = $targetFile->getSystem();
        $targetFileNode = $targetFile->getNode();
        if (
            $targetFile->getProfile() !== $profile &&
            $targetFileSystem->getProfile() !== $profile &&
            ($targetFileNode->getProfile() && $targetFileNode->getProfile() !== $profile) &&
            ($targetFileSystem->getProfile() != NULL && $targetFileSystem->getFaction() != NULL && $targetFileSystem->getGroup() != NULL)
        ){
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // check if the file is running - can't download then
        if ($targetFile->getRunning()) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to download running file'))->send();
        }
        // check if the file belongs to a profile or npc - can't download then
        if ($targetFile->getProfile() != NULL) {
            return $this->gameClientResponse->addMessage($this->translate('This file has already been downloaded by someone'))->send();
        }
        if ($targetFile->getNpc() != NULL) {
            return $this->gameClientResponse->addMessage($this->translate('This file has already been downloaded by an entity'))->send();
        }
        // check if there is enough storage to store this
        if (!$this->canStoreFile($profile, $targetFile)) {
            $message = sprintf(
                $this->translate('You do not have enough storage to download %s - build or upgrade storage nodes'),
                $targetFile->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all checks passed, download file */
        $targetFile->setProfile($profile);
        $targetFile->setNode(NULL);
        $targetFile->setSystem(NULL);
        $this->entityManager->flush($targetFile);
        $message = sprintf(
            $this->translate('You download %s to your storage'),
            $targetFile->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] downloaded [%s]'),
            $this->user->getUsername(),
            $targetFile->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function enterMode($resourceId, $command, $contentArray = [])
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $this->getWebsocketServer()->setConfirm($resourceId, $command, $contentArray);
        switch ($command) {
            default:
                break;
            case 'rm':
                $checkResult = $this->removeFileChecks($contentArray);
                if (!$checkResult instanceof File) {
                    if ($checkResult instanceof GameClientResponse) {
                        return $checkResult->send();
                    }
                    return $this->gameClientResponse->addMessage($checkResult)->send();
                }
                $message = sprintf(
                    $this->translate('Are you sure that you want to delete [%s] - Please confirm this action:'),
                    $checkResult->getName()
                );
                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERCONFIRMMODE);
                break;
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function harvestCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $minerString = $this->getNextParameter($contentArray, false);
        if (!$minerString) {
            $message = $this->translate('Please specify the miner that you want to harvest');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $minerId = NULL;
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $minerString);
        if (count($targetFiles) < 1) {
            $message = $this->translate('File not found');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $miner = array_shift($targetFiles);
        /** @var File $miner */
        if ($miner->getProfile() != $profile) {
            $message = $this->translate('Permission denied');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $minerData = json_decode($miner->getData());
        if (!isset($minerData->value)) {
            $message = $this->translate('No resources to harvest in that miner');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($minerData->value < 1) {
            $message = $this->translate('No resources to harvest in that miner');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $availableResources = $minerData->value;
        $minerData->value = 0;
        switch ($miner->getFileType()->getId()) {
            default:
                $message = sprintf(
                    $this->translate('Unable to harvest at this moment'),
                    $availableResources,
                    $miner->getName()
                );
                return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_DANGER)->send();
            case FileType::ID_DATAMINER:
                $profile->setSnippets($profile->getSnippets() + $availableResources);
                $message = sprintf(
                    $this->translate('You harvest [%s] snippets from [%s]'),
                    $availableResources,
                    $miner->getName()
                );
                break;
            case FileType::ID_COINMINER:
                $profile->setCredits($profile->getCredits() + $availableResources);
                $message = sprintf(
                    $this->translate('You harvest [%s] credits from [%s]'),
                    $availableResources,
                    $miner->getName()
                );
                break;
        }
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $miner->setData(json_encode($minerData));
        $this->entityManager->flush($profile);
        $this->entityManager->flush($miner);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is harvesting [%s]'),
            $this->user->getUsername(),
            $miner->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function initArmorCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true, false, false, true);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        $file = array_shift($targetFiles);
        if (!$file) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        if ($file->getFileType()->getId() != FileType::ID_CODEARMOR) {
            return $this->gameClientResponse->addMessage($this->translate('You can only initialize codearmor files'))->send();
        }
        // now get the subtype
        $subtype = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$subtype) {
            $message = $this->translate('Please choose from the following options:');
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
            $message = wordwrap(implode(',', FileType::$armorSubtypeLookup), 120);
            return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE)->send();
        }
        // check if they can change the type
        if ($profile != $file->getProfile()) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // all seems fine - init
        $fileData = json_decode($file->getData());
        if ($fileData && $fileData->subtype) {
            return $this->gameClientResponse->addMessage($this->translate('This codearmor has already been initialized'))->send();
        }
        switch ($subtype) {
            default:
                $realType = false;
                break;
            case FileType::SUBTYPE_ARMOR_HEAD_STRING:
                $realType = FileType::SUBTYPE_ARMOR_HEAD;
                break;
            case FileType::SUBTYPE_ARMOR_SHOULDERS_STRING:
                $realType = FileType::SUBTYPE_ARMOR_SHOULDERS;
                break;
            case FileType::SUBTYPE_ARMOR_UPPER_ARM_STRING:
                $realType = FileType::SUBTYPE_ARMOR_UPPER_ARM;
                break;
            case FileType::SUBTYPE_ARMOR_LOWER_ARM_STRING:
                $realType = FileType::SUBTYPE_ARMOR_LOWER_ARM;
                break;
            case FileType::SUBTYPE_ARMOR_HANDS_STRING:
                $realType = FileType::SUBTYPE_ARMOR_HANDS;
                break;
            case FileType::SUBTYPE_ARMOR_TORSO_STRING:
                $realType = FileType::SUBTYPE_ARMOR_TORSO;
                break;
            case FileType::SUBTYPE_ARMOR_LEGS_STRING:
                $realType = FileType::SUBTYPE_ARMOR_LEGS;
                break;
            case FileType::SUBTYPE_ARMOR_SHOES_STRING:
                $realType = FileType::SUBTYPE_ARMOR_SHOES;
                break;
        }
        if (!$realType) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid subtype'))->send();
        }
        else {
            $file->setData(json_encode(['subtype'=>$realType]));
            $this->entityManager->flush($file);
            $message = sprintf(
                $this->translate('You have initialized [%s] to subtype [%s]'),
                $file->getName(),
                FileType::$armorSubtypeLookup[$realType]
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            // inform other players in node
            $message = sprintf(
                $this->translate('[%s] has initialized [%s]'),
                $this->user->getUsername(),
                $file->getName()
            );
            $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function killProcess($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        // init response
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if (!$parameter) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the process id to kill (ps for list)'))->send();
        }
        /** @var File $runningFile */
        $runningFile = $this->entityManager->find('Netrunners\Entity\File', $parameter);
        if (!$runningFile) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid process id'))->send();
        }
        if ($runningFile->getProfile() != $profile) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid process id'))->send();
        }
        if (!$runningFile->getRunning()) {
            return $this->gameClientResponse->addMessage($this->translate('No process with that id'))->send();
        }
        if ($runningFile->getSystem() && $runningFile->getSystem() != $profile->getCurrentNode()->getSystem())  {
            return $this->gameClientResponse->addMessage($this->translate('That process needs to be killed in the system that it is running in'))->send();
        }
        if ($runningFile->getNode() && $runningFile->getNode() != $profile->getCurrentNode()) {
            return $this->gameClientResponse->addMessage($this->translate('That process needs to be killed in the node that it is running in'))->send();
        }
        // check if this is equipment
        if ($runningFile->getFileType()->getId() == FileType::ID_CODEBLADE) {
            $profile->setBlade(null);
            $this->entityManager->flush($profile);
        }
        if ($runningFile->getFileType()->getId() == FileType::ID_CODEBLASTER) {
            $profile->setBlaster(null);
            $this->entityManager->flush($profile);
        }
        if ($runningFile->getFileType()->getId() == FileType::ID_CODESHIELD) {
            $profile->setShield(null);
            $this->entityManager->flush($profile);
        }
        if ($runningFile->getFileType()->getId() == FileType::ID_CODEARMOR) {
            $fileData = json_decode($runningFile->getData());
            switch ($fileData->subtype) {
                default:
                    break;
                case FileType::SUBTYPE_ARMOR_HEAD:
                    $profile->setHeadArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_SHOULDERS:
                    $profile->setShoulderArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_UPPER_ARM:
                    $profile->setUpperArmArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_LOWER_ARM:
                    $profile->setLowerArmArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_HANDS:
                    $profile->setHandArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_TORSO:
                    $profile->setTorsoArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_LEGS:
                    $profile->setLegArmor(null);
                    break;
                case FileType::SUBTYPE_ARMOR_SHOES:
                    $profile->setShoesArmor(null);
                    break;
            }
            $this->entityManager->flush($profile);
        }
        $runningFile->setRunning(false);
        $runningFile->setSystem(NULL);
        $runningFile->setNode(NULL);
        $this->entityManager->flush($runningFile);
        $message = sprintf(
            $this->translate('Process with id [%s] has been killed'),
            $runningFile->getId()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] killed a process'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listProcesses($resourceId, $contentArray)
    {
        // TODO add more info to output
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $showAll = $this->getNextParameter($contentArray, false);
        if ($showAll) {
            $runningFiles = $this->fileRepo->findBy([
                'profile' => $profile,
                'running' => true
            ]);
        }
        else {
            $runningFiles = $this->fileRepo->findBy([
                'system' => $profile->getCurrentNode()->getSystem(),
                'running' => true
            ]);
        }
        $returnMessage = sprintf(
            '%-12s|%-20s|%s',
            $this->translate('PROCESS-ID'),
            $this->translate('FILE-TYPE'),
            $this->translate('FILE-NAME')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($runningFiles as $runningFile) {
            /** @var File $runningFile */
            $returnMessage = sprintf(
                '%-12s|%-20s|%s',
                $runningFile->getId(),
                $runningFile->getFileType()->getName(),
                $runningFile->getName()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $contentArray
     * @return mixed|File|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function removeFileChecks($contentArray)
    {
        $response = false;
        $profile = $this->user->getProfile();
        $parameter = $this->getNextParameter($contentArray, false);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $parameter);
        if (count($targetFiles) < 1) {
            $response = $this->translate('File not found');
        }
        $file = array_shift($targetFiles);
        /** @var File $file */
        // check if this file is a mission file
        if (!$response && $file->getFileType()->getId() == FileType::ID_TEXT) {
             $response = $this->executeMissionFile($file);
        }
        // check if the file belongs to the profile
        if (!$response && $file && $file->getProfile() != $profile) {
            $response = $this->translate('Permission denied');
        }
        if (!$response && $file->getRunning()) {
            $response = $this->translate('Command failed - program is still running');
        }
        if (!$response && $file->getSystem()) {
            $response = $this->translate('Command failed - please unload the file first');
        }
        return ($response) ? $response : $file;
    }

    /**
     * Checks the given file if it can be modified.
     * If you pass a contentArray (user-input), it will get the file from the user-input.
     * @param array|null $contentArray
     * @param File|null $givenFile
     * @return bool|string|File|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function editFileChecks($contentArray = NULL, File $givenFile = NULL)
    {
        $response = false;
        $profile = $this->user->getProfile();
        if ($givenFile) {
            $file = $givenFile;
        }
        else {
            $file = NULL;
            $parameter = $this->getNextParameter($contentArray, false);
            // try to get target file via repo method
            $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $parameter);
            if (count($targetFiles) < 1) {
                $response = $this->translate('File not found');
            }
        }
        if (!$response) {
            if (!$givenFile) $file = array_shift($targetFiles);
            /** @var File $file */
            // check if the file belongs to the profile
            if (!$response && $file && $file->getProfile() != $profile) {
                $response = $this->translate('Permission denied');
            }
            if (!$response && $file->getSystem()) {
                $response = $this->translate('Command failed - please download the file first');
            }
            // check if this file is a text file
            if (!$response && $file && $file->getFileType()->getId() != FileType::ID_TEXT) {
                $response = $this->translate('Unable to edit this file type');
            }
            $missionRepo = $this->entityManager->getRepository('Netrunners\Entity\Mission');
            /** @var MissionRepository $missionRepo */
            // check if this file is a text file that is used in a mission
            if (!$response && $file && $missionRepo->findByTargetFile($file)) {
                $response = $this->translate('Unable to edit mission files');
            }
        }
        return ($response) ? $response : $file;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function modFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('No such file'))->send();
        }
        $file = array_shift($targetFiles);
        if (!$file) {
            return $this->gameClientResponse->addMessage($this->translate('No such file'))->send();
        }
        /** @var File $file */
        // now get the filemodinstance
        $fileModName = $this->getNextParameter($contentArray, false, false, true, true);
        // get a list of possible file mods
        $fileType = $file->getFileType();
        $possibleFileMods = $this->fileModRepo->listForTypeCommand($fileType);
        if (!$fileModName) {
            $message = $this->translate('Please choose from the following options:');
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
            $fileModListString = '';
            foreach ($possibleFileMods as $possibleFileMod) {
                /** @var FileMod $possibleFileMod */
                $fileModListString .= $possibleFileMod->getName() . ' ';
            }
            $message = wordwrap($fileModListString, 120);
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            return $this->gameClientResponse->send();
        }
        // check if they can change the type
        if ($profile != $file->getProfile()) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // check if the file can accept more mods
        if ($this->fileModInstanceRepo->countByFile($file) >= $file->getSlots()) {
            $message = $this->translate('This file can no longer be modded - max mods reached');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all seems fine
        $fileMod = $this->fileModRepo->findLikeName($fileModName);
        if (!$fileMod) {
            $message = $this->translate('Unable to find given file mod type');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if filetype and filemod are compatible
        $compatible = $this->entityManager->getRepository('Netrunners\Entity\FileTypeMod')->findOneBy([
            'fileType' => $fileType,
            'fileMod' => $fileMod
        ]);
        if (!$compatible) {
            $message = $this->translate('Invalid file type and mod combination, valid options for this file type:');
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
            $fileModListString = '';
            foreach ($possibleFileMods as $possibleFileMod) {
                /** @var FileMod $possibleFileMod */
                $fileModListString .= $possibleFileMod->getName() . ' ';
            }
            $message = wordwrap($fileModListString, 120);
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
            return $this->gameClientResponse->send();
        }
        // ok, now we know the file and the filemod, try to find a filemodinstance that fits the variables
        $fileModInstances = $this->fileModInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $fileMod, $file->getLevel());
        if (count($fileModInstances) < 1) {
            $message = $this->translate('You do not own a fitting file-mod of that level');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $fileModInstance = array_shift($fileModInstances);
        /** @var FileModInstance $fileModInstance */
        $flush = false;
        $successMessage = false;
        switch ($fileMod->getId()) {
            default:
                break;
            case FileMod::ID_BACKSLASH:
                $fileModInstance->setFile($file);
                $fileModInstance->setProfile(NULL);
                $flush = true;
                $successMessage = sprintf(
                    $this->translate('[%s] has been modded with [%s]'),
                    $file->getName(),
                    $fileMod->getName()
                );
                break;
            case FileMod::ID_INTEGRITY_BOOSTER:
                $newMaxIntegrity = $file->getMaxIntegrity() + $fileModInstance->getLevel();
                if ($newMaxIntegrity > 100) $newMaxIntegrity = 100;
                $file->setMaxIntegrity($newMaxIntegrity);
                $fileModInstance->setFile($file);
                $fileModInstance->setProfile(NULL);
                $flush = true;
                $successMessage = sprintf(
                    $this->translate('[%s] has been modded with [%s] - new max-integrity: %s'),
                    $file->getName(),
                    $fileMod->getName(),
                    $newMaxIntegrity
                );
                break;
            case FileMod::ID_TITANKILLER:
                $fileModInstance->setFile($file);
                $fileModInstance->setProfile(NULL);
                $flush = true;
                $successMessage = sprintf(
                    $this->translate('[%s] has been modded with [%s]'),
                    $file->getName(),
                    $fileMod->getName()
                );
                break;
        }
        if ($flush) {
            $this->entityManager->flush($file);
            $this->entityManager->flush($fileModInstance);
            $this->gameClientResponse->addMessage($successMessage, GameClientResponse::CLASS_SUCCESS);
        }
        else {
            $this->gameClientResponse->addMessage($this->translate('This mod has no effect, yet'));
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function removeFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $checkResult = false;
        if (!$this->response) {
            $checkResult = $this->removeFileChecks($contentArray);
            if (!$checkResult instanceof File) {
                if ($checkResult instanceof GameClientResponse) {
                    return $checkResult->send();
                }
                return $this->gameClientResponse->addMessage($checkResult)->send();
            }
        }
        // start removing the file by removing all of its filemodinstances
        $fmInstances = $this->fileModInstanceRepo->findBy([
            'file' => $checkResult
        ]);
        foreach ($fmInstances as $fmInstance) {
            $this->entityManager->remove($fmInstance);
        }
        // now we need to check if it's a spawner and remove the ice, if possible
        $spawnerCategory = $this->entityManager->find('Netrunners\Entity\FileCategory', FileCategory::ID_SPAWNER);
        /** @var ArrayCollection $fileCategories */
        $fileCategories = $checkResult->getFileType()->getFileCategories();
        if ($fileCategories->contains($spawnerCategory)) {
            /** @var NpcInstance $npcInstance */
            foreach ($this->npcInstanceRepo->findBySpawner($checkResult->getId()) as $npcInstance) {
                $npcInstance->setSpawner(null);
            }
        }
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You removed [%s]'),
            $checkResult->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->entityManager->remove($checkResult);
        $this->entityManager->flush($checkResult);
        return $this->gameClientResponse;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function editFileDescription($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $checkResult = $this->editFileChecks($contentArray);
        if (!$checkResult instanceof File) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        /* checks passed, we can now edit the file */
        /** @var File $checkResult */
        $view = new ViewModel();
        $view->setTemplate('netrunners/file/edit-text.phtml');
        $description = $checkResult->getContent();
        $processedDescription = '';
        if ($description) {
            $processedDescription = htmLawed($description, array('safe'=>1, 'elements'=>'strong, em, strike, u'));
        }
        $view->setVariable('description', $processedDescription);
        $view->setVariable('entityId', $checkResult->getId());
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is editing a file'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param string $content
     * @param $entityId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveFileDescription(
        $resourceId,
        $content = '===invalid content===',
        $entityId
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $file = $this->fileRepo->find($entityId);
        if (!$file) {
            $message = $this->translate('invalid file id');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $checkResult = $this->editFileChecks(NULL, $file);
        if (!$checkResult instanceof File) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        /** @var File $file */
        $content = htmLawed($content, ['safe'=>1,'elements'=>'strong,i,ul,ol,li,p,a,br']);
        $file->setContent($content);
        $this->entityManager->flush($file);
        $message = $this->translate('File content saved');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function decompileFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $checkResult = $this->removeFileChecks($contentArray);
        if (!$checkResult instanceof File) {
            if ($checkResult instanceof GameClientResponse) {
                return $checkResult->send();
            }
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        if (!$checkResult->getFileType()->getCodable()) {
            $message = $this->translate('Unable to decompile this file type');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $returnedSnippets = $checkResult->getLevel();
        // start removing the file by removing all of its filemodinstances
        $fmInstances = $this->fileModInstanceRepo->findBy([
            'file' => $checkResult
        ]);
        foreach ($fmInstances as $fmInstance) {
            /** @var FileModInstance $fmInstance */
            $returnedSnippets += $fmInstance->getLevel();
            $this->entityManager->remove($fmInstance);
        }
        $this->entityManager->remove($checkResult);
        $profile->setSnippets($profile->getSnippets()+$returnedSnippets);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You decompiled [%s] and received %s snippets'),
            $checkResult->getName(),
            $returnedSnippets
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createPasskeyCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $nodeType = $currentNode->getNodeType();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // sanity checks
        if ($nodeType->getId() != NodeType::ID_PUBLICIO && $nodeType->getId() != NodeType::ID_IO) {
            $message = $this->translate('Passkeys can only be created in I/O nodes');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($currentNode->getProfile() !== $profile && $currentSystem->getProfile() !== $profile) {
            $message = $this->translate('Passkeys can only be created in nodes that you own');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($profile->getSnippets() < self::PASSKEY_COST) {
            $message = sprintf(
                $this->translate('You need %s snippets to create a passkey'),
                self::PASSKEY_COST
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // logic start
        $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_PASSKEY);
        $data = [
            'systemid' => $currentSystem->getId(),
            'nodeid' => $currentNode->getId()
        ];
        $shortSystemName = substr($currentSystem->getName(), 0, 8);
        $shortNodeName = substr($currentNode->getName(), 0, 8);
        $passkeyDesc = sprintf(
            'This passkey can be used to access the node [%s] in system [%s]',
            $currentNode->getName(),
            $currentSystem->getName()
        );
        $profile->setSnippets($profile->getSnippets()-self::PASSKEY_COST);
        $this->entityManager->flush($profile);
        $newName = sprintf(
            '%s_%s_%s',
            $shortSystemName,
            $shortNodeName,
            'passkey'
        );
        $this->createFile(
            $fileType,
            true,
            $newName,
            1,
            100,
            false,
            100,
            $profile,
            $passkeyDesc,
            json_encode($data),
            null,
            null,
            null,
            $profile,
            null,
            0
        );
        $this->gameClientResponse->addMessage($this->translate('Passkey created'), GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listPasskeysCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // logic start
        $message = sprintf(
            '%-11s|%-32s|%-32s|%s',
            $this->translate('PASSKEY-ID'),
            $this->translate('TARGET-SYSTEM'),
            $this->translate('TARGET-NODE'),
            $this->translate('CURRENT-OWNER')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        foreach ($this->fileRepo->findByProfileAndType($profile, FileType::ID_PASSKEY) as $passkey) {
            /** @var File $passkey */
            $passkeyData = json_decode($passkey->getData());
            if (!$passkeyData) continue;
            $targetNodeId = $passkeyData->nodeid;
            if (!$targetNodeId) continue;
            $targetNode = $this->entityManager->find('Netrunners\Entity\Node', $targetNodeId);
            if (!$targetNode) continue;
            $currentOwnerString = ($passkey->getProfile()) ?
                sprintf('<span class="text-attention">%s</span>', $passkey->getProfile()->getUser()->getUsername()) :
                sprintf('<span class="text-danger">%s</span>', $this->translate('UNKNOWN-LOCATION'));
            $message = sprintf(
                '%-11s|%-32s|%-32s|%s',
                $passkey->getId(),
                $targetNode->getSystem()->getName(),
                $targetNode->getName(),
                $currentOwnerString
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function removePasskeyCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $passkey = NULL;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $passkeyId = $this->getNextParameter($contentArray, false, true);
        if (!$passkeyId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the passkey id'))->send();
        }
        $passkey = $this->fileRepo->find($passkeyId);
        if (!$passkey) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid passkey id'))->send();
        }
        /** @var File $passkey */
        if ($passkey->getCoder() !== $profile) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid passkey id'))->send();
        }
        // logic start
        $owningProfile = ($passkey->getProfile()) ? $passkey->getProfile() : NULL;
        $this->entityManager->remove($passkey);
        $this->entityManager->flush($passkey);
        // inform the current owner of deletion
        if ($owningProfile && $owningProfile !== $profile) {
            if ($owningProfile->getCurrentResourceId()) {
                $message = sprintf(
                    $this->translate('Passkey [%s] has been deleted by [%s]'),
                    $passkey->getName(),
                    $passkey->getCoder()->getUser()->getUsername()
                );
                $this->messageProfileNew($owningProfile, $message, GameClientResponse::CLASS_ATTENTION);
            }
            else {
                $message = sprintf(
                    $this->translate('Passkey [%s] has been deleted by [%s]'),
                    $passkey->getName(),
                    $passkey->getCoder()->getUser()->getUsername()
                );
                $this->storeNotification($owningProfile, $message, Notification::SEVERITY_INFO);
            }
        }
        // inform actor
        $this->gameClientResponse->addMessage($this->translate('Passkey deleted'), GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showFileCategories($resourceId)
    {
        $this->initService($resourceId);
        $fileMods = $this->entityManager->getRepository('Netrunners\Entity\FileCategory')->findBy(
            [],
            ['name' => 'ASC']
        );
        $returnMessage = sprintf(
            '%-32s|%s',
            $this->translate('FILECAT-NAME'),
            $this->translate('DESCRIPTION')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($fileMods as $fileMod) {
            /** @var FileCategory $fileMod */
            if (!$fileMod->getResearchable()) continue;
            $returnMessage = sprintf(
                '%-32s|%s',
                $fileMod->getName(),
                $fileMod->getDescription()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showFileMods($resourceId)
    {
        $this->initService($resourceId);
        $fileMods = $this->entityManager->getRepository('Netrunners\Entity\FileMod')->findBy(
            [],
            ['name' => 'ASC']
        );
        $returnMessage = sprintf(
            '%-32s|%s',
            $this->translate('FILEMOD-NAME'),
            $this->translate('DESCRIPTION')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($fileMods as $fileMod) {
            /** @var FileMod $fileMod */
            $returnMessage = sprintf(
                '%-32s|%s',
                $fileMod->getName(),
                $fileMod->getDescription()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showFileTypes($resourceId)
    {
        $this->initService($resourceId);
        $fileTypes = $this->entityManager->getRepository('Netrunners\Entity\FileType')->findBy(
            ['codable' => true],
            ['name' => 'ASC']
        );
        $returnMessage = sprintf(
            '%-32s|%-20s|%-4s|%-2s|%s',
            $this->translate('FILETYPE-NAME'),
            $this->translate('FILETYPE-CATEGORIES'),
            $this->translate('SIZE'),
            $this->translate('RR'),
            $this->translate('DESCRIPTION')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
            /** @var FileType $fileType */
        foreach ($fileTypes as $fileType) {
            if (!$fileType->getCodable()) continue;
            $categories = '';
                /** @var FileCategory $fileCategory */
            foreach ($fileType->getFileCategories() as $fileCategory) {
                $categories .= $fileCategory->getName() . ' ';
            }
            $returnMessage = sprintf(
                '%-32s|%-20s|%-4s|%-2s|%s',
                $fileType->getName(),
                $categories,
                $fileType->getSize(),
                ($fileType->getNeedRecipe()) ? $this->translate('Y') : $this->translate('N'),
                $fileType->getDescription()
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function statFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $parameter = $this->getNextParameter($contentArray, false);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            return $this->gameClientResponse->addMessage($this->translate('File not found'))->send();
        }
        /* start logic */
        /** @var File $targetFile */
        $targetFile = array_shift($targetFiles);
        $this->generateFileInfo($targetFile);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function touchFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        // init response
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findFileInNodeByName(
            $profile->getCurrentNode(),
            $parameter
        );
        if (count($targetFiles) >= 1) {
            $message = $this->translate('A file with that name already exists in this node');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($profile->getSnippets() < 1) {
            $message = $this->translate('You need 1 snippet to create an empty text file');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check string val and length
        $checkResult = $this->stringChecker($parameter);
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        $parameter = str_replace(' ', '_', $parameter);
        /* start logic */
        $currentSnippets = $profile->getSnippets();
        $profile->setSnippets($currentSnippets - 1);
        $newFileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_TEXT);
        $this->createFile(
            $newFileType,
            false,
            $parameter . '.txt',
            1,
            100,
            false,
            100,
            $profile,
            '',
            null,
            null,
            null,
            null,
            $profile,
            null,
            0
        );
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('%s has been created'),
            $parameter
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has created a text file'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function unloadFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        // init response
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // try to get target file via repo method
        $targetFile = $this->fileRepo->findOneBy([
            'name' => $parameter,
            'profile' => $profile
        ]);
        if (!$targetFile) {
            return $this->gameClientResponse->addMessage($this->translate('No such file'))->send();
        }
        /** @var File $targetFile */
        // check if the file belongs to the profile
        if ($targetFile->getProfile() != $profile) {
            return $this->gameClientResponse->addMessage($this->translate('Permission denied'))->send();
        }
        // check if attempt to unload running file
        if ($targetFile->getRunning()) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to upload running file'))->send();
        }
        /* all checks passed, unload file */
        if ($targetFile->getFileType()->getId() == FileType::ID_TEXT) {
            $missionFileCheck = $this->executeMissionFile($targetFile);
            if ($missionFileCheck instanceof GameClientResponse) {
                return $missionFileCheck->send();
            }
        }
        $targetFile->setProfile(NULL);
        $targetFile->setNode($profile->getCurrentNode());
        $targetFile->setSystem($profile->getCurrentNode()->getSystem());
        $this->entityManager->flush($targetFile);
        $message = sprintf(
            $this->translate('You upload %s to the node'),
            $targetFile->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->updateMap($resourceId);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has uploaded [%s] to the node'),
            $this->user->getUsername(),
            $targetFile->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId(), true);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function useCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        $file = NULL;
        if (count($targetFiles) >= 1) {
            $file = array_shift($targetFiles);
        }
        if (!$file) {
            return $this->gameClientResponse->addMessage($this->translate('No such file'))->send();
        }
        if (!$file->getRunning()) {
            return $this->gameClientResponse->addMessage($this->translate('Files must be running to use them'))->send();
        }
        $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
        switch ($file->getFileType()->getId()) {
            default:
                return $this->gameClientResponse->addMessage($this->translate('Unable to use this type of file'))->send();
            case FileType::ID_WILDERSPACE_HUB_PORTAL:
                $hubNode = $this->entityManager->find('Netrunners\Entity\Node', $serverSetting->getWildernessHubNodeId());
                $this->movePlayerToTargetNodeNew($resourceId, $profile, NULL, $profile->getCurrentNode(), $hubNode);
                $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
                $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, explode(',',$hubNode->getSystem()->getGeocoords()));
                $this->gameClientResponse->send();
                $this->updateMap($resourceId);
                break;
        }
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has used [%s]'),
            $this->user->getUsername(),
            $file->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        $this->gameClientResponse
            ->reset()
            ->setSilent(true)
            ->addMessage($this->translate('You have connected to the target node'), GameClientResponse::CLASS_SUCCESS)
            ->send();
        // redirect to show-node-info method
        return $this->showNodeInfoNew($resourceId, NULL, true);
    }

    /**
     * @param File $file
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getAmountOfFittedSlots(File $file)
    {
        return $this->fileModInstanceRepo->countByFile($file);
    }

}
