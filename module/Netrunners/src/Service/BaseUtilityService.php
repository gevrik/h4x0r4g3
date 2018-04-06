<?php

/**
 * BaseUtility Service.
 * The service supplies basic utlity methods.
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
use Netrunners\Entity\FileModInstance;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\GameOptionInstance;
use Netrunners\Entity\Group;
use Netrunners\Entity\GroupRoleInstance;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\MailMessage;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Mission;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileEffect;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Entity\ServerSetting;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FilePartSkillRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeSkillRepository;
use Netrunners\Repository\GroupRoleInstanceRepository;
use Netrunners\Repository\KnownNodeRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\ProfileEffectRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SystemRepository;

class BaseUtilityService {

    const SETTING_MOTD = 'motd';
    const SETTING_CHATSUBO_SYSTEM_ID = 'csid';
    const SETTING_CHATSUBO_NODE_ID = 'cnid';
    const SETTING_WILDERNESS_SYSTEM_ID = 'wsid';
    const SETTING_WILDERNESS_NODE_ID = 'wnid';
    const VALUE_TYPE_CODINGNODELEVELS = 'codingnodelevels';
    const VALUE_TYPE_MEMORYLEVELS = 'memorylevels';
    const VALUE_TYPE_STORAGELEVELS = 'storagelevels';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * BaseUtilityService constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(
        EntityManager $entityManager
    )
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return WebsocketService
     */
    protected function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * @param Profile $member
     * @param Group $group
     * @param array $allowedRoles
     * @return bool
     */
    protected function memberRoleIsAllowed(Profile $member, Group $group = null, $allowedRoles = [])
    {
        if (!$member->getGroup()) return false;
        if (!$group) $group = $member->getGroup();
        /** @var GroupRoleInstanceRepository $griRepo */
        $griRepo = $this->entityManager->getRepository('Netrunners\Entity\GroupRoleInstance');
        $roles = $griRepo->findBy([
            'member' => $member,
            'group' => $member->getGroup()
        ]);
        /** @var GroupRoleInstance $role */
        foreach ($roles as $role) {
            if (in_array($role->getId(), $allowedRoles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Profile $profile
     * @return null|object
     */
    protected function getWsClientByProfile(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        foreach ($ws->getClients() as $wsClientId => $wsClient) {
            $wsClientData = $ws->getClientData($wsClient->resourceId);
            if ($wsClientData->profileId == $profile->getId()) {
                return $wsClient;
            }
        }
        return NULL;
    }

    /**
     * @param object|null $entity
     * @return null|\ReflectionClass
     * @throws \ReflectionException
     */
    protected function getReflectionClass($entity = null)
    {
        if ($entity) {
            return new \ReflectionClass(get_class($entity));
        }
        return null;
    }

    /**
     * Generate a random string.
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
            $str .= $keyspace[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     * @param string $locale
     * @param int $value
     * @param int $min
     * @param int $max
     * @return string
     */
    public function getNumberFormat($locale = 'en-US', $value = 0, $min = 0, $max = 0)
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $min);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $max);
        return $formatter->format($value);
    }

    /**
     * @param int $target
     * @param bool $returnMarginOfSuccess
     * @return bool|int
     */
    protected function makePercentRollAgainstTarget($target, $returnMarginOfSuccess = false)
    {
        $roll = mt_rand(1, 100);
        $result = false;
        if ($roll <= $target) {
            if ($returnMarginOfSuccess) {
                $result = $target - $roll;
            }
            else {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * @param System $system
     * @param int $amount
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function raiseSystemAlertLevel(System $system, $amount = 0)
    {
        $currentLevel = $system->getAlertLevel();
        $system->setAlertLevel($currentLevel + $amount);
        $this->entityManager->flush($system);
    }

    /**
     * @param $nodeTypeId
     * @return array
     */
    protected function generateNodeDataByType($nodeTypeId)
    {
        $result = [];
        switch ($nodeTypeId) {
            default:
                break;
            case NodeType::ID_FIREWALL:
                $result['roaming'] = 1;
                $result['aggressive'] = 1;
                $result['codegates'] = 0;
                break;
            case NodeType::ID_TERMINAL:
            case NodeType::ID_DATABASE:
                $result['roaming'] = 1;
                $result['aggressive'] = 0;
                $result['codegates'] = 0;
                break;
            case NodeType::ID_CPU:
                $result['roaming'] = 1;
                $result['aggressive'] = 0;
                $result['codegates'] = 1;
                break;
        }
        return $result;
    }

    /**
     * @param Node $node
     * @param bool $assoc
     * @return mixed|string
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function getNodeData(Node $node, $assoc = false)
    {
        $nodeData = json_decode($node->getData(), $assoc);
        if (($assoc) ? !is_array($nodeData) : !is_object($nodeData)) {
            $nodeData = json_encode($this->generateNodeDataByType($node->getNodeType()->getId()));
            $node->setData($nodeData);
            $this->entityManager->flush($node);
            $nodeData = json_decode($nodeData, $assoc);
        }
        return $nodeData;
    }

    /**
     * @param Npc $npc
     * @param Node $node
     * @param Profile|NULL $profile
     * @param Faction|NULL $faction
     * @param Group|NULL $group
     * @param Node|NULL $homeNode
     * @param null $baseLevel
     * @param bool $flush
     * @return NpcInstance
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function spawnNpcInstance(
        Npc $npc,
        Node $node,
        Profile $profile = NULL,
        Faction $faction = NULL,
        Group $group = NULL,
        Node $homeNode = NULL,
        $baseLevel = NULL,
        $flush = false
    )
    {
        // check if a base level was given or use the node level as the base level
        if (!$baseLevel) {
            $baseLevel = $node->getLevel();
        }
        // determine base values depending on npc type
        switch ($npc->getId()) {
            default:
                $credits = 0;
                $snippets = 0;
                $maxEeg = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                break;
            case Npc::ID_KILLER_VIRUS:
            case Npc::ID_MURPHY_VIRUS:
                $credits = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                $snippets = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                $maxEeg = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                break;
        }
        // check if this is a home-node npc and check for node-data
        $roaming = false;
        $aggressive = false;
        $codegates = false;
        if ($homeNode) {
            $nodeData = $this->getNodeData($homeNode);
            if (isset($nodeData->roaming)) $roaming = $nodeData->roaming;
            if (isset($nodeData->aggressive)) $aggressive = $nodeData->aggressive;
            if (isset($nodeData->codegates)) $codegates = $nodeData->codegates;
        }
        // sanity checks for generated values
        if ($maxEeg < 1) $maxEeg = 1;
        // spawn
        $npcInstance = new NpcInstance();
        $npcInstance->setNpc($npc);
        $npcInstance->setAdded(new \DateTime());
        $npcInstance->setProfile($profile);
        $npcInstance->setNode($node);
        $npcInstance->setCredits($npc->getBaseCredits() + $credits);
        $npcInstance->setSnippets($npc->getBaseSnippets() + $snippets);
        $npcInstance->setAggressive(($aggressive) ? $aggressive : $npc->getAggressive());
        $npcInstance->setMaxEeg($npc->getBaseEeg() + $maxEeg);
        $npcInstance->setCurrentEeg($npc->getBaseEeg() + $maxEeg);
        $npcInstance->setDescription($npc->getDescription());
        $npcInstance->setName($npc->getName());
        $npcInstance->setFaction($faction);
        $npcInstance->setHomeNode($homeNode);
        $npcInstance->setRoaming(($roaming) ? $roaming : $npc->getRoaming());
        $npcInstance->setBypassCodegates($codegates);
        $npcInstance->setGroup($group);
        $npcInstance->setLevel($npc->getLevel() + $baseLevel);
        $npcInstance->setSlots($npc->getBaseSlots());
        $npcInstance->setStealthing($npc->getStealthing());
        $npcInstance->setSystem($node->getSystem());
        $npcInstance->setHomeSystem($node->getSystem());
        $this->entityManager->persist($npcInstance);
        /* add skills */
        $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
        foreach ($skills as $skill) {
            /** @var Skill $skill */
            $rating = 0;
            switch ($skill->getId()) {
                default:
                    continue;
                case Skill::ID_STEALTH:
                    $rating = $npc->getBaseStealth() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_DETECTION:
                    $rating = $npc->getBaseDetection() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_BLADES:
                    $rating = $npc->getBaseBlade() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_BLASTERS:
                    $rating = $npc->getBaseBlaster() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_SHIELDS:
                    $rating = $npc->getBaseShield() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_FRAY:
                    $rating = $npc->getBaseFray() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
            }
            $skillRating = new SkillRating();
            $skillRating->setNpc($npcInstance);
            $skillRating->setProfile(NULL);
            $skillRating->setSkill($skill);
            $skillRating->setRating($rating);
            $this->entityManager->persist($skillRating);
            $npcInstance->addSkillRating($skillRating);
        }
        // add files
        switch ($npc->getId()) {
            default:
                break;
            case Npc::ID_WILDERSPACE_INTRUDER:
                $dropChance = $npcInstance->getLevel();
                if ($this->makePercentRollAgainstTarget($dropChance)) {
                    /** @var FileType $fileType */
                    $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_WILDERSPACE_HUB_PORTAL);
                    $file = $this->createFile(
                        $fileType,
                        false,
                        $fileType->getName(),
                        $dropChance,
                        $dropChance*10,
                        false,
                        $dropChance*10,
                        null,
                        null,
                        null,
                        null,
                        null,
                        $npcInstance,
                        null,
                        null,
                        null
                    );
                    $npcInstance->addFile($file);
                }
                break;
            case Npc::ID_NETWATCH_INVESTIGATOR:
            case Npc::ID_NETWATCH_AGENT:
                /** @var FileType $fileType */
                $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_CODEBLADE);
                $file = $this->createFile(
                    $fileType,
                    false,
                    $fileType->getName(),
                    $baseLevel*10,
                    $baseLevel*10,
                    true,
                    $baseLevel*10,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $npcInstance,
                    null,
                    null,
                    null
                );
                $npcInstance->setBladeModule($file);
                $npcInstance->addFile($file);
                break;
        }
        if ($flush) {
            $this->entityManager->flush();
        }
        return $npcInstance;
    }

    /**
     * @param FileType $fileType
     * @param bool $flush
     * @param string|null $name
     * @param int $level
     * @param int $integrity
     * @param bool $running
     * @param int $maxIntegrity
     * @param Profile|null $coder
     * @param string|null $content
     * @param string|null $data
     * @param MailMessage|null $mailMessage
     * @param Node|null $node
     * @param NpcInstance|null $npc
     * @param Profile|null $profile
     * @param System|null $system
     * @param int|null $slots
     * @param int $version
     * @return File
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function createFile(
        FileType $fileType,
        $flush = false,
        $name = null,
        $level = 1,
        $integrity = 100,
        $running = false,
        $maxIntegrity = 100,
        Profile $coder = null,
        $content = null,
        $data = null,
        MailMessage $mailMessage = null,
        Node $node = null,
        NpcInstance $npc = null,
        Profile $profile = null,
        System $system = null,
        $slots = null,
        $version = 1
    )
    {
        if (!$name) $name = $fileType->getName();
        if (!$slots) $slots = $fileType->getSize();
        $file = new File();
        $file->setIntegrity($integrity);
        $file->setCoder($coder);
        $file->setContent($content);
        $file->setCreated(new \DateTime());
        $file->setData($data);
        $file->setExecutable($fileType->getExecutable());
        $file->setFileType($fileType);
        $file->setLevel($level);
        $file->setMailMessage($mailMessage);
        $file->setMaxIntegrity($maxIntegrity);
        $file->setModified(NULL);
        $file->setName($name);
        $file->setNode($node);
        $file->setNpc($npc);
        $file->setProfile($profile);
        $file->setRunning($running);
        $file->setSize($fileType->getSize());
        $file->setSlots($slots);
        $file->setSystem($system);
        $file->setVersion($version);
        $this->entityManager->persist($file);
        if ($flush) $this->entityManager->flush($file);
        return $file;
    }

    /**
     * Checks if the given profile can execute the given file.
     * Returns true if the file can be executed.
     * @param Profile $profile
     * @param File $file
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
     * Get the given profile's total memory.
     * This is calculated from all systems that the profile owns.
     * @param Profile $profile
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
     * @param $fileTypeId
     * @return array
     */
    protected function generateFileDataByType($fileTypeId)
    {
        $result = [];
        switch ($fileTypeId) {
            default:
                break;
            case FileType::ID_GUARD_SPAWNER:
                $result['roaming'] = 0;
                $result['aggressive'] = 1;
                $result['codegates'] = 0;
                $result['npcid'] = 0;
                break;
            case FileType::ID_SPIDER_SPAWNER:
                $result['roaming'] = 0;
                $result['aggressive'] = 0;
                $result['codegates'] = 0;
                $result['npcid'] = 0;
                break;
            case FileType::ID_DATAMINER:
            case FileType::ID_COINMINER:
                $result['value'] = 0;
                break;
            case FileType::ID_RESEARCHER:
                $result['progress'] = 0;
                $result['type'] = '';
                $result['id'] = 0;
                break;
        }
        return $result;
    }

    /**
     * @param File $file
     * @param bool $assoc
     * @return mixed|string
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function getFileData(File $file, $assoc = false)
    {
        $fileData = json_decode($file->getData(), $assoc);
        if (($assoc) ? !is_array($fileData) : !is_object($fileData)) {
            $fileData = json_encode($this->generateFileDataByType($file->getFileType()->getId()));
            $file->setData($fileData);
            $this->entityManager->flush($file);
            $fileData= json_decode($fileData, $assoc);
        }
        return $fileData;
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
     * Get the given profile's total storage.
     * This is calculated from all systems that the profile owns.
     * @param Profile $profile
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function getTotalStorage(Profile $profile)
    {
        /** @var SystemRepository $systemRepo */
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var NodeRepository $nodeRepo */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
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
     * @param System $system
     * @return float|int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function getCurrentNodeMaximumForSystem(System $system)
    {
        /** @var NodeRepository $nodeRepo */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        $cpus = $nodeRepo->getTotalCpuLevels($system);
        $maxNodes = $cpus * NodeService::MAX_NODES_MULTIPLIER;
        return $maxNodes;
    }

    /**
     * Checks if the given profile can store the given file.
     * Returns true if the file can be stored.
     * @param Profile $profile
     * @param File $file
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function canStoreFileOfSize(Profile $profile, $size = 0)
    {
        return ($size + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? true : false;
    }

    /**
     * Get the given system's memory value.
     * @param System $system
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
     * @param Profile $profile
     * @param $element
     * @param $content
     * @param array $adds
     * @param bool $sendNow
     * @return bool|GameClientResponse
     * @throws \Exception
     */
    protected function updateDivHtml(Profile $profile, $element, $content, $adds = [], $sendNow = false)
    {
        if (!$profile->getCurrentResourceId()) return false;
        $response = new GameClientResponse($profile->getCurrentResourceId());
        $response->setCommand(GameClientResponse::COMMAND_UPDATEDIVHTML);
        $response->addOption(GameClientResponse::OPT_ELEMENT, $element);
        $response->addOption(GameClientResponse::OPT_CONTENT, (string)$content);
        if (!empty($adds)) {
            foreach ($adds as $key => $value) {
                $response->addOption($key, $value);
            }
        }
        if ($sendNow) {
            return $response->send();
        }
        else {
            return $response;
        }
    }

    /**
     * Get the given system's storage value.
     * @param System $system
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
     * @param Profile $profile
     * @param Node $node
     * @throws \Doctrine\ORM\OptimisticLockException
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
     * @param Profile|NpcInstance $profile
     * @param int $skillId
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function getSkillRating($profile, $skillId)
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
     * @param File $file
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function destroyFile(File $file)
    {
        /** @var FileModInstanceRepository $fileModInstanceRepo */
        $fileModInstanceRepo = $this->entityManager->getRepository(FileModInstance::class);
        $fileMods = $fileModInstanceRepo->findByFile($file);
        foreach ($fileMods as $fileMod) {
            $this->entityManager->remove($fileMod);
        }
        $this->entityManager->remove($file);
        $this->entityManager->flush();
    }

    /**
     * @param Profile $profile
     * @param $codeOptions
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
        if ($codeOptions->mode == 'mod') {
            $skillModifier = $this->getSkillRating($profile, Skill::ID_ADVANCED_CODING);
        }
        $skillCoding = $this->getSkillRating($profile, Skill::ID_CODING);
        $skillRating = floor(($skillCoding + $skillModifier)/2);
        $chance = $skillRating - $difficulty;
        return (int)$chance;
    }

    /**
     * @param Profile $profile
     * @param string $mode
     * @param int $difficulty
     * @param int $fileType
     * @return int
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function calculateCodingSuccessChanceNew(Profile $profile, $mode, $difficulty, $fileType)
    {
        $skillModifier = 0;
        if ($mode == 'program') {
            $targetType = $this->entityManager->find('Netrunners\Entity\FileType', $fileType);
            /** @var FileType $targetType */
            $skillModifier = $this->getSkillModifierForFileType($targetType, $profile);
        }
        if ($mode == 'resource') {
            $targetType = $this->entityManager->find('Netrunners\Entity\FilePart', $fileType);
            /** @var FilePart $targetType */
            $skillModifier = $this->getSkillModifierForFilePart($targetType, $profile);
        }
        if ($mode == 'mod') {
            $skillModifier = $this->getSkillRating($profile, Skill::ID_ADVANCED_CODING);
        }
        $skillCoding = $this->getSkillRating($profile, Skill::ID_CODING);
        $skillRating = floor(($skillCoding + $skillModifier)/2);
        $chance = $skillRating - $difficulty;
        return (int)$chance;
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
     * @param int $partyId
     * @param $message
     * @param string $textClass
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function messageEveryoneInParty(int $partyId, $message, $textClass = GameClientResponse::CLASS_MUTED)
    {
        $party = $this->getWebsocketServer()->getParty($partyId);
        if ($party) {
            $response = new GameClientResponse(NULL, GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
            $response->addMessage($message, $textClass);
            foreach ($party['members'] as $memberProfileId => $memberData) {
                /** @var Profile $memberProfile */
                $memberProfile = $this->entityManager->find('Netrunners\Entity\Profile', $memberProfileId);
                if ($memberProfile) {
                    $memberResourceId = $memberProfile->getCurrentResourceId();
                    if (!$memberResourceId) continue;
                    $response->setResourceId($memberResourceId)->send();
                }
            }
        }
    }

    /**
     * @param Profile $profile
     * @param Node|NULL $node
     * @return bool
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function checkKnownNode(Profile $profile, Node $node = NULL)
    {
        $currentNode = ($node) ? $node : $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        if ($profile === $currentSystem->getProfile()) return true;
        if ($profile->getFaction() && $profile->getFaction() === $currentSystem->getFaction()) return true;
        if ($profile->getGroup() && $profile->getGroup() === $currentSystem->getGroup()) return true;
        $currentNodeType = $currentNode->getNodeType();
        $knRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
        /** @var KnownNodeRepository $knRepo */
        $knownNode = $knRepo->findByProfileAndNode($profile, $currentNode);
        /** @var KnownNode $knownNode */
        if ($knownNode) {
            if ($currentNodeType->getId() != $knownNode->getType()) {
                $knownNode->setType($currentNodeType->getId());
                $this->entityManager->flush($knownNode);
            }
        }
        else {
            $knownNode = new KnownNode();
            $knownNode->setType($currentNodeType->getId());
            $knownNode->setProfile($profile);
            $knownNode->setCreated(new \DateTime());
            $knownNode->setNode($currentNode);
            $this->entityManager->persist($knownNode);
            $this->entityManager->flush($knownNode);
        }
        return true;
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

    /**
     * @param $resourceId
     * @return bool
     */
    protected function isInAction($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        return (empty($clientData->action)) ? false : true;
    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     * @return mixed
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
     * @param System $currentSytem
     * @return bool
     */
    protected function canAccess(Profile $profile, System $currentSytem)
    {
        $systemProfile = $currentSytem->getProfile();
        $systemGroup = $currentSytem->getGroup();
        $systemFaction = $currentSytem->getFaction();
        $canAccess = true;
        if ($systemProfile && $systemProfile !== $profile) $canAccess = false;
        if ($systemFaction && $systemFaction !== $profile->getFaction()) $canAccess = false;
        if ($systemGroup && $systemGroup !== $profile->getGroup()) $canAccess = false;
        return $canAccess;
    }

    /**
     * @param $subject
     * @param $effectId
     * @return bool
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function isUnderEffect($subject, $effectId)
    {
        $result = false;
        $peRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileEffect');
        /** @var ProfileEffectRepository $peRepo */
        $effectInstance = NULL;
        if ($subject instanceof Profile) {
            $effectInstance = $peRepo->findOneByProfileAndEffect($subject, $effectId);
        }
        if ($subject instanceof NpcInstance) {
            $effectInstance = $peRepo->findOneByNpcAndEffect($subject, $effectId);
        }
        if ($effectInstance) {
            /** @var ProfileEffect $effectInstance */
            $now = new \DateTime();
            if ($effectInstance->getExpires() > $now) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * Calculate the attack difficulty for the given node.
     * If a file is given, we will check for additional modifiers.
     * @param Node|NULL $node
     * @param File|NULL $file
     * @return bool|int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function getNodeAttackDifficulty(Node $node = NULL, File $file = NULL)
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
            if ($result && $file) {
                switch ($file->getFileType()->getId()) {
                    default:
                        break;
                    case FileType::ID_PORTSCANNER:
                    case FileType::ID_JACKHAMMER:
                    case FileType::ID_WORMER:
                        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                        /** @var FileRepository $fileRepo */
                        $icmpBlockerLevels = $fileRepo->getTotalRunningLevelInNodeByType($node, FileType::ID_ICMP_BLOCKER);
                        $result += $icmpBlockerLevels;
                        break;
                }
            }
        }
        return $result;
    }

    /**
     * @param File $targetFile
     * @param File|NULL $attackingFile
     * @return bool|int
     */
    protected function getFileAttackDifficulty(File $targetFile, File $attackingFile = NULL)
    {
        $result = false;
        if ($targetFile) {
            switch ($targetFile->getFileType()->getId()) {
                default:
                    break;
                case FileType::ID_DATAMINER:
                case FileType::ID_COINMINER:
                    $result = (($targetFile->getNode()->getLevel() * FileService::DEFAULT_DIFFICULTY_MOD) + $targetFile->getLevel() + $targetFile->getIntegrity()) / 2;
                    break;
            }
            // check for file mods that are effective against the attacking program
            if ($result && $attackingFile) {
                // determine what happens depending on the attacking file's type
                switch ($attackingFile->getFileType()->getId()) {
                    default:
                        break;
                }
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
            case FileType::ID_RESEARCHER:
                $validNodeTypes[] = NodeType::ID_MEMORY;
                break;
            case FileType::ID_COINMINER:
                $validNodeTypes[] = NodeType::ID_TERMINAL;
                break;
            case FileType::ID_DATAMINER:
                $validNodeTypes[] = NodeType::ID_DATABASE;
                break;
            case FileType::ID_OMEN:
                $validNodeTypes[] = NodeType::ID_MONITORING;
                break;
            case FileType::ID_ICMP_BLOCKER:
                $validNodeTypes[] = NodeType::ID_IO;
                break;
            case FileType::ID_CUSTOM_IDE:
                $validNodeTypes[] = NodeType::ID_CODING;
                break;
            case FileType::ID_SKIMMER:
            case FileType::ID_BLOCKCHAINER:
                $validNodeTypes[] = NodeType::ID_BANK;
                break;
            case FileType::ID_LOG_ENCRYPTOR:
            case FileType::ID_LOG_DECRYPTOR:
                $validNodeTypes[] = NodeType::ID_MONITORING;
                break;
            case FileType::ID_PHISHER:
            case FileType::ID_WILDERSPACE_HUB_PORTAL:
                $validNodeTypes[] = NodeType::ID_INTRUSION;
                break;
            case FileType::ID_BEARTRAP:
                $validNodeTypes[] = NodeType::ID_FIREWALL;
                break;
            case FileType::ID_JACKHAMMER:
            case FileType::ID_PORTSCANNER:
            case FileType::ID_WORMER:
            case FileType::ID_IO_TRACER:
                $validNodeTypes[] = NodeType::ID_IO;
                $validNodeTypes[] = NodeType::ID_PUBLICIO;
                break;
        }
        // if result is false, check if the node type matches an entry of the valid-node-types array
        return (!$result) ? in_array($node->getNodeType()->getId(), $validNodeTypes) : $result;
    }

    protected function canStartActionInNodeType()
    {

    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     * @return mixed
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function getProfileGameOptionValue(Profile $profile, $gameOptionId)
    {
        $gameOption = $this->entityManager->find('Netrunners\Entity\GameOption', $gameOptionId);
        $goiRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOptionInstance');
        $gameOptionInstance = $goiRepo->findOneBy([
            'gameOption' => $gameOption,
            'profile' => $profile
        ]);
        return ($gameOptionInstance) ? $gameOptionInstance->getValue() : $gameOption->getDefaultValue();
    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
            $now = new \DateTime();
            if ($gameOptionInstance) {
                $gameOptionInstance->setStatus($newStatus);
                $gameOptionInstance->setChanged($now);
            }
            else {
                $gameOptionInstance = new GameOptionInstance();
                $gameOptionInstance->setStatus($newStatus);
                $gameOptionInstance->setProfile($profile);
                $gameOptionInstance->setGameOption($gameOption);
                $gameOptionInstance->setChanged($now);
                $this->entityManager->persist($gameOptionInstance);
            }
            $this->entityManager->flush($gameOptionInstance);
        }
    }

    /**
     * @param null $setting
     * @return int|string
     * @throws \Exception
     */
    protected function getServerSetting($setting = NULL)
    {
        $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
        /** @var ServerSetting $serverSetting */
        switch ($setting) {
            default:
                throw new \Exception('No setting was given');
                break;
            case self::SETTING_MOTD:
                $result = $serverSetting->getMotd();
                break;
            case self::SETTING_CHATSUBO_NODE_ID:
                $result = $serverSetting->getChatsuboNodeId();
                break;
            case self::SETTING_CHATSUBO_SYSTEM_ID:
                $result = $serverSetting->getChatsuboSystemId();
                break;
            case self::SETTING_WILDERNESS_NODE_ID:
                $result = $serverSetting->getWildernessHubNodeId();
                break;
            case self::SETTING_WILDERNESS_SYSTEM_ID:
                $result = $serverSetting->getWildernessSystemId();
                break;
        }
        return $result;
    }

    /**
     * @param System $system
     * @param string $valueType
     * @return int|null
     * @throws \Exception
     */
    protected function getTotalSystemValueByNodeType(System $system, $valueType = '')
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $value = NULL;
        $nodeType = NULL;
        switch ($valueType) {
            default:
                break;
            case self::VALUE_TYPE_CODINGNODELEVELS:
                $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CODING);
                break;
            case self::VALUE_TYPE_MEMORYLEVELS:
                $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_MEMORY);
                break;
            case self::VALUE_TYPE_STORAGELEVELS:
                $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_STORAGE);
                break;
        }
        if ($nodeType && !empty($valueType)) {
            $affectedNodes = $nodeRepo->findBy([
                'system' => $system,
                'nodeType' => $nodeType
            ]);
            foreach ($affectedNodes as $affectedNode) {
                /** @var Node $affectedNode */
                $value += $affectedNode->getLevel();
            }
        }
        if (!$value) throw new \Exception('Invalid system or value type given');
        return $value;
    }

    /**
     * @param Profile $profile
     * @param MilkrunInstance|NULL $milkrunInstance
     * @param Mission|NULL $mission
     * @param Profile|NULL $rater
     * @param int $source
     * @param int $sourceRating
     * @param int $targetRating
     * @param null $sourceFaction
     * @param null $targetFaction
     * @return bool
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function createProfileFactionRating(
        Profile $profile,
        MilkrunInstance $milkrunInstance = NULL,
        Mission $mission = NULL,
        Profile $rater = NULL,
        $source = 0,
        $sourceRating = 0,
        $targetRating = 0,
        $sourceFaction = NULL,
        $targetFaction = NULL
    )
    {
        $existingRating = false;
        // make sure milkrun isnt added twice
        if ($milkrunInstance) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(pfr.id)');
            $qb->from(ProfileFactionRating::class, 'pfr');
            $qb->where('pfr.milkrunInstance = :milkrun');
            $qb->setParameter('milkrun', $milkrunInstance);
            $result = $qb->getQuery()->getSingleScalarResult();
            if ($result >= 1) $existingRating = true;
        }
        // make sure mission isnt added twice
        if ($mission) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(pfr.id)');
            $qb->from(ProfileFactionRating::class, 'pfr');
            $qb->where('pfr.mission = :mission');
            $qb->setParameter('mission', $mission);
            $result = $qb->getQuery()->getSingleScalarResult();
            if ($result >= 1) $existingRating = true;
        }
        // if no rating exists, create one
        if (!$existingRating) {
            $pfr = new ProfileFactionRating();
            $pfr->setProfile($profile);
            $pfr->setAdded(new \DateTime());
            $pfr->setMilkrunInstance($milkrunInstance);
            $pfr->setMission($mission);
            $pfr->setRater($rater);
            $pfr->setSource($source);
            $pfr->setSourceRating($sourceRating);
            if ($mission) {
                if ($mission->getSourceFaction() === $mission->getTargetFaction()) {
                    $pfr->setTargetRating(0);
                }
                else {
                    $pfr->setTargetRating($targetRating);
                }
            }
            else {
                $pfr->setTargetRating($targetRating);
            }
            $pfr->setSourceFaction($sourceFaction);
            $pfr->setTargetFaction($targetFaction);
            $this->entityManager->persist($pfr);
            $this->entityManager->flush($pfr);
        }
        return true;
    }

    /**
     * @param System $system
     * @param int $amount
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function systemIntegrityChange(System $system, $amount = 0)
    {
        // TODO npcinstances need to check if they belong to profile, group or faction when spawned
        $newIntegrity = $this->checkValueMinMax($system->getIntegrity() + $amount, 0, 100);
        $system->setIntegrity($newIntegrity);
        $this->entityManager->flush($system);
    }

    /**
     * @param $subject
     * @param $effectId
     * @return ProfileEffect|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function getEffectInstance($subject, $effectId)
    {
        $peRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileEffect');
        /** @var ProfileEffectRepository $peRepo */
        $effectInstance = NULL;
        if ($subject instanceof Profile) {
            $effectInstance = $peRepo->findOneByProfileAndEffect($subject, $effectId);
        }
        if ($subject instanceof NpcInstance) {
            $effectInstance = $peRepo->findOneByNpcAndEffect($subject, $effectId);
        }
        return $effectInstance;
    }

    /**
     * @param $string
     * @param int $caseChance
     * @param int $leetChance
     * @return mixed
     */
    protected function leetifyString($string, $caseChance = 50, $leetChance = 50)
    {
        // TODO can be leetified more?
        for ($index = 0; $index < mb_strlen($string); $index++) {
            if (mt_rand(1, 100) > $caseChance) {
                $string[$index] = strtoupper($string[$index]);
            }
            else {
                $string[$index] = strtolower($string[$index]);
            }
            if (mt_rand(1, 100) > $leetChance) {
                switch (strtolower($string[$index])) {
                    default:
                        break;
                    case 'a':
                        $string[$index] = '4';
                        break;
                    case 'b':
                        $string[$index] = '8';
                        break;
                    case 'e':
                        $string[$index] = '3';
                        break;
                    case 'g':
                        $string[$index] = '6';
                        break;
                    case 'i':
                        $string[$index] = '1';
                        break;
                    case 'o':
                        $string[$index] = '0';
                        break;
                    case 'p':
                        $string[$index] = '9';
                        break;
                    case 's':
                        $string[$index] = '5';
                        break;
                    case 't':
                        $string[$index] = '7';
                        break;
                }
            }
        }
        return $string;
    }

    /**
     * @param string $name
     * @param string $addy
     * @param Profile|null $profile
     * @param Faction|null $faction
     * @param Group|null $group
     * @param bool $noclaim
     * @param int $level
     * @param int $maxSize
     * @return System
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function createBaseSystem(
        $name,
        $addy,
        Profile $profile = null,
        Faction $faction = null,
        Group $group = null,
        $noclaim = false,
        $level = 1,
        $maxSize = System::DEFAULT_MAX_SYSTEM_SIZE
    )
    {
        $system = new System();
        $system->setProfile($profile);
        $system->setName($name);
        $system->setAddy($addy);
        $system->setGroup($group);
        $system->setFaction($faction);
        $system->setMaxSize($maxSize);
        $system->setAlertLevel(0);
        $system->setNoclaim($noclaim);
        $system->setIntegrity(100);
        $system->setGeocoords(NULL); // TODO add geocoords
        $this->entityManager->persist($system);
        // default cpu node
        /** @var NodeType $nodeType */
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU);
        $cpuNode = new Node();
        $cpuNode->setCreated(new \DateTime());
        $cpuNode->setLevel($level);
        $cpuNode->setName($nodeType->getName());
        $cpuNode->setSystem($system);
        $cpuNode->setNodeType($nodeType);
        $this->entityManager->persist($cpuNode);
        // default private io node
        /** @var NodeType $nodeType */
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_IO);
        $ioNode = new Node();
        $ioNode->setCreated(new \DateTime());
        $ioNode->setLevel($level);
        $ioNode->setName($nodeType->getName());
        $ioNode->setSystem($system);
        $ioNode->setNodeType($nodeType);
        $this->entityManager->persist($ioNode);
        // connection between nodes
        $connection = new Connection();
        $connection->setCreated(new \DateTime());
        $connection->setLevel($level);
        $connection->setIsOpen(NULL);
        $connection->setSourceNode($cpuNode);
        $connection->setTargetNode($ioNode);
        $connection->setType(Connection::TYPE_CODEGATE);
        $this->entityManager->persist($connection);
        $connection = new Connection();
        $connection->setCreated(new \DateTime());
        $connection->setLevel($level);
        $connection->setIsOpen(NULL);
        $connection->setTargetNode($cpuNode);
        $connection->setSourceNode($ioNode);
        $connection->setType(Connection::TYPE_CODEGATE);
        $this->entityManager->persist($connection);
        return $system;
    }

    /**
     * @param $value
     * @param null $min
     * @param null $max
     * @return null
     */
    protected function checkValueMinMax($value, $min = NULL, $max = NULL)
    {
        if ($min && $value < $min) $value = $min;
        if ($max && $value > $max) $value = $max;
        return $value;
    }

    /**
     * @param $message
     * @param $textClass
     * @throws \Exception
     */
    protected function broadcastMessage($message, $textClass)
    {
        $response = new GameClientResponse(NULL, GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $response->addMessage($message, $textClass);
        foreach ($this->getWebsocketServer()->getClients() as $wsClientId => $wsClient) {
            $response->setResourceId($wsClient->resourceId)->send();
        }
    }

    /**
     * @param $amount
     * @param string $locale
     * @return string
     */
    protected function numberFormat($amount, $locale = Profile::DEFAULT_PROFILE_LOCALE)
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        return $formatter->format($amount);
    }

}
