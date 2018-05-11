<?php

/**
 * Skill Entity.
 * All kinds of information about the different skills that are available to players.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\SkillRepository") */
final class Skill
{

    const ID_CODING = 1;
    const ID_BLACKHAT = 2;
    const ID_WHITEHAT = 3;
    const ID_NETWORKING = 4;
    const ID_COMPUTING = 5;
    const ID_DATABASE = 6;
    const ID_ELECTRONICS = 7;
    const ID_FORENSICS = 8;
    const ID_SOCIAL_ENGINEERING = 9;
    const ID_CRYPTOGRAPHY = 10;
    const ID_REVERSE_ENGINEERING = 11;
    const ID_ADVANCED_NETWORKING = 12;
    const ID_ADVANCED_CODING = 13;
    const ID_BLADES = 14;
    const ID_BLASTERS = 15;
    const ID_SHIELDS = 16;
    const ID_BLADECODING = 17;
    const ID_BLASTERCODING = 18;
    const ID_SHIELDCODING = 19;
    const ID_STEALTH = 20;
    const ID_DETECTION = 21;
    const ID_LEADERSHIP = 22;

    const ID_BEAM_WEAPONS = 23;
    const ID_BLADE_WEAPONS = 24;
    const ID_CLIMBING = 25;
    const ID_CONTROL = 26;
    const ID_DECEPTION = 27;
    const ID_DEMOLITIONS = 28;
    const ID_DISGUISE = 29;
    const ID_FLIGHT = 30;
    const ID_FRAY = 31;
    const ID_FREE_FALL = 32;
    const ID_FREE_RUNNING = 33;
    const ID_GUNNERY = 34;
    const ID_HARDWARE = 35;
    const ID_IMPERSONATION = 36;
    const ID_INFILTRATION = 37;
    const ID_INFOSEC = 38;
    const ID_INTERFACING = 39;
    const ID_INTIMIDATION = 40;
    const ID_INVESTIGATION = 41;
    const ID_KINESICS = 42;
    const ID_KINETIC_WEAPONS = 43;
    const ID_MEDICINE = 44;
    const ID_NAVIGATION = 45;
    const ID_PALMING = 46;
    const ID_PERCEPTION = 47;
    const ID_PERSUASION = 48;
    const ID_PILOT = 49;

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
    protected $advanced;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $level;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Skill
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
     * @return Skill
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
     * @return Skill
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getAdvanced()
    {
        return $this->advanced;
    }

    /**
     * @param int $advanced
     * @return Skill
     */
    public function setAdvanced($advanced)
    {
        $this->advanced = $advanced;
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
     * @return Skill
     */
    public function setAdded($added)
    {
        $this->added = $added;
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
     * @return Skill
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

}
