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
use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\System;
use Netrunners\Repository\FileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class FileService extends BaseService
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
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        FileUtilityService $fileUtilityService,
        FileExecutionService $fileExecutionService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileUtilityService = $fileUtilityService;
        $this->fileExecutionService = $fileExecutionService;
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return array|bool|false
     */
    public function enterMode($resourceId, $command, $contentArray = [])
    {
        return $this->fileUtilityService->enterMode($resourceId, $command, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function removeFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->removeFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function statFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->statFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function downloadFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->downloadFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function unloadFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->unloadFile($resourceId, $contentArray);
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function touchFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->touchFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function modFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->modFile($resourceId, $contentArray);
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function updateFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->updateFile($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function initArmorCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->initArmorCommand($resourceId, $contentArray);
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function changeFileName($resourceId, $contentArray)
    {
        return $this->fileUtilityService->changeFileName($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function useCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->useCommand($resourceId, $contentArray);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function executeFile($resourceId, $contentArray)
    {
        return $this->fileExecutionService->executeFile($resourceId, $contentArray);
    }

    /**
     * @param File $file
     * @param System $system
     * @return array|bool
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
     * @return array|bool
     */
    public function executeJackhammer($resourceId, File $file, System $system, Node $node)
    {
        return $this->fileExecutionService->executeJackhammer($resourceId, $file, $system, $node);
    }

    /**
     * @param File $file
     * @param File $miner
     * @return bool|string
     */
    public function executeSiphon(File $file, File $miner)
    {
        return $this->fileExecutionService->executeSiphon($file, $miner);
    }

    /**
     * @param File $file
     * @return array|bool
     */
    public function executeMedkit(File $file)
    {
        return $this->fileExecutionService->executeMedkit($file);
    }

    /**
     * @param File $file
     * @return array|bool
     */
    public function executeProxifier(File $file)
    {
        return $this->fileExecutionService->executeProxifier($file);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
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
     */
    public function listProcesses($resourceId, $contentArray)
    {
        return $this->fileUtilityService->listProcesses($resourceId, $contentArray);
    }

    /**
     * @return array
     */
    public function showFileTypes()
    {
        return $this->fileUtilityService->showFileTypes();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function decompileFile($resourceId, $contentArray)
    {
        return $this->fileUtilityService->decompileFile($resourceId, $contentArray);
    }

    /**
     * @return array
     */
    public function showFileMods()
    {
        return $this->fileUtilityService->showFileMods();
    }

    /**
     * @return array
     */
    public function showFileCategories()
    {
        return $this->fileUtilityService->showFileCategories();
    }

}
