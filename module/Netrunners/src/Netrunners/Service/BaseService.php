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
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\GameOptionInstance;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Entity\SystemLog;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FilePartSkillRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeSkillRepository;
use Netrunners\Repository\KnownNodeRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\ProfileFactionRatingRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SystemRepository;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Mvc\I18n\Translator;
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
     * @var Translator
     */
    protected $translator;

    /**
     * @var string
     */
    protected $profileLocale = 'en_US';

    /**
     * @var object
     */
    protected $clientData;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var array|false
     */
    protected $response = false;


    /**
     * BaseService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     * @param $translator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator
    )
    {
        $this->entityManager = $entityManager;
        $this->viewRenderer = $viewRenderer;
        $this->translator = $translator;
    }

    /**
     * @param $string
     * @return string
     */
    protected function translate($string)
    {
        $this->translator->getTranslator()->setLocale($this->profileLocale);
        return $this->translator->translate($string);
    }

    /**
     * @return WebsocketService
     */
    protected function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * @param $resourceId
     */
    protected function initService($resourceId)
    {
        $this->setClientData($resourceId);
        $this->setUser();
        $this->setProfileLocale();
        $this->setResponse(false);
    }

    /**
     * @param $resourceId
     */
    private function setClientData($resourceId)
    {
        $this->clientData = $this->getWebsocketServer()->getClientData($resourceId);
    }

    /**
     * Sets the user from the client data.
     * @param User|NULL $user
     */
    protected function setUser(User $user = NULL)
    {
        $this->user = ($user) ? $user : $this->entityManager->find('TmoAuth\Entity\User', $this->clientData->userId);
    }

    private function setProfileLocale()
    {
        if ($this->user && $this->user->getProfile()) $this->profileLocale = $this->user->getProfile()->getLocale();
    }

    /**
     * @param $response
     */
    protected function setResponse($response)
    {
        $this->response = $response;
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
        $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_MEMORY);
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
        $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_STORAGE);
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
            $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_MEMORY);
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
            $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_STORAGE);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $total += $node->getLevel() * SystemService::BASE_STORAGE_VALUE;
            }
        }
        return $total;
    }

    /**
     * @param Profile|NpcInstance $detector
     * @param Profile|NpcInstance $stealther
     * @return bool
     */
    protected function canSee($detector, $stealther)
    {
        $canSee = true;
        if ($stealther->getStealthing()) {
            $detectorSkillRating = $this->getSkillRating($detector, SKill::ID_DETECTION);
            $stealtherSkillRating = $this->getSkillRating($stealther, SKill::ID_STEALTH);
            $chance = 50 + $detectorSkillRating - $stealtherSkillRating;
            if (mt_rand(1, 100) > $chance) $canSee = false;
            // check for skill gain
            if ($canSee) {
                $this->learnFromSuccess($detector, ['skills' => ['detection']], -50);
            }
            else {
                $this->learnFromSuccess($stealther, ['skills' => ['stealth']], -50);
            }
        }
        return $canSee;
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
     * @param int $modifier
     * @return bool
     */
    protected function learnFromSuccess(Profile $profile, $jobData, $modifier = 0)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $this->reverseSkillNameModification($skillName)
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill->getId());
            $chance = 100 - $skillRating + $modifier;
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
     * Players can learn from failure, but not a lot.
     * @param Profile $profile
     * @param $jobData
     * @param int $modifier
     * @return bool
     */
    protected function learnFromFailure(Profile $profile, $jobData, $modifier = 0)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $this->reverseSkillNameModification($skillName)
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill->getId());
            if ($skillRating >= SkillRating::MAX_SKILL_RATING_FAIL_LEARN) continue;
            $chance = 100 - $skillRating + $modifier;
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
     * @param $parameter
     * @param Node $currentNode
     * @return bool|Connection
     */
    protected function findConnectionByNameOrNumber($parameter, Node $currentNode)
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $connectionRepo->findBySourceNode($currentNode);
        $connection = false;
        if ($searchByNumber) {
            if (isset($connections[$parameter - 1])) {
                $connection = $connections[$parameter - 1];
            }
        } else {
            foreach ($connections as $pconnection) {
                /** @var Connection $pconnection */
                if ($pconnection->getTargetNode()->getName() == $parameter) {
                    $connection = $pconnection;
                    break;
                }
            }
        }
        return $connection;
    }

    /**
     * @param $skillName
     * @return mixed
     */
    protected function reverseSkillNameModification($skillName)
    {
        return str_replace('-', ' ', $skillName);
    }

    /**
     * @param Profile|NpcInstance $profile
     * @param int $skillId
     * @return int
     */
    protected function getSkillRating(Profile $profile, $skillId)
    {
        $skill = $this->entityManager->find('Netrunners\Entity\Skill', $skillId);
        /** @var Skill $skill */
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
            $row->setType($node->getNodeType()->getId());
            $row->setCreated(new \DateTime());
        }
        else {
            $row = new KnownNode();
            $row->setCreated(new \DateTime());
            $row->setProfile($profile);
            $row->setNode($node);
            $row->setType($node->getNodeType()->getId());
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
     * @param Profile|NULL $profile
     */
    public function messageEveryoneInNode(Node $node, $message, Profile $profile = NULL)
    {
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
        $wsClients = $this->getWebsocketServer()->getClients();
        $wsClientsData = $this->getWebsocketServer()->getClientsData();
        $profiles = $profileRepo->findByCurrentNode($node, $profile);
        foreach ($profiles as $xprofile) {
            /** @var Profile $xprofile */
            if ($xprofile === $profile) continue;
            foreach ($wsClients as $wsClient) {
                if (
                    isset($wsClientsData[$wsClient->resourceId]) &&
                    $wsClientsData[$wsClient->resourceId]['profileId'] == $xprofile->getId()
                ) {
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
    protected function getNextParameter(
        $contentArray = [],
        $returnContent = true,
        $castToInt = false,
        $implode = false,
        $makeSafe = false,
        $safeOptions = ['safe'=>1,'elements'=>'strong']
    )
    {
        $parameter = NULL;
        $nextParameter = (!$implode) ? array_shift($contentArray) : implode(' ', $contentArray);
        if ($nextParameter !== NULL) {
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
        $skillCoding = $this->getSkillRating($profile, Skill::ID_CODING);
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
     * @param int|NULL $resourceId
     * @param Profile $profile
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return array|bool
     */
    protected function movePlayerToTargetNode(
        $resourceId = NULL,
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
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has used the connection to %s</pre>'),
            $profile->getUser()->getUsername(),
            $targetNode->getName()
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($sourceNode, $message, $profile);
        $profile->setCurrentNode($targetNode);
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has connected to this node from %s</pre>'),
            $profile->getUser()->getUsername(),
            $sourceNode->getName()
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($targetNode, $message, $profile);
        $this->entityManager->flush($profile);
        return ($resourceId) ? $this->getWebsocketServer()->getNodeService()->showNodeInfo($resourceId) : false;
    }

    /**
     * @param NpcInstance $npc
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return bool
     */
    protected function moveNpcToTargetNode(
        NpcInstance $npc,
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
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has used the connection to %s</pre>'),
            $npc->getName(),
            $targetNode->getName()
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($sourceNode, $message);
        $npc->setNode($targetNode);
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has connected to this node from %s</pre>'),
            $npc->getName(),
            $sourceNode->getName()
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($targetNode, $message);
        $this->entityManager->flush($npc);
        return true;
    }

    /**
     * Checks if the player is blocked from performing another action.
     * Returns true if the action is blocked, false if it is not blocked.
     * @param $resourceId
     * @param bool $checkForFullBlock
     * @return array|bool
     */
    protected function isActionBlocked($resourceId, $checkForFullBlock = false)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $isBlocked = false;
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
            $this->translate('You are currently busy with something else')
        );
        /* combat block check follows - combat never fully blocks */
        if (!$checkForFullBlock) {
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            $isBlocked = $this->isInCombat($user->getProfile());
            if ($isBlocked) {
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You are currently busy fighting')
                );
            }
        }
        /* action block check follows */
        if (!empty($clientData->action) && !$isBlocked) {
            $actionData = (object)$clientData->action;
            $isBlocked = false;
            if ($checkForFullBlock) {
                if ($actionData->fullblock) $isBlocked = true;
            }
            if (!$isBlocked) {
                if ($actionData->blocking) $isBlocked = true;
            }
        }
        if ($isBlocked) {
            $isBlocked = [
                'command' => 'showmessage',
                'message' => $message
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
            switch ($node->getNodeType()->getId()) {
                default:
                    break;
                case NodeType::ID_PUBLICIO:
                case NodeType::ID_IO:
                    $result = $node->getLevel() * FileService::DEFAULT_DIFFICULTY_MOD;
                    break;
            }
        }
        return $result;
    }

    /**
     * @param $resourceId
     * @param $element
     * @param $value
     * @return bool
     */
    protected function updateInterfaceElement($resourceId, $element, $value)
    {
        $wsClient = NULL;
        foreach ($this->getWebsocketServer()->getClients() as $xClientId => $xClient) {
            if ($xClient->resourceId == $resourceId) {
                $wsClient = $xClient;
                break;
            }
        }
        if ($wsClient) {
            $response = [
                'command' => 'updateinterfaceelement',
                'message' => [
                    'element' => $element,
                    'value' => $value
                ]
            ];
            $wsClient->send(json_encode($response));
        }
        return true;
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
                $validNodeTypes[] = NodeType::ID_TERMINAL;
                break;
            case FileType::ID_DATAMINER:
                $validNodeTypes[] = NodeType::ID_DATABASE;
                break;
            case FileType::ID_ICMP_BLOCKER:
                $validNodeTypes[] = NodeType::ID_IO;
                break;
            case FileType::ID_JACKHAMMER:
            case FileType::ID_PORTSCANNER:
            case FileType::ID_WORMER:
                $validNodeTypes[] = NodeType::ID_IO;
                $validNodeTypes[] = NodeType::ID_PUBLICIO;
                break;
        }
        // if result is false, check if the node type matches an entry of the valid-node-types array
        return (!$result) ? in_array($node->getNodeType()->getId(), $validNodeTypes) : $result;
    }

    /**
     * @param Profile $profile
     * @param string $subject
     * @param string $severity
     * @return bool
     */
    protected function storeNotification(Profile $profile, $subject = 'INVALID', $severity = 'danger')
    {
        $notification = new Notification();
        $notification->setProfile($profile);
        $notification->setSentDateTime(new \DateTime());
        $notification->setSubject($subject);
        $notification->setSeverity($severity);
        $this->entityManager->persist($notification);
        $this->entityManager->flush($notification);
        return true;
    }

    /**
     * @param Profile $profile
     * @param MilkrunInstance|NULL $milkrunInstance
     * @param Profile|NULL $rater
     * @param int $source
     * @param int $sourceRating
     * @param int $targetRating
     * @param null $sourceFaction
     * @param null $targetFaction
     * @return bool
     */
    protected function createProfileFactionRating(
        Profile $profile,
        MilkrunInstance $milkrunInstance = NULL,
        Profile $rater = NULL,
        $source = 0,
        $sourceRating = 0,
        $targetRating = 0,
        $sourceFaction = NULL,
        $targetFaction = NULL
    )
    {
        $pfr = new ProfileFactionRating();
        $pfr->setProfile($profile);
        $pfr->setAdded(new \DateTime());
        $pfr->setMilkrunInstance($milkrunInstance);
        $pfr->setRater($rater);
        $pfr->setSource($source);
        $pfr->setSourceRating($sourceRating);
        $pfr->setTargetRating($targetRating);
        $pfr->setSourceFaction($sourceFaction);
        $pfr->setTargetFaction($targetFaction);
        $this->entityManager->persist($pfr);
        $this->entityManager->flush($pfr);
        return true;
    }

    /**
     * Returns the total rating for the given profile and faction.
     * @param Profile $profile
     * @param Faction $faction
     * @return mixed
     */
    protected function getProfileFactionRating(Profile $profile, Faction $faction)
    {
        $profileFactionRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFactionRating');
        /** @var ProfileFactionRatingRepository $profileFactionRatingRepo */
        return $profileFactionRatingRepo->getProfileFactionRating($profile, $faction);
    }

    protected function canStartActionInNodeType()
    {

    }

    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int)
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int $length      How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     */
    public function getRandomString($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    /**
     * @param System $system
     * @param int $amount
     */
    public function raiseSystemAlertLevel(System $system, $amount = 0)
    {
        $currentLevel = $system->getAlertLevel();
        $system->setAlertLevel($currentLevel + $amount);
        $this->entityManager->flush($system);
    }

    /**
     * @param Profile $profile
     * @param int $amount
     */
    public function raiseProfileSecurityRating(Profile $profile, $amount = 0)
    {
        $currentRating = $profile->getSecurityRating();
        $profile->setSecurityRating($currentRating + $amount);
        $this->entityManager->flush($profile);
    }

    /**
     * @param System $system
     * @param string $subject
     * @param string $severity
     * @param null $details
     * @param File|NULL $file
     * @param Node|NULL $node
     * @param Profile|NULL $profile
     */
    public function writeSystemLogEntry(
        System $system,
        $subject = '',
        $severity = 'info',
        $details = NULL,
        File $file = NULL,
        Node $node = NULL,
        Profile $profile = NULL
    )
    {
        $log = new SystemLog();
        $log->setAdded(new \DateTime());
        $log->setSystem($system);
        $log->setSubject($subject);
        $log->setSeverity($severity);
        $log->setDetails($details);
        $log->setFile($file);
        $log->setNode($node);
        $log->setProfile($profile);
        $this->entityManager->persist($log);
        $this->entityManager->flush($log);
    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     * @return mixed
     */
    protected function getProfileGameOption(Profile $profile, $gameOptionId)
    {
        $gameOption = $this->entityManager->find('Netrunners\Entity\GameOption', $gameOptionId);
        $goiRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOptionInstance');
        $gameOptionInstance = $goiRepo->findOneBy([
            'gameOption' => $gameOption,
            'profile' => $profile
        ]);
        return ($gameOptionInstance) ? $gameOptionInstance->getStatus() : $gameOption->getDefaultStatus();
    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     */
    protected function toggleProfileGameOption(Profile $profile, $gameOptionId)
    {
        $gameOption = $this->entityManager->find('Netrunners\Entity\GameOption', $gameOptionId);
        if ($profile && $gameOption) {
            $goiRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOptionInstance');
            $gameOptionInstance = $goiRepo->findOneBy([
                'gameOption' => $gameOption,
                'profile' => $profile
            ]);
            if ($gameOptionInstance) {
                /** @var GameOptionInstance $gameOptionInstance */
                $currentStatus = $gameOptionInstance->getStatus();
            }
            else {
                $currentStatus = $gameOption->getDefaultStatus();
            }
            $newStatus = ($currentStatus) ? false : true;
            if ($gameOptionInstance) {
                $gameOptionInstance->setStatus($newStatus);
            }
            else {
                $gameOptionInstance = new GameOptionInstance();
                $gameOptionInstance->setStatus($newStatus);
                $gameOptionInstance->setProfile($profile);
                $gameOptionInstance->setGameOption($gameOption);
                $gameOptionInstance->setChanged(new \DateTime());
                $this->entityManager->persist($gameOptionInstance);
            }
            $this->entityManager->flush($gameOptionInstance);
        }
    }

    /**
     * @return bool
     */
    protected function isSuperAdmin()
    {
        $isAdmin = false;
        foreach ($this->user->getRoles() as $role) {
            /** @var Role $role */
            if ($role->getRoleId() === Role::ROLE_ID_SUPERADMIN) {
                $isAdmin = true;
                break;
            }
        }
        return $isAdmin;
    }

    /**
     * @return bool
     */
    protected function isAdmin()
    {

        $isAdmin = false;
        foreach ($this->user->getRoles() as $role) {
            /** @var Role $role */
            if ($role->getRoleId() === Role::ROLE_ID_ADMIN || $role->getRoleId() === Role::ROLE_ID_SUPERADMIN) {
                $isAdmin = true;
                break;
            }
        }
        return $isAdmin;
    }

    /**
     * @param NpcInstance|Profile $combatant
     * @return bool
     */
    protected function isInCombat($combatant)
    {
        $inCombat = false;
        $combatantData = (object)$this->getWebsocketServer()->getCombatants();
        if ($combatant instanceof Profile) {
            if (array_key_exists($combatant->getId(), $combatantData->profiles)) $inCombat = true;
        }
        if ($combatant instanceof NpcInstance) {
            if (array_key_exists($combatant->getId(), $combatantData->npcs)) $inCombat = true;
        }
        return $inCombat;
    }

}
