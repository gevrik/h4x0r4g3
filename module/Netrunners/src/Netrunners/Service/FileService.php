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
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\FileRepository;
use Zend\I18n\Validator\Alnum;

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
        $targetFiles = $fileRepository->findFileInSystemByName(
            $profile->getCurrentDirectory()->getSystem(),
            $profile->getCurrentDirectory(),
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
            $targetFile = $targetFiles[0];
            /** @var File $targetFile */
            $returnMessage = array();
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', "Name", $targetFile->getName());
            $returnMessage[] = sprintf('<pre>%-12s: %smu</pre>', "Size", $targetFile->getSize());
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', "Version", $targetFile->getVersion());
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', "Type", File::$fileTypeData[$targetFile->getType()][File::TYPE_KEY_LABEL]);
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', "Birth", $targetFile->getCreated()->format('Y/m/d H:i:s'));
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', "Modified", ($targetFile->getModified()) ? $targetFile->getModified()->format('Y/m/d H:i:s') : "---");
            $response = array(
                'command' => 'stat',
                'message' => $returnMessage
            );
            return $response;
        }
        return $response;
    }

    /**
     * Executes a file.
     * @param File $file
     * @param $clientData
     * @return bool
     */
    public function executeFile(File $file, $clientData)
    {
        // get user
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // init response
        $response = false;
        // check if file belongs to user TODO should be able to bypass this via bh program
        if ($file->getProfile() != $profile) {
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
            switch ($file->getType()) {
                default:
                    $response = array(
                        'command' => 'showMessage',
                        'type' => 'sysmsg',
                        'message' => sprintf("%s is not executable", $file->getName())
                    );
                    break;
                case File::TYPE_CHAT_CLIENT:
                    $response = $this->executeChatClient($file);
                    break;
            }
        }
        return $response;
    }

    /**
     * Create a new directory in the current system and current directory.
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function makeDirectory($clientData, $contentArray)
    {
        // init return value
        $response = false;
        // get user and profile
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        // check if the name is valid TODO check for blacklisted words
        if (strlen($parameter) > 128) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "The directory name is too long, maximum of 128 characters"
            );
        }
        // check if system belongs to user TODO should be able to bypass this via bh program
        if (!$response && $profile->getCurrentDirectory()->getSystem()->getProfile() != $profile) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "You are not allowed to create directories in this system"
            );
        }
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => false));
        if (!$response && !$validator->isValid($parameter)) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "The directory name contains non-alphanumeric characters"
            );
        }
        // check if name is valid
        if (!$response && (!$parameter || $parameter == '')) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "Specify a name for the new directory"
            );
        }
        // check if a file with that name already exists in this directory
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        $targetFile = $fileRepository->findFileInSystemByName(
            $profile->getCurrentDirectory()->getSystem(),
            $profile->getCurrentDirectory(),
            $parameter,
            false
        );
        if (!empty($targetFile)) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "A file with this name already exists in the current directory"
            );
        }
        /* all checks passed - create directory */
        if (!$response) {
            $currentDirectory = $profile->getCurrentDirectory();
            /** @var File $currentDirectory */
            $currentSystem = $currentDirectory->getSystem();
            /** @var System $currentSystem */
            // create directory
            $file = new File();
            $file->setName($parameter);
            $file->setSystem($currentSystem);
            $file->setProfile(NULL);
            $file->setCoder(NULL);
            $file->setCreated(new \DateTime());
            $file->setMaxIntegrity(100);
            $file->setIntegrity(100);
            $file->setLevel(1);
            $file->setParent($currentDirectory);
            $file->setSize(0);
            $file->setVersion(1);
            $file->setType(File::TYPE_DIRECTORY);
            $this->entityManager->persist($file);
            $currentSystem->addFile($file);
            $currentDirectory->addChild($file);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => "New directory created"
            );
        }
        return $response;
    }

    /**
     * Remove a directory in the current system and current directory.
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function removeDirectory($clientData, $contentArray)
    {
        // init return value
        $response = false;
        // get user and profile
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        $parameter = trim($parameter);
        // check for empty parameter
        if (!$parameter || $parameter == '') {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "Specify the name of the directory that is to be removed"
            );
        }
        // find the target file
        $fileRepository = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepository */
        $targetFile = $fileRepository->findFileInSystemByName(
            $profile->getCurrentDirectory()->getSystem(),
            $profile->getCurrentDirectory(),
            $parameter,
            false
        );
        // error if file could not be found
        if (!$response && !isset($targetFile[0])) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "No such directory"
            );
        }
        $targetFile = $targetFile[0];
        /** @var File $targetFile */
        // check if the directory is empty
        if (!$response && !$targetFile->getChildren()->isEmpty()) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "The directory is not empty"
            );
        }
        // check if system belongs to user TODO should be able to bypass this via bh program
        if (!$response && $profile->getCurrentDirectory()->getSystem()->getProfile() != $profile) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => "You are not allowed to remove directories in this system"
            );
        }
        /* all checks passed - we can remove the dir */
        if (!$response) {
            $targetFile->getSystem()->removeFile($targetFile);
            $targetFile->getParent()->removeChild($targetFile);
            $this->entityManager->remove($targetFile);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => "Directory removed"
            );
        }
        return $response;
    }

    /**
     * Changes the current directory.
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function changeDirectory($clientData, $contentArray)
    {
        // TODO complex paths
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $parameter = array_shift($contentArray);
        if (!$parameter) {
            $currentSystem = $profile->getCurrentDirectory()->getSystem();
            $rootDirectory = $this->entityManager->getRepository('Netrunners\Entity\File')->findOneBy(array(
                'system' => $currentSystem,
                'name' => 'home'
            ));
            $profile->setCurrentDirectory($rootDirectory);
            $this->entityManager->flush($profile);
            $response = array(
                'command' => 'refreshPrompt',
                'message' => 'default'
            );
        }
        else if ($parameter == '..') {
            $parentDirectory = $profile->getCurrentDirectory()->getParent();
            if (!$parentDirectory) {
                $response = array(
                    'command' => 'refreshPrompt',
                    'message' => 'default'
                );
            }
            else {
                $profile->setCurrentDirectory($parentDirectory);
                $this->entityManager->flush($profile);
                $response = array(
                    'command' => 'refreshPrompt',
                    'message' => 'default'
                );
            }
        }
        else {
            $currentSystem = $profile->getCurrentDirectory()->getSystem();
            $newDirectory = $this->entityManager->getRepository('Netrunners\Entity\File')->findOneBy(array(
                'system' => $currentSystem,
                'name' => $parameter,
                'type' => File::TYPE_DIRECTORY,
                'parent' => $profile->getCurrentDirectory()
            ));
            if (!$newDirectory) {
                $response = array(
                    'command' => 'showMessage',
                    'type' => 'sysmsg',
                    'message' => 'No such file or directory'
                );
            }
            else {
                $profile->setCurrentDirectory($newDirectory);
                $this->entityManager->flush($profile);
                $response = array(
                    'command' => 'refreshPrompt',
                    'message' => 'default'
                );
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
                'type' => $directoryChild->getType(),
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
            'system' => $profile->getCurrentDirectory()->getSystem(),
            'running' => true
        ));
        $returnMessage = array();
        foreach ($runningFiles as $runningFile) {
            /** @var File $runningFile */
            $returnMessage[] = sprintf('<pre>%-12s | %s</pre>', $runningFile->getId(), $runningFile->getName());
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
            'message' => sprintf("%s has been started as process %s", $file->getName(), $file->getId())
        );
        return $response;
    }

    /**
     * Returns all running programs of the given type in the given system.
     * @param System $system
     * @param bool|true $running
     * @param null $fileType
     * @return array
     */
    public function findRunningInSystemByType(System $system, $running = true, $fileType = NULL)
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
