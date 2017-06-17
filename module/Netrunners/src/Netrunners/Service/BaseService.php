<?php

/**
 * Base Service.
 * The service supplies a base for all complex services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Ratchet\ConnectionInterface;

class BaseService
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    protected $viewRenderer;

    /**
     * BaseService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     */
    public function __construct(
        EntityManager $entityManager,
        $viewRenderer
    )
    {
        $this->entityManager = $entityManager;
        $this->viewRenderer = $viewRenderer;
    }

    /**
     * Checks if the given profile can execute the given file.
     * Returns true if the file can be executed.
     * @param Profile $profile
     * @param File $file
     * @return bool
     */
    protected function canExecuteFile(Profile $profile, File $file)
    {
        $result = true;
        if ($file->getSize() + $this->getUsedMemory($profile) <= $this->getTotalMemory($profile)) {
            $result = false;
        }
        return $result;
    }

    /**
     * Checks if the given profile can store the given file.
     * Returns true if the file can be stored.
     * @param Profile $profile
     * @param File $file
     * @return bool
     */
    protected function canStoreFile(Profile $profile, File $file)
    {
        return ($file->getSize() + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? false : true;
    }

    /**
     * Checks if the given profile can store the given file size.
     * Returns true if the file can be stored.
     * @param Profile $profile
     * @param int $size
     * @return bool
     */
    protected function canStoreFileOfSize(Profile $profile, $size = 0)
    {
        return ($size + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? false : true;
    }

    /**
     * Get the given system's memory value.
     * @param System $system
     * @return int
     */
    protected function getSystemMemory(System $system)
    {
        $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystemAndType($system, Node::ID_MEMORY);
        $total = 0;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $total += $node->getLevel() * SystemService::BASE_MEMORY_VALUE;
        }
        return $total;
    }

    /**
     * Get the given system's storage value.
     * @param System $system
     * @return int
     */
    protected function getSystemStorage(System $system)
    {
        $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystemAndType($system, Node::ID_STORAGE);
        $total = 0;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $total += $node->getLevel() * SystemService::BASE_STORAGE_VALUE;
        }
        return $total;
    }

    /**
     * Get the given profile's total memory.
     * This is calculated from all systems that the profile owns.
     * @param Profile $profile
     * @return int
     */
    protected function getTotalMemory(Profile $profile)
    {
        $systems = $this->entityManager->getRepository('Netrunners\Entity\System')->findByProfile($profile);
        $total = 0;
        foreach ($systems as $system) {
            /** @var System $system */
            $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystemAndType($system, Node::ID_MEMORY);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $total += $node->getLevel() * SystemService::BASE_MEMORY_VALUE;
            }
        }
        return $total;
    }

    /**
     * Get the given profile's total storage.
     * This is calculated from all systems that the profile owns.
     * @param Profile $profile
     * @return int
     */
    protected function getTotalStorage(Profile $profile)
    {
        $systems = $this->entityManager->getRepository('Netrunners\Entity\System')->findByProfile($profile);
        $total = 0;
        foreach ($systems as $system) {
            /** @var System $system */
            $nodes = $this->entityManager->getRepository('Netrunners\Entity\Node')->findBySystemAndType($system, Node::ID_STORAGE);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $total += $node->getLevel() * SystemService::BASE_STORAGE_VALUE;
            }
        }
        return $total;
    }

    /**
     * Get the amount of used memory for the given profile.
     * @param Profile $profile
     * @return int
     */
    protected function getUsedMemory(Profile $profile)
    {
        $amount = 0;
        $files = $this->entityManager->getRepository('Netrunners\Entity\File')->findByProfile($profile);
        foreach ($files as $file) {
            /** @var File $file */
            if ($file->getRunning()) $amount += $file->getSize();
        }
        return $amount;
    }

    /**
     * Get the amount of used storage for the given profile.
     * @param Profile $profile
     * @return int
     */
    protected function getUsedStorage(Profile $profile)
    {
        $amount = 0;
        $files = $this->entityManager->getRepository('Netrunners\Entity\File')->findByProfile($profile);
        foreach ($files as $file) {
            /** @var File $file */
            $amount += $file->getSize();
        }
        return $amount;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @param $roll
     * @return bool
     */
    protected function learnFromSuccess(Profile $profile, $jobData, $roll)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skillRating = $this->getSkillRating($profile, $skillName);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skillName, $newSkillRating);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @param $roll
     * @return bool
     */
    protected function learnFromFailure(Profile $profile, $jobData, $roll)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skillRating = $this->getSkillRating($profile, $skillName);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skillName, $newSkillRating);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $skillName
     * @return int
     */
    protected function getSkillRating(Profile $profile, $skillName)
    {
        $skillRating = 0;
        switch ($skillName) {
            default:
                break;
            case 'coding':
                $skillRating = $profile->getSkillCoding();
                break;
            case 'advancedcoding':
                $skillRating = $profile->getSkillAdvancedCoding();
                break;
            case 'whitehat':
                $skillRating = $profile->getSkillWhitehat();
                break;
            case 'blackhat':
                $skillRating = $profile->getSkillBlackhat();
                break;
            case 'crypto':
                $skillRating = $profile->getSkillCryptography();
                break;
            case 'database':
                $skillRating = $profile->getSkillDatabases();
                break;
            case 'electronics':
                $skillRating = $profile->getSkillElectronics();
                break;
            case 'forensics':
                $skillRating = $profile->getSkillForensics();
                break;
            case 'networking':
                $skillRating = $profile->getSkillNetworking();
                break;
            case 'reverse':
                $skillRating = $profile->getSkillReverseEngineering();
                break;
            case 'social':
                $skillRating = $profile->getSkillSocialEngineering();
                break;
        }
        return $skillRating;
    }

    /**
     * @param Profile $profile
     * @param $skillName
     * @param $newSkillRating
     * @return bool
     */
    public function setSkillRating(Profile $profile, $skillName, $newSkillRating)
    {
        switch ($skillName) {
            default:
                break;
            case 'coding':
                $profile->setSkillCoding($newSkillRating);
                break;
            case 'advancedcoding':
                $profile->setSkillAdvancedCoding($newSkillRating);
                break;
            case 'whitehat':
                $profile->setSkillWhitehat($newSkillRating);
                break;
            case 'blackhat':
                $profile->setSkillBlackhat($newSkillRating);
                break;
            case 'crypto':
                $profile->setSkillCryptography($newSkillRating);
                break;
            case 'database':
                $profile->setSkillDatabases($newSkillRating);
                break;
            case 'electronics':
                $profile->setSkillElectronics($newSkillRating);
                break;
            case 'forensics':
                $profile->setSkillForensics($newSkillRating);
                break;
            case 'networking':
                $profile->setSkillNetworking($newSkillRating);
                break;
            case 'reverse':
                $profile->setSkillReverseEngineering($newSkillRating);
                break;
            case 'social':
                $profile->setSkillSocialEngineering($newSkillRating);
                break;
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param Node $node
     */
    protected function addKnownNode(Profile $profile, Node $node)
    {
        $row = $this->entityManager->getRepository('Netrunners\Entity\KnownNode')->findByProfileAndNode($profile, $node);
        if ($row) {
            /** @var KnownNode $row */
            $row->setType($node->getType());
            $row->setCreated(new \DateTime());
        }
        else {
            $row = new KnownNode();
            $row->setCreated(new \DateTime());
            $row->setProfile($profile);
            $row->setNode($node);
            $row->setType($node->getType());
            $this->entityManager->persist($row);
        }
        $this->entityManager->flush($row);
    }

    /**
     * @param Profile $profile
     * @param Node $node
     * @return mixed
     */
    protected function getKnownNode(Profile $profile, Node $node)
    {
        return $this->entityManager->getRepository('Netrunners\Entity\KnownNode')->findByProfileAndNode($profile, $node);
    }

    protected function messageEveryoneInNode(Node $node, $wsClientsData, $wsClients, $message, $profile)
    {
        $profiles = $this->entityManager->getRepository('Netrunners\Entity\Profile')->findByCurrentNode($node, $profile);
        foreach ($profiles as $xprofile) {
            /** @var Profile $xprofile */
            if ($xprofile == $profile) continue;
            foreach ($wsClients as $wsClient) {
                if (isset($wsClientsData[$wsClient->resourceId]) && $wsClientsData[$wsClient->resourceId]['profileId'] == $xprofile->getId()) {
                    $wsClient->send(json_encode($message));
                }
            }
        }
    }

}
