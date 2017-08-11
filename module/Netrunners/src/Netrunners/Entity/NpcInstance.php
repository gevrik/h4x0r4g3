<?php

/**
 * NpcInstance Entity.
 * Keeps track of all the spawned npcs in the game.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Netrunners\Repository\NpcInstanceRepository")
 * @ORM\Table(indexes={@ORM\Index(name="search_idx", columns={"name"})})
 */
class NpcInstance
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @var string
     */
    protected $description;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $maxEeg;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $currentEeg;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $snippets;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $credits;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $slots;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $aggressive;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $roaming;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $stealthing;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Npc")
     */
    protected $npc;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     */
    protected $node;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     */
    protected $homeNode;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Group")
     */
    protected $group;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $faction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\System")
     */
    protected $system;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\System")
     */
    protected $homeSystem;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return NpcInstance
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return NpcInstance
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return NpcInstance
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxEeg()
    {
        return $this->maxEeg;
    }

    /**
     * @param int $maxEeg
     * @return NpcInstance
     */
    public function setMaxEeg($maxEeg)
    {
        $this->maxEeg = $maxEeg;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentEeg()
    {
        return $this->currentEeg;
    }

    /**
     * @param int $currentEeg
     * @return NpcInstance
     */
    public function setCurrentEeg($currentEeg)
    {
        $this->currentEeg = $currentEeg;
        return $this;
    }

    /**
     * @return int
     */
    public function getSnippets()
    {
        return $this->snippets;
    }

    /**
     * @param int $snippets
     * @return NpcInstance
     */
    public function setSnippets($snippets)
    {
        $this->snippets = $snippets;
        return $this;
    }

    /**
     * @return int
     */
    public function getCredits()
    {
        return $this->credits;
    }

    /**
     * @param int $credits
     * @return NpcInstance
     */
    public function setCredits($credits)
    {
        $this->credits = $credits;
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
     * @return NpcInstance
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return int
     */
    public function getSlots()
    {
        return $this->slots;
    }

    /**
     * @param int $slots
     * @return NpcInstance
     */
    public function setSlots($slots)
    {
        $this->slots = $slots;
        return $this;
    }

    /**
     * @return int
     */
    public function getAggressive()
    {
        return $this->aggressive;
    }

    /**
     * @param int $aggressive
     * @return NpcInstance
     */
    public function setAggressive($aggressive)
    {
        $this->aggressive = $aggressive;
        return $this;
    }

    /**
     * @return int
     */
    public function getRoaming()
    {
        return $this->roaming;
    }

    /**
     * @param int $roaming
     * @return NpcInstance
     */
    public function setRoaming($roaming)
    {
        $this->roaming = $roaming;
        return $this;
    }

    /**
     * @return int
     */
    public function getStealthing()
    {
        return $this->stealthing;
    }

    /**
     * @param int $stealthing
     * @return NpcInstance
     */
    public function setStealthing($stealthing)
    {
        $this->stealthing = $stealthing;
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
     * @return NpcInstance
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getNpc()
    {
        return $this->npc;
    }

    /**
     * @param mixed $npc
     * @return NpcInstance
     */
    public function setNpc($npc)
    {
        $this->npc = $npc;
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
     * @return NpcInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param mixed $node
     * @return NpcInstance
     */
    public function setNode($node)
    {
        $this->node = $node;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHomeNode()
    {
        return $this->homeNode;
    }

    /**
     * @param mixed $homeNode
     * @return NpcInstance
     */
    public function setHomeNode($homeNode)
    {
        $this->homeNode = $homeNode;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param mixed $group
     * @return NpcInstance
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFaction()
    {
        return $this->faction;
    }

    /**
     * @param mixed $faction
     * @return NpcInstance
     */
    public function setFaction($faction)
    {
        $this->faction = $faction;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @param mixed $system
     * @return NpcInstance
     */
    public function setSystem($system)
    {
        $this->system = $system;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHomeSystem()
    {
        return $this->homeSystem;
    }

    /**
     * @param mixed $homeSystem
     * @return NpcInstance
     */
    public function setHomeSystem($homeSystem)
    {
        $this->homeSystem = $homeSystem;
        return $this;
    }

}
