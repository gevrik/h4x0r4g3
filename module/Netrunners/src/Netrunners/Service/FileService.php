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
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\System;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class FileService extends BaseService
{

    const DEFAULT_DIFFICULTY_MOD = 10;

    /**
     * @var CodebreakerService
     */
    protected $codebreakerService;

    /**
     * @var FileRepository
     */
    protected $fileRepo;


    /**
     * FileService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param CodebreakerService $codebreakerService
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        CodebreakerService $codebreakerService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->codebreakerService = $codebreakerService;
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
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
                'message' => '<pre style="white-space: pre-wrap;" class="text-sysmsg">No such file</pre>'
            );
        }
        /* start logic if we do not have a response already */
        if (!$this->response) {
            $targetFile = array_shift($targetFiles);
            /** @var File $targetFile */
            $returnMessage = array();
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s: %s</pre>',
                $this->translate("Name"),
                $targetFile->getName()
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
        if (!$this->response && $profile->getSnippets() < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need 1 snippet to create an empty text file')
                )
            );
        }
        if (!$this->response && count($targetFiles) >= 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('A file with that name already exists in this node')
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been created</pre>'),
                    $parameter
                )
            );
            return $this->response;
        }
        return $this->response;
    }

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
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-sysmsg\">%s</pre>",
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
                }
            }
        }
        return $this->response;
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
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-sysmsg\">%s</pre>",
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
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function executeFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
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
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        // check if file belongs to user TODO should be able to bypass this via bh program
        if (!$this->response && $file->getProfile() != $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You are not allowed to execute %s</pre>'),
                    $file->getName()
                )
            );
        }
        // check if already running
        if (!$this->response && $file->getRunning()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s is already running</pre>'),
                    $file->getName()
                )
            );
        }
        // check if there is enough memory to execute this
        if (!$this->response && !$this->canExecuteFile($profile, $file) && !in_array($file->getFileType()->getId(), [])) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You do not have enough memory to execute %s - build more memory nodes</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$this->response) {
            // determine what to do depending on file type
            switch ($file->getFileType()->getId()) {
                default:
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s is not executable, yet</pre>'),
                            $file->getName()
                        )
                    );
                    break;
                case FileType::ID_CHATCLIENT:
                    $this->response = $this->executeChatClient($file);
                    break;
                case FileType::ID_DATAMINER:
                    $this->response = $this->executeDataminer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_COINMINER:
                    $this->response = $this->executeCoinminer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_ICMP_BLOCKER:
                    $this->response = $this->executeIcmpBlocker($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_PORTSCANNER:
                case FileType::ID_JACKHAMMER:
                    $this->response = $this->queueProgramExecution($resourceId, $file, $profile->getCurrentNode(), $contentArray);
                    break;
                case FileType::ID_CODEBREAKER:
                    $this->response = $this->codebreakerService->startCodebreaker($resourceId, $file, $contentArray);
                    break;
                case FileType::ID_CUSTOM_IDE:
                    $this->response = $this->executeCustomIde($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_SKIMMER:
                    $this->response = $this->executeSkimmer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_BLOCKCHAINER:
                    $this->response = $this->executeBlockchainer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_IO_TRACER:
                    $this->response = $this->executeIoTracer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_OBFUSCATOR:
                    $this->response = $this->executeObfuscator($file);
                    break;
                case FileType::ID_CLOAK:
                    $this->response = $this->executeCloak($file);
                    break;
                case FileType::ID_LOG_ENCRYPTOR:
                    $this->response = $this->executeLogEncryptor($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_LOG_DECRYPTOR:
                    $this->response = $this->executeLogDecryptor($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_PHISHER:
                    $this->response = $this->executePhisher($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_CODEBLADE:
                case FileType::ID_CODEBLASTER:
                case FileType::ID_CODESHIELD:
                case FileType::ID_CODEARMOR:
                    $this->response = $this->equipFile($file);
                    break;
            }
        }
        return $this->response;
    }

    /**
     * @param File $file
     * @return array|false
     */
    private function equipFile(File $file)
    {
        $profile = $file->getProfile();
        $messages = [];
        switch ($file->getFileType()->getId()) {
            default:
                break;
            case FileType::ID_CODEBLADE:
                $currentBlade = $profile->getBlade();
                if ($currentBlade) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                        $currentBlade->getName()
                    );
                    $currentBlade->setRunning(false);
                    $profile->setBlade(NULL);
                    $this->entityManager->flush($currentBlade);
                }
                if (!$this->canExecuteFile($profile, $file)) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                        $file->getName()
                    );
                }
                else {
                    $profile->setBlade($file);
                    $file->setRunning(true);
                    $this->entityManager->flush($file);
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your blade module</pre>'),
                        $file->getName()
                    );
                }
                $this->entityManager->flush($profile);
                break;
            case FileType::ID_CODEBLASTER:
                $currentBlaster = $profile->getBlaster();
                if ($currentBlaster) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                        $currentBlaster->getName()
                    );
                    $currentBlaster->setRunning(false);
                    $profile->setBlaster(NULL);
                    $this->entityManager->flush($currentBlaster);
                }
                if (!$this->canExecuteFile($profile, $file)) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                        $file->getName()
                    );
                }
                else {
                    $profile->setBlaster($file);
                    $file->setRunning(true);
                    $this->entityManager->flush($file);
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your blaster module</pre>'),
                        $file->getName()
                    );
                }
                $this->entityManager->flush($profile);
                break;
            case FileType::ID_CODESHIELD:
                $currentShield = $profile->getBlaster();
                if ($currentShield) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                        $currentShield->getName()
                    );
                    $currentShield->setRunning(false);
                    $profile->setShield(NULL);
                    $this->entityManager->flush($currentShield);
                }
                if (!$this->canExecuteFile($profile, $file)) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                        $file->getName()
                    );
                }
                else {
                    $profile->setShield($file);
                    $file->setRunning(true);
                    $this->entityManager->flush($file);
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your shield module</pre>'),
                        $file->getName()
                    );
                }
                $this->entityManager->flush($profile);
                break;
            case FileType::ID_CODEARMOR:
                $fileData = json_decode($file->getData());
                if (!$fileData) {
                    $messages[] = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] has not been initialized yet</pre>'),
                        $file->getName()
                    );
                }
                else {
                    switch ($fileData->subtype) {
                        default:
                            break;
                        case FileType::SUBTYPE_ARMOR_HEAD:
                            $currentArmor = $profile->getHeadArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setHeadArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setHeadArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your head armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_SHOULDERS:
                            $currentArmor = $profile->getShoulderArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setShoulderArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setShoulderArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your shoulder armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_UPPER_ARM:
                            $currentArmor = $profile->getUpperArmArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setUpperArmArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setUpperArmArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your upper-arm armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_LOWER_ARM:
                            $currentArmor = $profile->getLowerArmArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setLowerArmArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setLowerArmArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your lower-arm armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_HANDS:
                            $currentArmor = $profile->getHandArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setHandArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setHandArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your hands armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_TORSO:
                            $currentArmor = $profile->getTorsoArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setTorsoArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setTorsoArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your torso armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_LEGS:
                            $currentArmor = $profile->getLegArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setLegArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setLegArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your leg armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_SHOES:
                            $currentArmor = $profile->getShoesArmor();
                            if ($currentArmor) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You remove the [%s]</pre>'),
                                    $currentArmor->getName()
                                );
                                $currentArmor->setRunning(false);
                                $profile->setShoesArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute [%s]</pre>'),
                                    $file->getName()
                                );
                            }
                            else {
                                $profile->setShoesArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $messages[] = sprintf(
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You now use [%s] as your shoes armor module</pre>'),
                                    $file->getName()
                                );
                            }
                            $this->entityManager->flush($profile);
                            break;
                    }
                }
                break;
        }
        $this->response = [
            'command' => 'showoutput',
            'message' => $messages
        ];
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param Node $node
     * @param $contentArray
     * @return array|bool|mixed
     */
    private function queueProgramExecution($resourceId, File $file, Node $node, $contentArray)
    {
        $executeWarning = false;
        $parameterArray = [];
        $message = '';
        switch ($file->getFileType()->getId()) {
            default:
                break;
            case FileType::ID_PORTSCANNER:
                list($executeWarning, $systemId) = $this->executeWarningPortscanner($file, $node, $contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'systemId' => $systemId,
                    'contentArray' => $contentArray,
                ];
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You start portscanning with [%s] - please wait</pre>'),
                    $file->getName()
                );
                break;
            case FileType::ID_JACKHAMMER:
                list($executeWarning, $systemId, $nodeId) = $this->executeWarningJackhammer($file, $node, $contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'systemId' => $systemId,
                    'nodeId' => $nodeId,
                    'contentArray' => $contentArray,
                ];
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You start breaking into the system with [%s] - please wait</pre>'),
                    $file->getName()
                );
                break;
        }
        if ($executeWarning) {
            $response = $executeWarning;
        }
        else {
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . $file->getFileType()->getExecutionTime() . 'S'));
            $actionData = [
                'command' => 'executeprogram',
                'completion' => $completionDate,
                'blocking' => $file->getFileType()->getBlocking(),
                'fullblock' => $file->getFileType()->getFullblock(),
                'parameter' => $parameterArray
            ];
            $this->getWebsocketServer()->setClientData($resourceId, 'action', $actionData);
            $response = array(
                'command' => 'showmessage',
                'message' => $message,
                'timer' => $file->getFileType()->getExecutionTime()
            );
        }
        return $response;
    }

    /**
     * Executes a chat client file.
     * @param File $file
     * @return array
     */
    protected function executeChatClient(File $file)
    {
        $file->setRunning(true);
        $this->entityManager->flush($file);
        $response = array(
            'command' => 'showmessage',
            'message' => sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                $file->getName(),
                $file->getId()
            )
        );
        return $response;
    }

    /**
     * Executes an obfuscator file.
     * @param File $file
     * @return array
     */
    protected function executeObfuscator(File $file)
    {
        $file->setRunning(true);
        $this->entityManager->flush($file);
        $response = array(
            'command' => 'showmessage',
            'message' => sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                $file->getName(),
                $file->getId()
            )
        );
        return $response;
    }

    /**
     * Executes an obfuscator file.
     * @param File $file
     * @return array
     */
    protected function executeCloak(File $file)
    {
        $file->setRunning(true);
        $this->entityManager->flush($file);
        $response = array(
            'command' => 'showmessage',
            'message' => sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                $file->getName(),
                $file->getId()
            )
        );
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeDataminer(File $file, Node $node)
    {
        // init response
        $response = false;
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a database node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeCoinminer(File $file, Node $node)
    {
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a terminal node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeCustomIde(File $file, Node $node)
    {
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a coding node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeSkimmer(File $file, Node $node)
    {
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a banking node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeBlockchainer(File $file, Node $node)
    {
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a banking node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeIoTracer(File $file, Node $node)
    {
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in io nodes</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeIcmpBlocker(File $file, Node $node)
    {
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in an I/O node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(), $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeLogEncryptor(File $file, Node $node)
    {
        // init response
        $response = false;
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a monitoring node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeLogDecryptor(File $file, Node $node)
    {
        // init response
        $response = false;
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a monitoring node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executePhisher(File $file, Node $node)
    {
        // init response
        $response = false;
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in an intrusion node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return array|bool
     */
    protected function executeBeartrap(File $file, Node $node)
    {
        // init response
        $response = false;
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a firewall node</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @param $contentArray
     * @return array
     */
    public function executeWarningPortscanner(File $file, Node $node, $contentArray)
    {
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in an I/O node</pre>'),
                    $file->getName()
                )
            );
        }
        $addy = $this->getNextParameter($contentArray, false);
        if (!$response && !$addy) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a system address to scan')
                ),
            );
        }
        $systemId = false;
        $system = false;
        if (!$response) {
            $system = $systemRepo->findOneBy([
                'addy' => $addy
            ]);
        }
        if (!$response) {
            if (!$system) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Invalid system address')
                    ),
                );
            }
            else {
                $systemId = $system->getId();
            }
        }
        /** @var System $system */
        $profile = $file->getProfile();
        /** @var Profile $profile */
        if (!$response && $system->getProfile() == $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Invalid system - unable to scan own systems')),
            );
        }
        return [$response, $systemId];
    }

    /**
     * @param File $file
     * @param Node $node
     * @param $contentArray
     * @return array
     */
    public function executeWarningJackhammer(File $file, Node $node, $contentArray)
    {
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in an I/O node</pre>'),
                    $file->getName()
                )
            );
        }
        list($contentArray, $addy) = $this->getNextParameter($contentArray, true);
        if (!$addy) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a system address to break in to')
                ),
            );
        }
        $systemId = false;
        $system = false;
        if (!$response) {
            $system = $systemRepo->findOneBy([
                'addy' => $addy
            ]);
        }
        if (!$response) {
            if (!$system) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Invalid system address')
                    ),
                );
            }
            else {
                $systemId = $system->getId();
            }
        }
        /** @var System $system */
        $profile = $file->getProfile();
        /** @var Profile $profile */
        if (!$response && $system->getProfile() == $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Invalid system - unable to break in to your own systems')
                ),
            );
        }
        // now check if a node id was given
        $nodeId = $this->getNextParameter($contentArray, false, true);
        if (!$response && !$nodeId) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a node ID to break in to')
                ),
            );
        }
        if (!$response) {
            $node = $this->entityManager->find('Netrunners\Entity\Node', $nodeId);
            /** @var Node $node */
            if (!$this->getNodeAttackDifficulty($node)) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Invalid node ID')
                    ),
                );
            }
        }
        return [$response, $systemId, $nodeId];
    }

    /**
     * @param File $file
     * @param System $system
     * @return array|bool
     */
    public function executePortscanner(File $file, System $system)
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $response = false;
        if (!$response) {
            $fileLevel = $file->getLevel();
            $fileIntegrity = $file->getIntegrity();
            $skillRating = $this->getSkillRating($file->getProfile(), Skill::ID_COMPUTING);
            $baseChance = ($fileLevel + $fileIntegrity + $skillRating) / 2;
            $nodes = $nodeRepo->findBySystem($system);
            $messages = [];
            foreach ($nodes as $node) {
                /** @var Node $node */
                $difficulty = $this->getNodeAttackDifficulty($node);
                if ($difficulty) {
                    $roll = mt_rand(1, 100);
                    if ($roll <= $baseChance - $difficulty) {
                        $messages[] = sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-white">%-45s|%-11s|%-20s|%s</pre>',
                            $system->getAddy(),
                            $node->getId(),
                            $node->getNodeType()->getName(),
                            $node->getName()
                        );
                    }
                }
            }
            if (empty($messages)) {
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">PORTSCANNER RESULTS FOR %s</pre>'),
                    $system->getAddy()
                );
                $messages[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No vulnerable nodes detected')
                );
            }
            else {
                array_unshift($messages, sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">PORTSCANNER RESULTS FOR %s</pre>'),
                    $system->getAddy()
                ));
                array_unshift($messages, sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-45s|%-11s|%-20s|%s</pre>',
                    $this->translate('address'),
                    $this->translate('id'),
                    $this->translate('nodetype'),
                    $this->translate('nodename')
                ));
            }
            $response = array(
                'command' => 'showoutputprepend',
                'message' => $messages
            );
        }
        return $response;
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
        $response = false;
        $fileLevel = $file->getLevel();
        $fileIntegrity = $file->getIntegrity();
        $skillRating = $this->getSkillRating($file->getProfile(), Skill::ID_COMPUTING);
        $baseChance = ($fileLevel + $fileIntegrity + $skillRating) / 2;
        $difficulty = $node->getLevel() * self::DEFAULT_DIFFICULTY_MOD;
        $messages = [];
        $roll = mt_rand(1, 100);
        if ($roll <= $baseChance - $difficulty) {
            $messages[] = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">JACKHAMMER RESULTS FOR %s:%s</pre>'),
                $system->getAddy(),
                $node->getId()
            );
            $messages[] = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You break in to the target system\'s node</pre>')
            );
            $profile = $file->getProfile();
            /** @var Profile $profile */
            $response = $this->movePlayerToTargetNode($resourceId, $profile, NULL, $file->getProfile()->getCurrentNode(), $node);
        }
        else {
            $messages[] = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">JACKHAMMER RESULTS FOR %s:%s</pre>'),
                $system->getAddy(),
                $node->getId()
            );
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('You fail to break in to the target system')
            );
        }
        if (!$response) {
            $response = array(
                'command' => 'showoutputprepend',
                'message' => $messages
            );
        }
        return $response;
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
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
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
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No process with that id')
                )
            );
        }
        if (!$this->response && $runningFile->getSystem() != $profile->getCurrentNode()->getSystem())  {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('That process needs to be killed in the system that it is running in')
                )
            );
        }
        if (!$this->response && $runningFile->getNode() != $profile->getCurrentNode()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('That process needs to be killed in the node that it is running in')
                )
            );
        }
        if (!$this->response) {
            $runningFile->setRunning(false);
            $runningFile->setSystem(NULL);
            $runningFile->setNode(NULL);
            $this->entityManager->flush($runningFile);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">Process with id %s has been killed</pre>'),
                    $runningFile->getId()
                )
            );
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
            $runningFiles = $this->fileRepo->findBy(array(
                'profile' => $profile,
                'running' => true
            ));
        }
        else {
            $runningFiles = $this->fileRepo->findBy(array(
                'system' => $profile->getCurrentNode()->getSystem(),
                'running' => true
            ));
        }
        $returnMessage = array();
        if (count($runningFiles) < 1) {
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('No running processes')
            );
        }
        else {
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-12s|%-20s|%s</pre>',
                $this->translate('process-id'),
                $this->translate('file-type'),
                $this->translate('file-name')
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
        $this->response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $this->response;
    }

    /**
     * @return array
     */
    public function showFileTypes()
    {
        $fileTypes = $this->entityManager->getRepository('Netrunners\Entity\FileType')->findBy([
            'codable' => true
        ]);
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-20s|%-4s|%s</pre>',
            $this->translate('name'),
            $this->translate('size'),
            $this->translate('description')
        );
        foreach ($fileTypes as $fileType) {
            /** @var FileType $fileType */
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-20s|%-4s|%s</pre>',
                $fileType->getName(),
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
     * @return array
     */
    public function showFileMods()
    {
        $fileMods = $this->entityManager->getRepository('Netrunners\Entity\FileMod')->findAll();
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-20s|%s</pre>',
            $this->translate('name'),
            $this->translate('description')
        );
        foreach ($fileMods as $fileMod) {
            /** @var FileMod $fileMod */
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-20s|%s</pre>',
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

}
