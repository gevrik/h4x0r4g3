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
class Skill
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
