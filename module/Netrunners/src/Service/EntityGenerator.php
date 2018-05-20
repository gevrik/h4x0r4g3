<?php

/**
 * EntityGenerator Service.
 * The service supplies methods to create entities.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Group;
use Netrunners\Entity\MailMessage;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;

final class EntityGenerator
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * EntityGenerator constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Node $sourceNode
     * @param Node $targetNode
     * @param bool $isOpen
     * @param int $level
     * @param int $type
     * @return Connection
     */
    public function createConnection(
        Node $sourceNode,
        Node $targetNode,
        $isOpen = true,
        $level = 1,
        $type = Connection::TYPE_NORMAL
    )
    {
        $connection = new Connection();
        $connection->setCreated(new \DateTime());
        $connection->setLevel($level);
        $connection->setIsOpen($isOpen);
        $connection->setIsOpen(NULL);
        $connection->setSourceNode($sourceNode);
        $connection->setTargetNode($targetNode);
        $connection->setType($type);
        $this->entityManager->persist($connection);
        return $connection;
    }

    /**
     * @param System $system
     * @param NodeType $nodeType
     * @param int $level
     * @param Profile|null $profile
     * @param string|null $name
     * @param string|null $description
     * @param bool $nomob
     * @param bool $nopvp
     * @param bool $noclaim
     * @param string|null $data
     * @return Node
     */
    public function createNode(
        System $system,
        NodeType $nodeType,
        $level = 1,
        Profile $profile = null,
        $name = null,
        $description = null,
        $nomob = false,
        $nopvp = false,
        $noclaim = true,
        $data = null
    )
    {
        $node = new Node();
        $node->setCreated(new \DateTime());
        $node->setLevel($level);
        $node->setName(($name) ? $name : $nodeType->getName());
        $node->setDescription(($description) ? $description : $nodeType->getDescription());
        $node->setNomob($nomob);
        $node->setProfile($profile);
        $node->setNopvp($nopvp);
        $node->setSystem($system);
        $node->setNodeType($nodeType);
        $node->setNoclaim($noclaim);
        $node->setIntegrity(100);
        $node->setData($data);
        $this->entityManager->persist($node);
        return $node;
    }

    /**
     * @param string $name
     * @param string $addy
     * @param Profile|null $profile
     * @param Group|null $group
     * @param Faction|null $faction
     * @param int|null $maxSize
     * @param bool $noclaim
     * @param null $geoCoords
     * @return System
     */
    public function createSystem(
        $name,
        $addy,
        Profile $profile = null,
        Group $group = null,
        Faction $faction = null,
        $maxSize = null,
        $noclaim = false,
        $geoCoords = null
    )
    {
        if (!$maxSize) {
            $maxSize = $this->getSystemSizeByType($faction, $group);
        }
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
        $system->setGeocoords($geoCoords);
        $this->entityManager->persist($system);
        return $system;
    }

    /**
     * @param Faction|null $faction
     * @param Group|null $group
     * @return int
     */
    protected function getSystemSizeByType(Faction $faction = null, Group $group = null)
    {
        if ($faction instanceof Faction) {
            $maxSize = System::FACTION_MAX_SYSTEM_SIZE;
        }
        elseif ($group instanceof Group) {
            $maxSize = System::GROUP_MAX_SYSTEM_SIZE;
        }
        else {
            $maxSize = System::DEFAULT_MAX_SYSTEM_SIZE;
        }
        return $maxSize;
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
    public function createFile(
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

}
