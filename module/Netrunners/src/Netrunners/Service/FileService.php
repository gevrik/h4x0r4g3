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
use Zend\View\Model\ViewModel;

class FileService extends BaseService
{

    /**
     * Get detailed information about a file.
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function statFile($clientData, $contentArray)
    {
        // init response
        $response = false;
        // get user
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
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => "No such file"
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

    public function touchFile($clientData, $contentArray)
    {
        // init response
        $response = false;
        // get user
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
        $targetFiles = $fileRepository->findFileInNodeByName(
            $profile->getCurrentNode(),
            $parameter
        );
        if ($profile->getSnippets() < 1) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => 'You need 1 snippet to create an empty text file'
            );
        }
        if (!$response && count($targetFiles) >= 1) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => 'A file with that name already exists in this node'
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => false));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => 'The file name contains non-alphanumeric characters'
            );
        }
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
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('%s has been created', $parameter)
            );
            return $response;
        }
        return $response;
    }

    public function editFile($clientData, $contentArray)
    {
        // init response
        $response = false;
        // get user
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
        $targetFiles = $fileRepository->findFileInNodeByName(
            $profile->getCurrentNode(),
            $parameter
        );
        if (count($targetFiles) < 1) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "No such file"
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => false));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "No such file"
            );
        }
        /* start logic if we do not have a response already */
        if (!$response) {
            $view = new ViewModel();
            $view->setTemplate('netrunners/file/edit-text.phtml');
            $response = array(
                'command' => 'showPanel',
                'type' => 'warning',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $response;
    }


    public function executeFile($clientData, $contentArray)
    {
        // get user
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
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => "No such file"
            );
        }
        $file = $targetFile = array_shift($targetFiles);
        // check if file belongs to user TODO should be able to bypass this via bh program
        if (!$response && $file->getProfile() != $profile) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf("You are not allowed to execute %s", $file->getName())
            );
        }
        // check if already running
        if (!$response && $file->getRunning()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf("%s is already running", $file->getName())
            );
        }
        if (!$response) {
            // determine what to do depending on file type
            switch ($file->getFileType()->getId()) {
                default:
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'sysmsg',
                        'message' => sprintf("%s is not executable, yet", $file->getName())
                    );
                    break;
                case FileType::ID_CHATCLIENT:
                    $response = $this->executeChatClient($file);
                    break;
                case FileType::ID_DATAMINER:
                    $response = $this->executeDataminer($file, $profile, $profile->getCurrentNode());
                    break;
            }
        }
        return $response;
    }

    /**
     * Kill a running program.
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function killProcess($clientData, $contentArray)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        // init response
        $response = false;
        if (!$parameter) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => 'Please specify the process id to kill (ps for list)'
            );
        }
        $runningFile = (!$response) ? $this->entityManager->find('Netrunners\Entity\File', $parameter) : NULL;
        if (!$response && !$runningFile) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => 'Invalid process id'
            );
        }
        if (!$response && $runningFile->getProfile() != $profile) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => 'Invalid process id'
            );
        }
        if (!$response && !$runningFile->getRunning()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => 'No process with that id'
            );
        }
        if (!$response) {
            $runningFile->setRunning(false);
            $this->entityManager->flush($runningFile);
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('Process with id %s has been killed', $runningFile->getId())
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
     * @param $clientData
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listProcesses($clientData)
    {
        // TODO add more info to output
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
            $returnMessage[] = sprintf('<pre style="white-space: pre-wrap;">%-12s | %s</pre>', $runningFile->getId(), $runningFile->getName());
        }
        $response = array(
            'command' => 'ps',
            'message' => $returnMessage
        );
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
            'command' => 'showMessage',
            'type' => 'sysmsg',
            'message' => sprintf('<pre style="white-space: pre-wrap;">%s has been started as process %s</pre>', $file->getName(), $file->getId())
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
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">%s can only be used in a database node</pre>', $file->getName())
            );
        }
        if (!$response) {
            $file->setRunning(true);
            $file->setSystem($node->getSystem());
            $file->setNode($node);
            $this->entityManager->flush($file);
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => sprintf('<pre style="white-space: pre-wrap;">%s has been started as process %s</pre>', $file->getName(), $file->getId())
            );
        }
        return $response;
    }

    /**
     * Returns all running programs of the given type in the given system.
     * @param System $system
     * @param bool|true $running
     * @param null|FileType $fileType
     * @return array
     */
    public function findRunningInSystemByType(System $system, $running = true, FileType $fileType = NULL)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $programs = $fileRepo->findRunningFilesInSystemByType($system, $running, $fileType);
        return $programs;
    }

    /**
     * @param System $system
     * @return int
     */
    public function getTotalSizeOfSystem(System $system)
    {
        $allFiles = $this->findRunningInSystemByType($system, false, NULL);
        $totalSize = 0;
        foreach ($allFiles as $file) {
            /** @var File $file */
            $totalSize += $file->getSize();
        }
        return $totalSize;
    }

    /**
     * @param System $system
     * @return int
     */
    public function getTotalMemoryUsageOfSystem(System $system)
    {
        $allFiles = $this->findRunningInSystemByType($system, true, NULL);
        $totalSize = 0;
        foreach ($allFiles as $file) {
            /** @var File $file */
            $totalSize += $file->getSize();
        }
        return $totalSize;
    }

}
