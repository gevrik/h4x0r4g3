<?php

/**
 * Mission Entity.
 * This keeps track of which profile has been assigned which mission.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MissionRepository") */
class Mission
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $completed;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $expires;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $expired;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $sourceFaction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $targetFaction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\MissionArchetype")
     */
    protected $mission;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\System")
     */
    protected $targetSystem;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $targetFile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     */
    protected $targetNode;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Mission
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * @param \DateTime $added
     * @return Mission
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCompleted()
    {
        return $this->completed;
    }

    /**
     * @param \DateTime $completed
     * @return Mission
     */
    public function setCompleted($completed)
    {
        $this->completed = $completed;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * @param \DateTime $expires
     * @return Mission
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;
        return $this;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param int $level
     * @return Mission
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return int
     */
    public function getExpired()
    {
        return $this->expired;
    }

    /**
     * @param int $expired
     * @return Mission
     */
    public function setExpired($expired)
    {
        $this->expired = $expired;
        return $this;
    }

    // ORM

    /**
     * @return Faction
     */
    public function getSourceFaction()
    {
        return $this->sourceFaction;
    }

    /**
     * @param mixed $sourceFaction
     * @return Mission
     */
    public function setSourceFaction($sourceFaction)
    {
        $this->sourceFaction = $sourceFaction;
        return $this;
    }

    /**
     * @return Faction
     */
    public function getTargetFaction()
    {
        return $this->targetFaction;
    }

    /**
     * @param mixed $targetFaction
     * @return Mission
     */
    public function setTargetFaction($targetFaction)
    {
        $this->targetFaction = $targetFaction;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return Mission
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return MissionArchetype
     */
    public function getMission()
    {
        return $this->mission;
    }

    /**
     * @param mixed $mission
     * @return Mission
     */
    public function setMission($mission)
    {
        $this->mission = $mission;
        return $this;
    }

    /**
     * @return System
     */
    public function getTargetSystem()
    {
        return $this->targetSystem;
    }

    /**
     * @param mixed $targetSystem
     * @return Mission
     */
    public function setTargetSystem($targetSystem)
    {
        $this->targetSystem = $targetSystem;
        return $this;
    }

    /**
     * @return File
     */
    public function getTargetFile()
    {
        return $this->targetFile;
    }

    /**
     * @param mixed $targetFile
     * @return Mission
     */
    public function setTargetFile($targetFile)
    {
        $this->targetFile = $targetFile;
        return $this;
    }

    /**
     * @return Node
     */
    public function getTargetNode()
    {
        return $this->targetNode;
    }

    /**
     * @param mixed $targetNode
     * @return Mission
     */
    public function setTargetNode($targetNode)
    {
        $this->targetNode = $targetNode;
        return $this;
    }

}
