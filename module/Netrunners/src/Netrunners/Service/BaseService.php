<?php

/**
 * Base Service.
 * The service supplies a base for all complex services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Application\Service\WebsocketService;
use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;

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
     * @return WebsocketService
     */
    protected function getWebsocketServer()
    {
        return WebsocketService::getInstance();
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
        $result = false;
        if ($file->getSize() + $this->getUsedMemory($profile) <= $this->getTotalMemory($profile)) {
            $result = true;
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
        return ($file->getSize() + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? true : false;
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
        return ($size + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? true : false;
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
        foreach ($jobData['skills'] as $skillId) {
            $skill = $this->entityManager->find('Netrunners\Entity\Skill', $skillId);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
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
        foreach ($jobData['skills'] as $skillId) {
            $skill = $this->entityManager->find('Netrunners\Entity\Skill', $skillId);
            $skillRating = $this->getSkillRating($profile, $skill);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }


    protected function getSkillRating(Profile $profile, Skill $skill)
    {
        $skillRatingObject = $this->entityManager->getRepository('Netrunners\Entity\SkillRating')->findByProfileAndSkill($profile, $skill);
        /** @var SkillRating $skillRatingObject */
        return ($skillRatingObject) ? $skillRatingObject->getRating() : 0;
    }

    /**
     * @param Profile $profile
     * @param Skill $skill
     * @param $newSkillRating
     * @return bool
     */
    public function setSkillRating(Profile $profile, Skill $skill, $newSkillRating)
    {
        $skillRatingObject = $this->entityManager->getRepository('Netrunners\Entity\SkillRating')->findByProfileAndSkill($profile, $skill);
        /** @var SkillRating $skillRatingObject */
        $skillRatingObject->setRating($newSkillRating);
        $this->entityManager->flush($skillRatingObject);
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

    /**
     * @param Node $node
     * @param $message
     * @param $profile
     */
    public function messageEveryoneInNode(Node $node, $message, $profile)
    {
        $wsClients = $this->getWebsocketServer()->getClients();
        $wsClientsData = $this->getWebsocketServer()->getClientsData();
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
