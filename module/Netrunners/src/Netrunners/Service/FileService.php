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
use TmoAuth\Entity\User;
use Zend\I18n\Validator\Alnum;
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
    }

    /**
     * Get detailed information about a file.
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function statFile($resourceId, $contentArray)
    {
        // init response
        $response = $this->isActionBlocked($resourceId, true);
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$response && count($targetFiles) < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-sysmsg">No such file</pre>'
            );
        }
        /* start logic if we do not have a response already */
        if (!$response) {
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
            $response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
            return $response;
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function touchFile($resourceId, $contentArray)
    {
        // init response
        $response = $this->isActionBlocked($resourceId);
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = implode(' ', $contentArray);
        $parameter = trim($parameter);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findFileInNodeByName(
            $profile->getCurrentNode(),
            $parameter
        );
        if (!$response && $profile->getSnippets() < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need 1 snippet to create an empty text file')
                )
            );
        }
        if (!$response && count($targetFiles) >= 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('A file with that name already exists in this node')
                )
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('The file name contains non-alphanumeric characters')
                )
            );
        }
        // check if max of 32 characters
        if (mb_strlen($parameter) > 32) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid file name (32-characters-max)')
                )
            );
        }
        $parameter = str_replace(' ', '_', $parameter);
        /* start logic if we do not have a response already */
        if (!$response) {
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
            $newCode->setName($parameter);
            $newCode->setRunning(NULL);
            $newCode->setSize(0);
            $newCode->setSlots(0);
            $newCode->setSystem(NULL);
            $newCode->setNode(NULL);
            $newCode->setVersion(1);
            $this->entityManager->persist($newCode);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been created</pre>'),
                    $parameter
                )
            );
            return $response;
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function changeFileName($resourceId, $contentArray)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = $this->isActionBlocked($resourceId, true);
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$response && count($targetFiles) < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    "<pre style=\"white-space: pre-wrap;\" class=\"text-sysmsg\">%s</pre>",
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        if (!$response && !$file) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('File not found')
                )
            );
        }
        // now get the new name
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$response && !$newName) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a new name (alpha-numeric only, 32-chars-max)')
                )
            );
        }
        // check if they can change the type
        if (!$response && $profile != $file->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$response && !$validator->isValid($newName)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid file name (alpha-numeric only)</pre>')
            );
        }
        // check if max of 32 characters
        if (mb_strlen($newName) > 32) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid file name (32-characters-max)</pre>')
            );
        }
        //
        if (!$response) {
            $newName = str_replace(' ', '_', $newName);
            $file->setName($newName);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">File name changed to %s</pre>', $newName)
            );
        }
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function executeFile($resourceId, $contentArray)
    {
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        // get profile
        $profile = $user->getProfile();
        // init response
        $response = $this->isActionBlocked($resourceId);
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (!$response && count($targetFiles) < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No such file')
                )
            );
        }
        $file = array_shift($targetFiles);
        // check if file belongs to user TODO should be able to bypass this via bh program
        if (!$response && $file->getProfile() != $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You are not allowed to execute %s</pre>'),
                    $file->getName()
                )
            );
        }
        // check if already running
        if (!$response && $file->getRunning()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s is already running</pre>'),
                    $file->getName()
                )
            );
        }
        // check if there is enough memory to execute this
        if (!$response && !$this->canExecuteFile($profile, $file)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You do not have enough memory to execute %s - build more memory nodes</pre>'),
                    $file->getName()
                )
            );
        }
        if (!$response) {
            // determine what to do depending on file type
            switch ($file->getFileType()->getId()) {
                default:
                    $response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s is not executable, yet</pre>'),
                            $file->getName()
                        )
                    );
                    break;
                case FileType::ID_CHATCLIENT:
                    $response = $this->executeChatClient($file);
                    break;
                case FileType::ID_DATAMINER:
                    $response = $this->executeDataminer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_COINMINER:
                    $response = $this->executeCoinminer($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_ICMP_BLOCKER:
                    $response = $this->executeIcmpBlocker($file, $profile->getCurrentNode());
                    break;
                case FileType::ID_PORTSCANNER:
                case FileType::ID_JACKHAMMER:
                    $response = $this->queueProgramExecution($resourceId, $file, $profile->getCurrentNode(), $contentArray);
                    break;
                case FileType::ID_CODEBREAKER:
                    $response = $this->codebreakerService->startCodebreaker($resourceId, $file, $contentArray);
                    break;
            }
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
            $computingSkill = $this->entityManager->find('Netrunners\Entity\Skill', Skill::ID_COMPUTING);
            /** @var Skill $computingSkill */
            $skillRating = $this->getSkillRating($file->getProfile(), $computingSkill);
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
        $computingSkill = $this->entityManager->find('Netrunners\Entity\Skill', Skill::ID_COMPUTING);
        /** @var Skill $computingSkill */
        $skillRating = $this->getSkillRating($file->getProfile(), $computingSkill);
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
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        // init response
        $response = $this->isActionBlocked($resourceId, true);
        if (!$response && !$parameter) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify the process id to kill (ps for list)')
                )
            );
        }
        $runningFile = (!$response) ? $this->entityManager->find('Netrunners\Entity\File', $parameter) : NULL;
        if (!$response && !$runningFile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid process id')
                )
            );
        }
        if (!$response && $runningFile->getProfile() != $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid process id')
                )
            );
        }
        if (!$response && !$runningFile->getRunning()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No process with that id')
                )
            );
        }
        if (!$response && $runningFile->getSystem() != $profile->getCurrentNode()->getSystem())  {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('That process needs to be killed in the system that it is running in')
                )
            );
        }
        if (!$response && $runningFile->getNode() != $profile->getCurrentNode()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('That process needs to be killed in the node that it is running in')
                )
            );
        }
        if (!$response) {
            $runningFile->setRunning(false);
            $runningFile->setSystem(NULL);
            $runningFile->setNode(NULL);
            $this->entityManager->flush($runningFile);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">Process with id %s has been killed</pre>'),
                    $runningFile->getId()
                )
            );
        }
        return $response;
    }

    /**
     * Shows all running processes of the current system.
     * @param int $resourceId
     * @return array|bool
     */
    public function listProcesses($resourceId)
    {
        // TODO add more info to output
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $runningFiles = $this->entityManager->getRepository('Netrunners\Entity\File')->findBy(array(
            'system' => $profile->getCurrentNode()->getSystem(),
            'running' => true
        ));
        $returnMessage = array();
        foreach ($runningFiles as $runningFile) {
            /** @var File $runningFile */
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-12s|%-20s|%s</pre>',
                $runningFile->getId(),
                $runningFile->getFileType()->getName(),
                $runningFile->getName()
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
