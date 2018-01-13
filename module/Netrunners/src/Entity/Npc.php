<?php

/**
 * NPC Entity.
 * All kinds of information about the different npcs in the game.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\NpcRepository") */
class Npc
{

    const ID_MURPHY_VIRUS = 1;
    const ID_KILLER_VIRUS = 2;
    const ID_BOUNCER_ICE = 3;
    const ID_WORKER_PROGRAM = 4;
    const ID_SENTINEL_ICE = 5;
    const ID_WILDERSPACE_INTRUDER = 6;
    const ID_DEBUGGER_PROGRAM = 7;
    const ID_NETWATCH_INVESTIGATOR = 8;
    const ID_NETWATCH_AGENT = 9;
    const ID_GUARDIAN_ICE = 10;

    const TYPE_VIRUS = 1;
    const TYPE_HELPER = 2;
    const TYPE_NETWATCH = 3;

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
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseEeg;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseSnippets;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseCredits;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseBlade;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseBlaster;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseShield;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseDetection;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseStealth;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $baseSlots;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $aggressive;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $roaming;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $stealthing;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $social;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $type;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Npc
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
     * @return Npc
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
     * @return Npc
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseEeg()
    {
        return $this->baseEeg;
    }

    /**
     * @param int $baseEeg
     * @return Npc
     */
    public function setBaseEeg($baseEeg)
    {
        $this->baseEeg = $baseEeg;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseSnippets()
    {
        return $this->baseSnippets;
    }

    /**
     * @param int $baseSnippets
     * @return Npc
     */
    public function setBaseSnippets($baseSnippets)
    {
        $this->baseSnippets = $baseSnippets;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseCredits()
    {
        return $this->baseCredits;
    }

    /**
     * @param int $baseCredits
     * @return Npc
     */
    public function setBaseCredits($baseCredits)
    {
        $this->baseCredits = $baseCredits;
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
     * @return Npc
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseBlade()
    {
        return $this->baseBlade;
    }

    /**
     * @param int $baseBlade
     * @return Npc
     */
    public function setBaseBlade($baseBlade)
    {
        $this->baseBlade = $baseBlade;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseBlaster()
    {
        return $this->baseBlaster;
    }

    /**
     * @param int $baseBlaster
     * @return Npc
     */
    public function setBaseBlaster($baseBlaster)
    {
        $this->baseBlaster = $baseBlaster;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseShield()
    {
        return $this->baseShield;
    }

    /**
     * @param int $baseShield
     * @return Npc
     */
    public function setBaseShield($baseShield)
    {
        $this->baseShield = $baseShield;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseDetection()
    {
        return $this->baseDetection;
    }

    /**
     * @param int $baseDetection
     * @return Npc
     */
    public function setBaseDetection($baseDetection)
    {
        $this->baseDetection = $baseDetection;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseStealth()
    {
        return $this->baseStealth;
    }

    /**
     * @param int $baseStealth
     * @return Npc
     */
    public function setBaseStealth($baseStealth)
    {
        $this->baseStealth = $baseStealth;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseSlots()
    {
        return $this->baseSlots;
    }

    /**
     * @param int $baseSlots
     * @return Npc
     */
    public function setBaseSlots($baseSlots)
    {
        $this->baseSlots = $baseSlots;
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
     * @return Npc
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
     * @return Npc
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
     * @return Npc
     */
    public function setStealthing($stealthing)
    {
        $this->stealthing = $stealthing;
        return $this;
    }

    /**
     * @return int
     */
    public function getSocial()
    {
        return $this->social;
    }

    /**
     * @param int $social
     * @return Npc
     */
    public function setSocial($social)
    {
        $this->social = $social;
        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return Npc
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

}
