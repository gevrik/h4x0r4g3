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
use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Repository\FilePartSkillRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeSkillRepository;
use Netrunners\Repository\KnownNodeRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SystemRepository;
use Zend\View\Renderer\PhpRenderer;

class BaseService
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var PhpRenderer
     */
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
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $nodes = $nodeRepo->findBySystemAndType($system, Node::ID_MEMORY);
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
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $nodes = $nodeRepo->findBySystemAndType($system, Node::ID_STORAGE);
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
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systems = $systemRepo->findByProfile($profile);
        $total = 0;
        foreach ($systems as $system) {
            /** @var System $system */
            $nodes = $nodeRepo->findBySystemAndType($system, Node::ID_MEMORY);
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
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systems = $systemRepo->findByProfile($profile);
        $total = 0;
        foreach ($systems as $system) {
            /** @var System $system */
            $nodes = $nodeRepo->findBySystemAndType($system, Node::ID_STORAGE);
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
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $amount = 0;
        $files = $fileRepo->findByProfile($profile);
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
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $amount = 0;
        $files = $fileRepo->findByProfile($profile);
        foreach ($files as $file) {
            /** @var File $file */
            $amount += $file->getSize();
        }
        return $amount;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @return bool
     */
    protected function learnFromSuccess(Profile $profile, $jobData)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $skillName
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (mt_rand(1, 100) <= $chance) {
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
     * @return bool
     */
    protected function learnFromFailure(Profile $profile, $jobData)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $skillName
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill);
            $chance = 100 - $skillRating;
            if ($chance < 1) return true;
            if (mt_rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param Skill $skill
     * @return int
     */
    protected function getSkillRating(Profile $profile, Skill $skill)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $skillRatingObject = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
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
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $skillRatingObject = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
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
        $knownNodeRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
        /** @var KnownNodeRepository $knownNodeRepo */
        $row = $knownNodeRepo->findByProfileAndNode($profile, $node);
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
        $knownNodeRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
        /** @var KnownNodeRepository $knownNodeRepo */
        return $knownNodeRepo->findByProfileAndNode($profile, $node);
    }

    /**
     * @param Node $node
     * @param $message
     * @param $profile
     */
    public function messageEveryoneInNode(Node $node, $message, $profile)
    {
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
        $wsClients = $this->getWebsocketServer()->getClients();
        $wsClientsData = $this->getWebsocketServer()->getClientsData();
        $profiles = $profileRepo->findByCurrentNode($node, $profile);
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

    /**
     * @param array $contentArray
     * @param bool $returnContent
     * @param bool $castToInt
     * @param bool $implode
     * @param bool $makeSafe
     * @param array $safeOptions
     * @return array|int|mixed|null|string
     */
    protected function getNextParameter($contentArray = [], $returnContent = true, $castToInt = false, $implode = false, $makeSafe = false, $safeOptions = ['safe'=>1,'elements'=>'strong'])
    {
        $parameter = NULL;
        $nextParameter = (!$implode) ? array_shift($contentArray) : implode(' ', $contentArray);
        if ($nextParameter !== NULL) {
            var_dump('got next param: ' . $nextParameter);
            trim($nextParameter);
            if ($makeSafe) $nextParameter = htmLawed($nextParameter, $safeOptions);
            if ($castToInt) $nextParameter = (int)$nextParameter;
            $parameter = $nextParameter;
        }
        return ($returnContent) ? [$contentArray, $parameter] : $parameter;
    }

    /**
     * @param Skill $skill
     * @return string
     */
    protected function getInputNameOfSkill(Skill $skill)
    {
        return str_replace(' ', '', $skill->getName());
    }

    /**
     * @param Profile $profile
     * @param $codeOptions
     * @return int
     */
    protected function calculateCodingSuccessChance(Profile $profile, $codeOptions)
    {
        $difficulty = $codeOptions->fileLevel;
        $testSkill = $this->entityManager->find('Netrunners\Entity\Skill', Skill::ID_CODING);
        /** @var Skill $testSkill */
        $skillModifier = 0;
        if ($codeOptions->mode == 'program') {
            $targetType = $this->entityManager->find('Netrunners\Entity\FileType', $codeOptions->fileType);
            /** @var FileType $targetType */
            $skillModifier = $this->getSkillModifierForFileType($targetType, $profile);
        }
        if ($codeOptions->mode == 'resource') {
            $targetType = $this->entityManager->find('Netrunners\Entity\FilePart', $codeOptions->fileType);
            /** @var FilePart $targetType */
            $skillModifier = $this->getSkillModifierForFilePart($targetType, $profile);
        }
        $skillCoding = $this->getSkillRating($profile, $testSkill);
        $skillRating = floor(($skillCoding + $skillModifier)/2);
        $chance = $skillRating - $difficulty;
        return (int)$chance;
    }

    /**
     * @param FileType $fileType
     * @param Profile $profile
     * @return int
     */
    protected function getSkillModifierForFileType(FileType $fileType, Profile $profile)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $fileTypeSkillRepo = $this->entityManager->getRepository('Netrunners\Entity\FileTypeSkill');
        /** @var FileTypeSkillRepository $fileTypeSkillRepo */
        $rating = 0;
        $fileTypeSkills = $fileTypeSkillRepo->findBy([
            'fileType' => $fileType
        ]);
        $amount = 0;
        foreach ($fileTypeSkills as $fileTypeSkill) {
            /** @var FileTypeSkill $fileTypeSkill */
            $amount++;
            $skillRating = $skillRatingRepo->findByProfileAndSkill(
                $profile, $fileTypeSkill->getSkill()
            );
            /** @var SkillRating $skillRating */
            $rating += ($skillRating->getRating()) ? $skillRating->getRating() : 0;
        }
        $rating = ceil($rating/$amount);
        return (int)$rating;
    }

    /**
     * @param FilePart $filePart
     * @param Profile $profile
     * @return int
     */
    protected function getSkillModifierForFilePart(FilePart $filePart, Profile $profile)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $filePartSkillRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartSkill');
        /** @var FilePartSkillRepository $filePartSkillRepo */
        $rating = 0;
        $filePartSkills = $filePartSkillRepo->findBy([
            'filePart' => $filePart
        ]);
        $amount = 0;
        foreach ($filePartSkills as $filePartSkill) {
            /** @var FilePartSkill $filePartSkill */
            $amount++;
            $skillRating = $skillRatingRepo->findByProfileAndSkill(
                $profile, $filePartSkill->getSkill()
            );
            /** @var SkillRating $skillRating */
            $rating += ($skillRating->getRating()) ? $skillRating->getRating() : 0;
        }
        $rating = ceil($rating/$amount);
        return (int)$rating;
    }

    /**
     * @param string $string
     * @param string $replacer
     * @return mixed
     */
    protected function getNameWithoutSpaces($string = '', $replacer = '-')
    {
        return str_replace(' ', $replacer, $string);
    }

    /**
     * @param $resourceId
     * @param Profile $profile
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return array|bool
     */
    protected function movePlayerToTargetNode(
        $resourceId,
        Profile $profile,
        Connection $connection = NULL,
        Node $sourceNode = NULL,
        Node $targetNode = NULL
    )
    {
        if ($connection) {
            $sourceNode = $connection->getSourceNode();
            $targetNode = $connection->getTargetNode();
        }
        // message everyone in source node
        $messageText = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has used the connection to %s</pre>', $profile->getUser()->getUsername(), $targetNode->getName());
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($sourceNode, $message, $profile);
        $profile->setCurrentNode($targetNode);
        $messageText = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has connected to this node from %s</pre>', $profile->getUser()->getUsername(), $sourceNode->getName());
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($targetNode, $message, $profile);
        $this->entityManager->flush($profile);
        return $this->getWebsocketServer()->getNodeService()->showNodeInfo($resourceId);
    }

    protected function isActionBlocked($resourceId, $checkForFullBlock = false)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        if (empty($clientData->action)) return false;
        $actionData = (object)$clientData->action;
        $isBlocked = false;
        if ($checkForFullBlock) {
            if ($actionData->fullblock) $isBlocked = true;
        }
        if (!$isBlocked) {
            if ($actionData->blocking) $isBlocked = true;
        }
        if ($isBlocked) {
            $isBlocked = [
                'command' => 'showmessage',
                'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You are curently busy with something else</pre>')
            ];
        }
        return $isBlocked;
    }

    /**
     * @param Node|NULL $node
     * @return bool|int
     */
    protected function getNodeAttackDifficulty(Node $node = NULL)
    {
        $result = false;
        if ($node) {
            switch ($node->getType()) {
                default:
                    break;
                case Node::ID_PUBLICIO:
                case Node::ID_IO:
                    $result = $node->getLevel() * FileService::DEFAULT_DIFFICULTY_MOD;
                    break;
            }
        }
        return $result;
    }

    /**
     * Used to check if a certain file-type can be executed in a node.
     * @param File $file
     * @param Node $node
     * @return bool
     */
    protected function canExecuteInNodeType(File $file, Node $node)
    {
        $result = false;
        $validNodeTypes = [];
        switch ($file->getFileType()->getId()) {
            default:
                $result = true;
                break;
            case FileType::ID_COINMINER:
                $validNodeTypes[] = Node::ID_TERMINAL;
                break;
            case FileType::ID_DATAMINER:
                $validNodeTypes[] = Node::ID_DATABASE;
                break;
            case FileType::ID_ICMP_BLOCKER:
                $validNodeTypes[] = Node::ID_IO;
                break;
            case FileType::ID_JACKHAMMER:
            case FileType::ID_PORTSCANNER:
            case FileType::ID_WORMER:
                $validNodeTypes[] = Node::ID_IO;
                $validNodeTypes[] = Node::ID_PUBLICIO;
                break;
        }
        // if result is false, check if the node type matches an entry of the valid-node-types array
        return (!$result) ? in_array($node->getType(), $validNodeTypes) : $result;
    }

    protected function canStartActionInNodeType()
    {

    }

}
