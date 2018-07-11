<?php

/**
 * File Service.
 * The service supplies methods that resolve logic around File objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class FileService extends BaseService
{

    const DEFAULT_DIFFICULTY_MOD = 10;

    /**
     * @var FileUtilityService
     */
    protected $fileUtilityService;

    /**
     * @var FileExecutionService
     */
    protected $fileExecutionService;

    /**
     * @var FileRepository
     */
    protected $fileRepo;


    /**
     * FileService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param FileUtilityService $fileUtilityService
     * @param FileExecutionService $fileExecutionService
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        FileUtilityService $fileUtilityService,
        FileExecutionService $fileExecutionService,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator, $entityGenerator);
        $this->fileUtilityService = $fileUtilityService;
        $this->fileExecutionService = $fileExecutionService;
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function enterMode($resourceId, $command, $contentArray = [])
    {
        return $this->fileUtilityService->enterMode($resourceId, $command, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function removeFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->removeFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function statFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->statFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function downloadFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->downloadFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function editFileDescription($resourceId, $contentArray)
    {
        return $this->fileUtilityService->editFileDescription($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $content
     * @param int $entityId
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveFileDescription($resourceId, $content, $entityId)
    {
        return $this->fileUtilityService->saveFileDescription($resourceId, $content, $entityId);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function unloadFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->unloadFile($resourceId, $contentArray);
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function touchFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->touchFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function modFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->modFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listPasskeysCommand($resourceId)
    {
        return $this->fileUtilityService->listPasskeysCommand($resourceId);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function removePasskeyCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->removePasskeyCommand($resourceId, $contentArray);
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function updateFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->updateFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function initArmorCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->initArmorCommand($resourceId, $contentArray);
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function changeFileName($resourceId, $contentArray)
    {
        return $this->fileUtilityService->changeFileName($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function useCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->useCommand($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function executeFile($resourceId, $contentArray)
    {
        return $this->fileExecutionService->executeFile($resourceId, $contentArray);
    }

    /**
     * @param File $file
     * @param System $system
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function executePortscanner(File $file, System $system)
    {
        return $this->fileExecutionService->executePortscanner($file, $system);
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param System $system
     * @param Node $node
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function executeJackhammer($resourceId, File $file, System $system, Node $node)
    {
        return $this->fileExecutionService->executeJackhammer($resourceId, $file, $system, $node);
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param Connection $connection
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function executeLockpick($resourceId, File $file, Connection $connection)
    {
        return $this->fileExecutionService->executeLockpick($resourceId, $file, $connection);
    }

    /**
     * @param File $file
     * @param File $miner
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function executeSiphon(File $file, File $miner)
    {
        return $this->fileExecutionService->executeSiphon($file, $miner);
    }

    /**
     * @param File $file
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function executeMedkit(File $file)
    {
        return $this->fileExecutionService->executeMedkit($file);
    }

    /**
     * @param File $file
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function executeProxifier(File $file)
    {
        return $this->fileExecutionService->executeProxifier($file);
    }

    /**
     * @param File $file
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function executeSysmapper(File $file)
    {
        return $this->fileExecutionService->executeSysmapper($file);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function harvestCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->harvestCommand($resourceId, $contentArray);
    }

    /**
     * Kill a running program.
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function killProcess($resourceId, $contentArray)
    {
        return $this->fileUtilityService->killProcess($resourceId, $contentArray);
    }

    /**
     * Shows all running processes of the current system.
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listProcesses($resourceId, $contentArray)
    {
        return $this->fileUtilityService->listProcesses($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showFileTypes($resourceId)
    {
        return $this->fileUtilityService->showFileTypes($resourceId);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function decompileFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->decompileFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showFileMods($resourceId)
    {
        return $this->fileUtilityService->showFileMods($resourceId);
    }

    /**
     * @param $resourceId
     * @return \Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showFileCategories($resourceId)
    {
        return $this->fileUtilityService->showFileCategories($resourceId);
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createPasskeyCommand($resourceId)
    {
        return $this->fileUtilityService->createPasskeyCommand($resourceId);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function compareCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        list($contentArray, $firstFileName) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$firstFileName) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the first file to compare'))->send();
        }
        $firstFile = $this->fileRepo->findByNodeOrProfileAndName($currentNode, $profile, $firstFileName);
        if (empty($firstFile)) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid first file to compare'))->send();
        }
        $firstFile = array_shift($firstFile);
        /** @var File $firstFile */
        $secondFileName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$secondFileName) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the second file to compare'))->send();
        }
        $secondFile = $this->fileRepo->findByNodeOrProfileAndName($currentNode, $profile, $secondFileName);
        if (empty($secondFile)) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid second file to compare'))->send();
        }
        $secondFile = array_shift($secondFile);
        /** @var File $secondFile */
        // now we can compare the two files
        $headerRow = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('PROPERTY'),
            $firstFile->getName(),
            $secondFile->getName()
        );
        $this->gameClientResponse->addMessage($headerRow, GameClientResponse::CLASS_SYSMSG);
        $returnMessages = [];
        $returnMessages[] = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('type'),
            $firstFile->getFileType()->getName(),
            $secondFile->getFileType()->getName()
        );
        $returnMessages[] = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('coder'),
            ($firstFile->getCoder()) ? $firstFile->getCoder()->getUser()->getUsername() : $this->translate('---'),
            ($secondFile->getCoder()) ? $secondFile->getCoder()->getUser()->getUsername() : $this->translate('---')
        );
        $returnMessages[] = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('size'),
            $firstFile->getSize(),
            $secondFile->getSize()
        );
        $returnMessages[] = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('level'),
            $firstFile->getLevel(),
            $secondFile->getLevel()
        );
        $returnMessages[] = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('integrity'),
            sprintf('%s/%s', $firstFile->getIntegrity(), $firstFile->getMaxIntegrity()),
            sprintf('%s/%s', $secondFile->getIntegrity(), $secondFile->getMaxIntegrity())
        );
        $returnMessages[] = sprintf(
            '%-12s|%-32s|%-32s',
            $this->translate('slots'),
            sprintf('%s/%s', $this->fileUtilityService->getAmountOfFittedSlots($firstFile), $firstFile->getSlots()),
            sprintf('%s/%s', $this->fileUtilityService->getAmountOfFittedSlots($secondFile), $secondFile->getSlots())
        );
        // TODO add mods to output
        return $this->gameClientResponse->addMessages($returnMessages)->send();
    }

}
