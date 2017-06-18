<?php

/**
 * File Service.
 * The service supplies methods that resolve logic around File objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\FileRepository;
use TmoAuth\Entity\User;
use Zend\I18n\Validator\Alnum;

class FileService extends BaseService
{

    /**
     * Get detailed information about a file.
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function statFile($resourceId, $contentArray)
    {
        // init response
        $response = false;
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
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
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Name", $targetFile->getName());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %smu</pre>', "Size", $targetFile->getSize());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Level", $targetFile->getLevel());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Version", $targetFile->getVersion());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Type", $targetFile->getFileType()->getName());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s/%s</pre>', "Integrity", $targetFile->getIntegrity(), $targetFile->getMaxIntegrity());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Slots", $targetFile->getSlots());
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Birth", $targetFile->getCreated()->format('Y/m/d H:i:s'));
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s: %s</pre>', "Modified", ($targetFile->getModified()) ? $targetFile->getModified()->format('Y/m/d H:i:s') : "---");
            $response = array(
                'command' => 'stat',
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
        $response = false;
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
        if ($profile->getSnippets() < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You need 1 snippet to create an empty text file</pre>')
            );
        }
        if (!$response && count($targetFiles) >= 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">A file with that name already exists in this node</pre>')
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">The file name contains non-alphanumeric characters</pre>')
            );
        }
        // check if max of 32 characters
        if (mb_strlen($parameter) > 32) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid file name (32-characters-max)</pre>')
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
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been created</pre>', $parameter)
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
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        $response = false;
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf("<pre style=\"white-space: pre-wrap;\" class=\"text-sysmsg\">No such file</pre>")
            );
        }
        $file = array_shift($targetFiles);
        if (!$response && !$file) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">File not found</pre>')
            );
        }
        // now ge the new name
        $newName = implode(' ', $contentArray);
        $newName = trim($newName);
        if (!$response && !$newName) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Please specify a new name (alpha-numeric only, 32-chars-max)</pre>')
            );
        }
        // check if they can change the type
        if (!$response && $profile != $file->getProfile()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Permission denied</pre>')
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
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // init response
        $response = false;
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        // get file repo
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        // try to get target file via repo method
        $targetFiles = $fileRepository->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        if (count($targetFiles) < 1) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">No such file</pre>')
            );
        }
        $file = array_shift($targetFiles);
        // check if file belongs to user TODO should be able to bypass this via bh program
        if (!$response && $file->getProfile() != $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">You are not allowed to execute %s</pre>', $file->getName())
            );
        }
        // check if already running
        if (!$response && $file->getRunning()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s is already running</pre>', $file->getName())
            );
        }
        // check if there is enough memory to execute this
        if (!$response && !$this->canExecuteFile($profile, $file)) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You do not have enough memory to execute %s - build more memory nodes</pre>', $file->getName())
            );
        }
        if (!$response) {
            // determine what to do depending on file type
            switch ($file->getFileType()->getId()) {
                default:
                    $response = array(
                        'command' => 'showmessage',
                        'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s is not executable, yet</pre>', $file->getName())
                    );
                    break;
                case FileType::ID_CHATCLIENT:
                    $response = $this->executeChatClient($file);
                    break;
                case FileType::ID_DATAMINER:
                    $response = $this->executeDataminer($file, $profile, $profile->getCurrentNode());
                    break;
                case FileType::ID_COINMINER:
                    $response = $this->executeCoinminer($file, $profile, $profile->getCurrentNode());
                    break;
                case FileType::ID_ICMP_BLOCKER:
                    $response = $this->executeIcmpBlocker($file, $profile, $profile->getCurrentNode());
                    break;
            }
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
            'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>', $file->getName(), $file->getId())
        );
        return $response;
    }

    /**
     * @param File $file
     * @param Profile $profile
     * @param Node $node
     * @return array|bool
     */
    protected function executeDataminer(File $file, Profile $profile, Node $node)
    {
        $response = false;
        if ($node->getType() != Node::ID_DATABASE) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a database node</pre>', $file->getName())
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>', $file->getName(), $file->getId())
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Profile $profile
     * @param Node $node
     * @return array|bool
     */
    protected function executeCoinminer(File $file, Profile $profile, Node $node)
    {
        $response = false;
        if ($node->getType() != Node::ID_TERMINAL) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in a terminal node</pre>', $file->getName())
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>', $file->getName(), $file->getId())
            );
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Profile $profile
     * @param Node $node
     * @return array|bool
     */
    protected function executeIcmpBlocker(File $file, Profile $profile, Node $node)
    {
        $response = false;
        if ($node->getType() != Node::ID_IO) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">%s can only be used in an I/O node</pre>', $file->getName())
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has been started as process %s</pre>', $file->getName(), $file->getId())
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
        $parameter = array_shift($contentArray);
        $parameter = (int)$parameter;
        // init response
        $response = false;
        if (!$parameter) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Please specify the process id to kill (ps for list)</pre>')
            );
        }
        $runningFile = (!$response) ? $this->entityManager->find('Netrunners\Entity\File', $parameter) : NULL;
        if (!$response && !$runningFile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid process id</pre>')
            );
        }
        if (!$response && $runningFile->getProfile() != $profile) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-warning">Invalid process id</pre>')
            );
        }
        if (!$response && !$runningFile->getRunning()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">No process with that id</pre>')
            );
        }
        if (!$response && $runningFile->getSystem() != $profile->getCurrentNode()->getSystem())  {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">That process needs to be killed in the system that it is running in</pre>')
            );
        }
        if (!$response && $runningFile->getNode() != $profile->getCurrentNode()) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">That process needs to be killed in the node that it is running in</pre>')
            );
        }
        if (!$response) {
            $runningFile->setRunning(false);
            $runningFile->setSystem(NULL);
            $runningFile->setNode(NULL);
            $this->entityManager->flush($runningFile);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Process with id %s has been killed</pre>', $runningFile->getId())
            );
        }
        return $response;
    }

    /**
     * Lists all files in a directory.
     * @param $clientData
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listDirectory($clientData)
    {
        // TODO output format parameters
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $directoryChildren = $profile->getCurrentDirectory()->getChildren();
        $returnMessage = array();
        foreach ($directoryChildren as $directoryChild) {
            /** @var File $directoryChild */
            $returnMessage[] = array(
                'type' => $directoryChild->getFileType()->getId(),
                'name' => $directoryChild->getName(),
                'running' => ($directoryChild->getRunning()) ? 1 : 0
            );
        }
        $response = array(
            'command' => 'ls',
            'message' => $returnMessage
        );
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
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s|%-20s|%s</pre>', $runningFile->getId(), $runningFile->getFileType()->getName() ,$runningFile->getName());
        }
        $response = array(
            'command' => 'ps',
            'message' => $returnMessage
        );
        return $response;
    }

}
