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
     * @var FileUtilityService
     */
    protected $fileUtilityService;

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
     * @param FileUtilityService $fileUtilityService
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        CodebreakerService $codebreakerService,
        FileUtilityService $fileUtilityService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->codebreakerService = $codebreakerService;
        $this->fileUtilityService = $fileUtilityService;
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
        /** @var File $file */
        // check if file belongs to user TODO should be able to bypass this via bh program
        if (!$this->response && $file && $file->getProfile() != $profile) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You are not allowed to execute %s</pre>'),
                    $file->getName()
                )
            );
        }
        // check if already running
        if (!$this->response && $file && $file->getRunning()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s is already running</pre>'),
                    $file->getName()
                )
            );
        }
        // check if file has enough integrity
        if (!$this->response && $file && $file->getIntegrity() < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s has no integrity</pre>'),
                    $file->getName()
                )
            );
        }
        // check if there is enough memory to execute this
        if (!$this->response && $file && !$this->canExecuteFile($profile, $file)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough memory to execute %s - build more memory nodes</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$this->response && $file) {
            // determine what to do depending on file type
            switch ($file->getFileType()->getId()) {
                default:
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s is not executable, yet</pre>'),
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
                case FileType::ID_BEARTRAP:
                    $this->response = $this->executeBeartrap($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_PORTSCANNER:
                case FileType::ID_JACKHAMMER:
                case FileType::ID_SIPHON:
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
                case FileType::ID_WILDERSPACE_HUB_PORTAL:
                    $this->response = $this->executeWilderspaceHubPortal($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_RESEARCHER:
                    $this->response = $this->executeResearcher($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_CODEBLADE:
                case FileType::ID_CODEBLASTER:
                case FileType::ID_CODESHIELD:
                case FileType::ID_CODEARMOR:
                    $this->response = $this->equipFile($file);
                    break;
            }
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has executed [%s]</pre>'),
                $this->user->getUsername(),
                $file->getName()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), ['command' => 'showmessageprepend', 'message' => $message], $profile, $profile->getId());
        }
        return $this->response;
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
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
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
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You start portscanning with [%s] - please wait</pre>'),
                    $file->getName()
                );
                break;
            case FileType::ID_JACKHAMMER:
                list($executeWarning, $systemId, $nodeId) = $this->executeWarningJackhammer($file, $node, $contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'systemId' => $systemId,
                    'nodeId' => $nodeId,
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You start breaking into the system with [%s] - please wait</pre>'),
                    $file->getName()
                );
                break;
            case FileType::ID_SIPHON:
                list($executeWarning, $minerId) = $this->executeWarningSiphon($contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'minerId' => $minerId,
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You start siphoning into the miner program with [%s] - please wait</pre>'),
                    $file->getName()
                );
                break;
        }
        if ($executeWarning) {
            $response = $executeWarning;
        }
        else {
            $fileType = $file->getFileType();
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . $fileType->getExecutionTime() . 'S'));
            $actionData = [
                'command' => 'executeprogram',
                'completion' => $completionDate,
                'blocking' => $fileType->getBlocking(),
                'fullblock' => $fileType->getFullblock(),
                'parameter' => $parameterArray
            ];
            $this->getWebsocketServer()->setClientData($resourceId, 'action', $actionData);
            $response = array(
                'command' => 'showmessage',
                'message' => $message,
                'timer' => $fileType->getExecutionTime()
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
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
        if (!$response && $system->getProfile() === $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
        if (!$response && $system->getProfile() === $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid node ID')
                    ),
                );
            }
        }
        return [$response, $systemId, $nodeId];
    }

    /**
     * @param $contentArray
     * @return array
     */
    public function executeWarningSiphon($contentArray)
    {
        $response = false;
        $profile = $this->user->getProfile();
        $minerString = $this->getNextParameter($contentArray, false);
        if (!$minerString) {
            $response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Please specify the miner that you want to siphon from</pre>'
            );
        }
        $minerId = NULL;
        if (!$response) {
            // try to get target file via repo method
            $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $minerString);
            if (!$response && count($targetFiles) < 1) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => '<pre style="white-space: pre-wrap;" class="text-warning">No such file</pre>'
                );
            }
            if (!$response) {
                $miner = array_shift($targetFiles);
                /** @var File $miner */
                switch ($miner->getFileType()->getId()) {
                    default:
                        $response = array(
                            'command' => 'showmessage',
                            'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Invalid file type for Siphon</pre>'
                        );
                        break;
                    case FileType::ID_COINMINER:
                    case FileType::ID_DATAMINER:
                        $minerId = $miner->getId();
                        break;
                }
            }
        }
        return [$response, $minerId];
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
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
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
    protected function executeWilderspaceHubPortal(File $file, Node $node)
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
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
    protected function executeResearcher(File $file, Node $node)
    {
        // init response
        $response = false;
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a memory node</pre>'),
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
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">%s has been started as process %s</pre>'),
                    $file->getName(),
                    $file->getId()
                )
            );
        }
        return $response;
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
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
                                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You remove the [%s]</pre>'),
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
     * @param $contentArray
     * @return array|bool|false
     */
    public function harvestCommand($resourceId, $contentArray)
    {
        return $this->fileUtilityService->harvestCommand($resourceId, $contentArray);
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
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">PORTSCANNER RESULTS FOR : %s</pre>'),
                $system->getAddy()
            );
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                $this->translate('No vulnerable nodes detected')
            );
        }
        else {
            array_unshift($messages, sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-info">PORTSCANNER RESULTS FOR : %s</pre>'),
                $system->getAddy()
            ));
            array_unshift($messages, sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-45s|%-11s|%-20s|%s</pre>',
                $this->translate('SYSTEM-ADDRESS'),
                $this->translate('NODE-ID'),
                $this->translate('NODE-TYPE'),
                $this->translate('NODE-NAME')
            ));
        }
        $response = array(
            'command' => 'showoutputprepend',
            'message' => $messages
        );
        $this->lowerIntegrityOfFile($file);
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
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You break in to the target system\'s node</pre>')
            );
            $profile = $file->getProfile();
            /** @var Profile $profile */
            $response = $this->movePlayerToTargetNode($resourceId, $profile, NULL, $file->getProfile()->getCurrentNode(), $node);
            $response = $this->addAdditionalCommand('flyto', $node->getSystem()->getGeocoords(), true, $response);
        }
        else {
            $messages[] = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">JACKHAMMER RESULTS FOR %s:%s</pre>'),
                $system->getAddy(),
                $node->getId()
            );
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-danger">%s</pre>',
                $this->translate('You fail to break in to the target system')
            );
        }
        if (!$response) {
            $response = array(
                'command' => 'showoutputprepend',
                'message' => $messages
            );
        }
        $this->lowerIntegrityOfFile($file);
        return $response;
    }

    /**
     * @param File $file
     * @param File $miner
     * @return bool|string
     */
    public function executeSiphon(File $file, File $miner)
    {
        $response = false;
        $minerData = json_decode($miner->getData());
        if (!isset($minerData->value)) {
            $response = [
                'command' => 'showmessageprepend',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No resources to siphon in that miner')
                )
            ];
        }
        if (!$response && $minerData->value < 1) {
            $response = [
                'command' => 'showmessageprepend',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('No resources to siphon in that miner')
                )
            ];
        }
        if (!$response) {
            $fileLevel = $file->getLevel();
            $availableResources = $minerData->value;
            $amountSiphoned = ($availableResources < $fileLevel) ? $availableResources : $fileLevel;
            $minerData->value = $minerData->value - $amountSiphoned;
            $profile = $file->getProfile();
            switch ($miner->getFileType()->getId()) {
                default:
                    $message = NULL;
                    break;
                case FileType::ID_DATAMINER:
                    $profile->setSnippets($profile->getSnippets() + $amountSiphoned);
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You siphon [%s] snippets from [%s]</pre>'),
                        $amountSiphoned,
                        $miner->getName()
                    );
                    break;
                case FileType::ID_COINMINER:
                    $profile->setCredits($profile->getCredits() + $amountSiphoned);
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You siphon [%s] credits from [%s]</pre>'),
                        $amountSiphoned,
                        $miner->getName()
                    );
                    break;
            }
            if (!$message) {
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">Unable to siphon at this moment</pre>'),
                    $amountSiphoned,
                    $miner->getName()
                );
            }
            $response = array(
                'command' => 'showmessageprepend',
                'message' => $message
            );
            $miner->setData(json_encode($minerData));
            $this->entityManager->flush($profile);
            $this->entityManager->flush($miner);
            $this->lowerIntegrityOfFile($file, 100, 1, true);
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
