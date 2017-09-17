<?php

/**
 * FileUtility Service.
 * The service supplies methods that resolve utility logic around File objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\FileCategory;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\FileType;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FileModRepository;
use Netrunners\Repository\FileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class FileUtilityService extends BaseService
{

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
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function changeFileName($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId, true);
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-warning\">%s</pre>",
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        if (!$this->response && !$file) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('File not found')
                )
            );
        }
        // now get the new name
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$this->response && !$newName) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a new name (alpha-numeric only, 32-chars-max)')
                )
            );
        }
        // check if they can change the type
        if (!$this->response && $profile != $file->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // string check
        $this->stringChecker($newName);
        /* all checks passed, we can rename the file now */
        if (!$this->response) {
            $newName = str_replace(' ', '_', $newName);
            $file->setName($newName);
            $this->entityManager->flush($file);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-success">File name changed to %s</pre>', $newName)
            );
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has edited [%s]</pre>'),
                $this->user->getUsername(),
                $newName
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function updateFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId, true);
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, false, true, true);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-warning\">%s</pre>",
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        if (!$this->response && !$file) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('File not found')
                )
            );
        }
        /** @var File $file */
        if (!$this->response && $file && $file->getIntegrity() >= $file->getMaxIntegrity()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('File is already at max integrity')
                )
            );
        }
        /* all checks passed, we can update the file now */
        if (!$this->response && $file) {
            $currentIntegrity = $file->getIntegrity();
            $maxIntegrity = $file->getMaxIntegrity();
            $neededIntegrity = $maxIntegrity - $currentIntegrity;
            if ($neededIntegrity > $profile->getSnippets()) $neededIntegrity = $profile->getSnippets();
            $file->setIntegrity($file->getIntegrity() + $neededIntegrity);
            $this->entityManager->flush($file);
            $profile->setSnippets($profile->getSnippets() - $neededIntegrity);
            $this->entityManager->flush($profile);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">[%s] updated with %s snippets</pre>',
                    $file->getName(),
                    $neededIntegrity
                )
            );
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has updated [%s]</pre>'),
                $this->user->getUsername(),
                $file->getName()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function downloadFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // init response
        $this->response = $this->isActionBlocked($resourceId);
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // try to get target file via repo method
        $targetFile = $this->fileRepo->findOneBy([
            'name' => $parameter,
            'node' => $profile->getCurrentNode()
        ]);
        if (!$this->response && !$targetFile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No such file')
                )
            );
        }
        /** @var File $targetFile */
        // check for mission
        if (!$this->response && $targetFile && $targetFile->getFileType()->getId() == FileType::ID_TEXT) {
            $this->response = $this->executeMissionFile($targetFile, $resourceId);
        }
        // can only download files that do not belong to themselves in owned systems
        if (!$this->response && $targetFile && $targetFile->getProfile() != $profile && $targetFile->getSystem()->getProfile() !== $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if the file is running - can't download then
        if (!$this->response && $targetFile && $targetFile->getRunning()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to download running file')
                )
            );
        }
        // check if the file belongs to a profile or npc - can't download then
        if (!$this->response && $targetFile && $targetFile->getProfile() != NULL) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('This file has already been downloaded by someone')
                )
            );
        }
        if (!$this->response && $targetFile && $targetFile->getNpc() != NULL) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('This file has already been downloaded by an entity')
                )
            );
        }
        // check if there is enough storage to store this
        if (!$this->response && $targetFile && !$this->canStoreFile($profile, $targetFile)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough storage to download %s - build or upgrade storage nodes</pre>'),
                    $targetFile->getName()
                )
            );
        }
        /* all checks passed, download file */
        if (!$this->response && $targetFile) {
            $targetFile->setProfile($profile);
            $targetFile->setNode(NULL);
            $targetFile->setSystem(NULL);
            $this->entityManager->flush($targetFile);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You download %s to your storage</pre>'),
                    $targetFile->getName()
                )
            );
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] downloaded [%s]</pre>'),
                $this->user->getUsername(),
                $targetFile->getName()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $command
     * @param array $contentArray
     * @return array|bool|false
     */
    public function enterMode($resourceId, $command, $contentArray = [])
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $this->getWebsocketServer()->setConfirm($resourceId, $command, $contentArray);
            switch ($command) {
                default:
                    break;
                case 'rm':
                    $file = $this->removeFileChecks($contentArray, $resourceId);
                    if (!$this->response) {
                        $this->response = [
                            'command' => 'enterconfirmmode',
                            'message' => sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-white">Are you sure that you want to delete [%s] - Please confirm this action:</pre>'),
                                $file->getName()
                            )
                        ];
                    }
                    break;
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function harvestCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        $minerString = $this->getNextParameter($contentArray, false);
        if (!$this->response && !$minerString) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Please specify the miner that you want to harvest</pre>'
            );
        }
        $minerId = NULL;
        if (!$this->response) {
            // try to get target file via repo method
            $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $minerString);
            if (!$this->response && count($targetFiles) < 1) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => '<pre style="white-space: pre-wrap;" class="text-warning">No such file</pre>'
                );
            }
            if (!$this->response) {
                $miner = array_shift($targetFiles);
                /** @var File $miner */
                if ($miner->getProfile() != $profile) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>'
                    );
                }
                if (!$this->response) {
                    $minerData = json_decode($miner->getData());
                    if (!isset($minerData->value)) {
                        $this->response = [
                            'command' => 'showmessage',
                            'message' => sprintf(
                                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                                $this->translate('No resources to harvest in that miner')
                            )
                        ];
                    }
                    if (!$this->response && $minerData->value < 1) {
                        $this->response = [
                            'command' => 'showmessage',
                            'message' => sprintf(
                                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                                $this->translate('No resources to harvest in that miner')
                            )
                        ];
                    }
                    if (!$this->response) {
                        $availableResources = $minerData->value;
                        $minerData->value = 0;
                        switch ($miner->getFileType()->getId()) {
                            default:
                                $message = NULL;
                                break;
                            case FileType::ID_DATAMINER:
                                $profile->setSnippets($profile->getSnippets() + $availableResources);
                                $message = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You harvest [%s] snippets from [%s]</pre>'),
                                    $availableResources,
                                    $miner->getName()
                                );
                                break;
                            case FileType::ID_COINMINER:
                                $profile->setCredits($profile->getCredits() + $availableResources);
                                $message = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You harvest [%s] credits from [%s]</pre>'),
                                    $availableResources,
                                    $miner->getName()
                                );
                                break;
                        }
                        if (!$message) {
                            $message = sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">Unable to harvest at this moment</pre>'),
                                $availableResources,
                                $miner->getName()
                            );
                        }
                        $this->response = array(
                            'command' => 'showmessage',
                            'message' => $message
                        );
                        $miner->setData(json_encode($minerData));
                        $this->entityManager->flush($profile);
                        $this->entityManager->flush($miner);
                        // inform other players in node
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] is harvesting [%s]</pre>'),
                            $this->user->getUsername(),
                            $miner->getName()
                        );
                        $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
                    }
                }
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function initArmorCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-warning\">%s</pre>",
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        if (!$this->response && !$file) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('File not found')
                )
            );
        }
        if (!$this->response && $file && $file->getFileType()->getId() != FileType::ID_CODEARMOR) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You can only initialize codearmor files')
                )
            );
        }
        // now get the subtype
        $subtype = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$this->response && !$subtype) {
            $message = [];
            $message[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('Please choose from the following options:')
            );
            $message[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                wordwrap(implode(',', FileType::$armorSubtypeLookup), 120)
            );
            $this->response = array(
                'command' => 'showoutput',
                'message' => $message
            );
        }
        // check if they can change the type
        if (!$this->response && $profile != $file->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // all seems fine - init
        if (!$this->response && $file && $subtype) {
            $fileData = json_decode($file->getData());
            if ($fileData && $fileData->subtype) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('This codearmor has already been initialized')
                    )
                );
            }
            if (!$this->response) {
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
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid subtype')
                        )
                    );
                }
                else {
                    $file->setData(json_encode(['subtype'=>$realType]));
                    $this->entityManager->flush($file);
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have initialized [%s] to subtype [%s]</pre>'),
                            $file->getName(),
                            FileType::$armorSubtypeLookup[$realType]
                        )
                    );
                    // inform other players in node
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has initialized [%s]</pre>'),
                        $this->user->getUsername(),
                        $file->getName()
                    );
                    $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
                }
            }
        }
        return $this->response;
    }

    /**
     * Kill a running program.
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function killProcess($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        // init response
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response && !$parameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify the process id to kill (ps for list)')
                )
            );
        }
        $runningFile = (!$this->response) ? $this->entityManager->find('Netrunners\Entity\File', $parameter) : NULL;
        if (!$this->response && !$runningFile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid process id')
                )
            );
        }
        if (!$this->response && $runningFile->getProfile() != $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid process id')
                )
            );
        }
        if (!$this->response && !$runningFile->getRunning()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No process with that id')
                )
            );
        }
        if (!$this->response && $runningFile->getSystem() != $profile->getCurrentNode()->getSystem())  {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('That process needs to be killed in the system that it is running in')
                )
            );
        }
        if (!$this->response && $runningFile->getNode() != $profile->getCurrentNode()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('That process needs to be killed in the node that it is running in')
                )
            ];
        }
        if (!$this->response) {
            $runningFile->setRunning(false);
            $runningFile->setSystem(NULL);
            $runningFile->setNode(NULL);
            $this->entityManager->flush($runningFile);
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">Process with id [%s] has been killed</pre>'),
                    $runningFile->getId()
                )
            ];
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] killed a process<s/pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * Shows all running processes of the current system.
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function listProcesses($resourceId, $contentArray)
    {
        // TODO add more info to output
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
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
        $returnMessage = [];
        if (count($runningFiles) < 1) {
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                $this->translate('No running processes')
            );
        }
        else {
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-12s|%-20s|%s</pre>',
                $this->translate('PROCESS-ID'),
                $this->translate('FILE-TYPE'),
                $this->translate('FILE-NAME')
            );
            foreach ($runningFiles as $runningFile) {
                /** @var File $runningFile */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-12s|%-20s|%s</pre>',
                    $runningFile->getId(),
                    $runningFile->getFileType()->getName(),
                    $runningFile->getName()
                );
            }
        }
        $this->response = [
            'command' => 'showoutput',
            'message' => $returnMessage
        ];
        return $this->response;
    }

    /**
     * @param $contentArray
     * @param null $resourceId
     * @return mixed|File|null
     */
    private function removeFileChecks($contentArray, $resourceId = NULL)
    {
        $profile = $this->user->getProfile();
        $parameter = $this->getNextParameter($contentArray, false);
        $file = NULL;
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $parameter);
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">No such file</pre>'
            );
        }
        if (!$this->response) {
            $file = array_shift($targetFiles);
            /** @var File $file */
            // check if this file is a mission file
            if ($file && $file->getFileType()->getId() == FileType::ID_TEXT) {
                 $this->response = $this->executeMissionFile($file, $resourceId);
            }
            // check if the file belongs to the profile
            if (!$this->response && $file && $file->getProfile() != $profile) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Permission denied')
                    )
                );
            }
            if (!$this->response && $file->getRunning()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Command failed - program is still running')
                    )
                );
            }
            if (!$this->response && $file->getSystem()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Command failed - please unload the file first')
                    )
                );
            }
        }
        return $file;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function modFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-warning\">%s</pre>",
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        if (!$this->response && !$file) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('File not found')
                )
            );
        }
        /** @var File $file */
        // now get the filemodinstance
        $fileModName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$this->response && !$fileModName) {
            $fileType = $file->getFileType();
            $message = [];
            $message[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('Please choose from the following options:')
            );
            $possibleFileMods = $this->fileModRepo->listForTypeCommand($fileType);
            $fileModListString = '';
            foreach ($possibleFileMods as $possibleFileMod) {
                /** @var FileMod $possibleFileMod */
                $fileModListString .= $possibleFileMod->getName() . ' ';
            }
            $message[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                wordwrap($fileModListString, 120)
            );
            $this->response = array(
                'command' => 'showoutput',
                'message' => $message
            );
        }
        // check if they can change the type
        if (!$this->response && $profile != $file->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if the file can accept more mods
        if (!$this->response && $file && $this->fileModInstanceRepo->countByFile($file) >= $file->getSlots()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('This file can no longer be modded - max mods reached')
                )
            );
        }
        // all seems fine
        if (!$this->response && $file && $fileModName) {
            $fileMod = $this->fileModRepo->findLikeName($fileModName);
            if (!$fileMod) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Unable to find given file mod type')
                    )
                );
            }
            if (!$this->response && $fileMod) {
                // ok, now we know the file and the filemod, try to find a filemodinstance that fits the variables
                $fileModInstances = $this->fileModInstanceRepo->findByProfileAndTypeAndMinLevel($profile, $fileMod, $file->getLevel());
                if (count($fileModInstances) < 1) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You do not own a fitting file-mod of that level')
                        )
                    );
                }
                else {
                    $fileModInstance = array_shift($fileModInstances);
                    /** @var FileModInstance $fileModInstance */
                    $flush = false;
                    $successMessage = false;
                    switch ($fileMod->getId()) {
                        default:
                            break;
                        case FileMod::ID_BACKSLASH:
                            break;
                        case FileMod::ID_INTEGRITY_BOOSTER:
                            $newMaxIntegrity = $file->getMaxIntegrity() + $fileModInstance->getLevel();
                            if ($newMaxIntegrity > 100) $newMaxIntegrity = 100;
                            $file->setMaxIntegrity($newMaxIntegrity);
                            $fileModInstance->setFile($file);
                            $flush = true;
                            $successMessage = sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] has been modded with [%s] - new max-integrity: %s</pre>'),
                                $file->getName(),
                                $fileMod->getName(),
                                $newMaxIntegrity
                            );
                            break;
                    }
                    if ($flush) {
                        $this->entityManager->flush($file);
                        $this->entityManager->flush($fileModInstance);
                        $this->response = array(
                            'command' => 'showmessage',
                            'message' => $successMessage
                        );
                    }
                    else {
                        $this->response = array(
                            'command' => 'showmessage',
                            'message' => sprintf(
                                '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                                $this->translate('This mod has no effect, yet')
                            )
                        );
                    }
                }
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function removeFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId);
        $file = false;
        if (!$this->response) {
            $file = $this->removeFileChecks($contentArray, $resourceId);
        }
        if (!$this->response && $file) {
            // start removing the file by removing all of its filemodinstances
            $fmInstances = $this->fileModInstanceRepo->findBy([
                'file' => $file
            ]);
            foreach ($fmInstances as $fmInstance) {
                $this->entityManager->remove($fmInstance);
            }
            $this->entityManager->remove($file);
            $this->entityManager->flush();
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You removed [%s]</pre>'),
                $file->getName()
            );
            $this->response = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function decompileFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        $file = false;
        if (!$this->response) {
            $file = $this->removeFileChecks($contentArray, $resourceId);
        }
        if (!$this->response && $file) {
            $returnedSnippets = $file->getLevel();
            // start removing the file by removing all of its filemodinstances
            $fmInstances = $this->fileModInstanceRepo->findBy([
                'file' => $file
            ]);
            foreach ($fmInstances as $fmInstance) {
                /** @var FileModInstance $fmInstance */
                $returnedSnippets += $fmInstance->getLevel();
                $this->entityManager->remove($fmInstance);
            }
            $this->entityManager->remove($file);
            $profile->setSnippets($profile->getSnippets()+$returnedSnippets);
            $this->entityManager->flush();
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You decompiled [%s] and received %s snippets</pre>'),
                $file->getName(),
                $returnedSnippets
            );
            $this->response = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $this->response;
    }

    /**
     * @return array
     */
    public function showFileCategories()
    {
        $fileMods = $this->entityManager->getRepository('Netrunners\Entity\FileCategory')->findBy(
            [],
            ['name' => 'ASC']
        );
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%s</pre>',
            $this->translate('FILECAT-NAME'),
            $this->translate('DESCRIPTION')
        );
        foreach ($fileMods as $fileMod) {
            /** @var FileCategory $fileMod */
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-32s|%s</pre>',
                $fileMod->getName(),
                $fileMod->getDescription()
            );
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @return array
     */
    public function showFileMods()
    {
        $fileMods = $this->entityManager->getRepository('Netrunners\Entity\FileMod')->findBy(
            [],
            ['name' => 'ASC']
        );
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%s</pre>',
            $this->translate('FILEMOD-NAME'),
            $this->translate('DESCRIPTION')
        );
        foreach ($fileMods as $fileMod) {
            /** @var FileMod $fileMod */
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-32s|%s</pre>',
                $fileMod->getName(),
                $fileMod->getDescription()
            );
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @return array
     */
    public function showFileTypes()
    {
        $fileTypes = $this->entityManager->getRepository('Netrunners\Entity\FileType')->findBy(
            ['codable' => true],
            ['name' => 'ASC']
        );
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%-20s|%-4s|%s</pre>',
            $this->translate('FILETYPE-NAME'),
            $this->translate('FILETYPE-CATEGORIES'),
            $this->translate('SIZE'),
            $this->translate('DESCRIPTION')
        );
        foreach ($fileTypes as $fileType) {
            /** @var FileType $fileType */
            $categories = '';
            foreach ($fileType->getFileCategories() as $fileCategory) {
                /** @var FileCategory $fileCategory */
                $categories .= $fileCategory->getName() . ' ';
            }
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-32s|%-20s|%-4s|%s</pre>',
                $fileType->getName(),
                $categories,
                $fileType->getSize(),
                $fileType->getDescription()
            );
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * Get detailed information about a file.
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function statFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        $profile = $this->user->getProfile();
        $parameter = $this->getNextParameter($contentArray, false);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">No such file</pre>'
            );
        }
        /* start logic if we do not have a response already */
        if (!$this->response) {
            $targetFile = array_shift($targetFiles);
            /** @var File $targetFile */
            $returnMessage = [];
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Name"),
                $targetFile->getName()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Coder"),
                ($targetFile->getCoder()) ?
                    $targetFile->getCoder()->getUser()->getUsername() :
                    $this->translate('<span class="text-muted">system-generated</span>')
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %smu</pre>',
                $this->translate("Size"),
                $targetFile->getSize()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Level"), $targetFile->getLevel()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Version"),
                $targetFile->getVersion()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Type"),
                $targetFile->getFileType()->getName()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s/%s</pre>',
                $this->translate("Integrity"),
                $targetFile->getIntegrity(),
                $targetFile->getMaxIntegrity()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Slots"),
                $targetFile->getSlots()
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Birth"),
                $targetFile->getCreated()->format('Y/m/d H:i:s')
            );
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Modified"),
                ($targetFile->getModified()) ? $targetFile->getModified()->format('Y/m/d H:i:s') : $this->translate("---")
            );
            $categories = '';
            foreach ($targetFile->getFileType()->getFileCategories() as $fileCategory) {
                /** @var FileCategory $fileCategory */
                $categories .= $fileCategory->getName() . ' ';
            }
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-addon">%s: %s</pre>',
                $this->translate("Categories"),
                $categories
            );
            switch ($targetFile->getFileType()->getId()) {
                default:
                    break;
                case FileType::ID_COINMINER:
                    $fileData = json_decode($targetFile->getData());
                    $returnMessage[] = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-addon">%-12s: %s</pre>',
                        $this->translate("Collected credits"),
                        (isset($fileData->value)) ? $fileData->value : 0
                    );
                    break;
                case FileType::ID_DATAMINER:
                    $fileData = json_decode($targetFile->getData());
                    $returnMessage[] = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-addon">%-12s: %s</pre>',
                        $this->translate("Collected snippets"),
                        (isset($fileData->value)) ? $fileData->value : 0
                    );
                    break;
            }
            // now show its file-mods
            $fileModsCount = $this->fileModInstanceRepo->countByFile($targetFile);
            if ($fileModsCount >= 1) {
                $fileMods = $this->fileModInstanceRepo->findByFile($targetFile);
                $installedModsString = '';
                foreach ($fileMods as $fileMod) {
                    /** @var FileModInstance $fileMod */
                    $installedModsString .= $fileMod->getFileMod()->getName() . ' ';
                }
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-addon">%s %s</pre>',
                    $this->translate("Installed mods:"),
                    wordwrap($installedModsString, 120)
                );
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function touchFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // init response
        $this->response = $this->isActionBlocked($resourceId);
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findFileInNodeByName(
            $profile->getCurrentNode(),
            $parameter
        );
        if (!$this->response && count($targetFiles) >= 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('A file with that name already exists in this node')
                )
            );
        }
        if (!$this->response && $profile->getSnippets() < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need 1 snippet to create an empty text file')
                )
            );
        }
        // check string val and length
        $this->stringChecker($parameter);
        $parameter = str_replace(' ', '_', $parameter);
        /* start logic if we do not have a response already */
        if (!$this->response) {
            $currentSnippets = $profile->getSnippets();
            $profile->setSnippets($currentSnippets - 1);
            $newCode = new File();
            $newCode->setProfile($profile);
            $newCode->setCoder($profile);
            $newCode->setLevel(1);
            $newCode->setFileType($this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_TEXT));
            $newCode->setCreated(new \DateTime());
            $newCode->setExecutable(0);
            $newCode->setIntegrity(100);
            $newCode->setMaxIntegrity(100);
            $newCode->setMailMessage(NULL);
            $newCode->setModified(NULL);
            $newCode->setName($parameter . '.txt');
            $newCode->setRunning(NULL);
            $newCode->setSize(0);
            $newCode->setSlots(0);
            $newCode->setSystem(NULL);
            $newCode->setNode(NULL);
            $newCode->setVersion(1);
            $newCode->setData(NULL);
            $this->entityManager->persist($newCode);
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been created</pre>'),
                    $parameter
                )
            );
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has created a text file</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
            return $this->response;
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function unloadFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // init response
        $this->response = $this->isActionBlocked($resourceId);
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // try to get target file via repo method
        $targetFile = $this->fileRepo->findOneBy([
            'name' => $parameter,
            'profile' => $profile
        ]);
        if (!$this->response && !$targetFile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No such file')
                )
            );
        }
        /** @var File $targetFile */
        // check if the file belongs to the profile
        if (!$this->response && $targetFile && $targetFile->getProfile() != $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if attempt to unload running file
        if (!$this->response && $targetFile && $targetFile->getRunning()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to unload running file')
                )
            );
        }
        /* all checks passed, unload file */
        if (!$this->response && $targetFile) {
            if ($targetFile->getFileType()->getId() == FileType::ID_TEXT) {
                $this->response = $this->executeMissionFile($targetFile, $resourceId);
            }
            if (!$this->response) {
                $targetFile->setProfile(NULL);
                $targetFile->setNode($profile->getCurrentNode());
                $targetFile->setSystem($profile->getCurrentNode()->getSystem());
                $this->entityManager->flush($targetFile);
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You unload %s to the node</pre>'),
                        $targetFile->getName()
                    )
                );
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has unloaded [%s] into the node</pre>'),
                    $this->user->getUsername(),
                    $targetFile->getName()
                );
                $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function useCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
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
        if (!$this->response && !$file) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No such file')
                )
            );
        }
        if (!$this->response && $file && !$file->getRunning()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Files must be running to use them')
                )
            );
        }
        if (!$this->response && $file) {
            $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
            switch ($file->getFileType()->getId()) {
                default:
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to use this type of file')
                        )
                    );
                    break;
                case FileType::ID_WILDERSPACE_HUB_PORTAL:
                    $hubNode = $this->entityManager->find('Netrunners\Entity\Node', $serverSetting->getWildernessHubNodeId());
                    $this->response = $this->movePlayerToTargetNode($resourceId, $profile, NULL, $profile->getCurrentNode(), $hubNode);
                    $this->addAdditionalCommand('flyto', $hubNode->getSystem()->getGeocoords(), true);
                    break;
            }
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has used [%s]</pre>'),
                $this->user->getUsername(),
                $file->getName()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

}
