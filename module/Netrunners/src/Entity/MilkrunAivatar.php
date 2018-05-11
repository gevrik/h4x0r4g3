<?php

/**
 * MilkrunAivatar Entity.
 * AIVATAR archetypes for milkruns.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MilkrunAivatarRepository") */
class MilkrunAivatar
{

    const ID_SCROUNGER = 1;

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
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $baseEeg;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $baseAttack;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $baseArmor;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $specials;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return MilkrunAivatar
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
     * @return MilkrunAivatar
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
     * @return MilkrunAivatar
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
     * @return MilkrunAivatar
     */
    public function setBaseEeg($baseEeg)
    {
        $this->baseEeg = $baseEeg;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseAttack()
    {
        return $this->baseAttack;
    }

    /**
     * @param int $baseAttack
     * @return MilkrunAivatar
     */
    public function setBaseAttack($baseAttack)
    {
        $this->baseAttack = $baseAttack;
        return $this;
    }

    /**
     * @return int
     */
    public function getBaseArmor()
    {
        return $this->baseArmor;
    }

    /**
     * @param int $baseArmor
     * @return MilkrunAivatar
     */
    public function setBaseArmor($baseArmor)
    {
        $this->baseArmor = $baseArmor;
        return $this;
    }

    /**
     * @return string
     */
    public function getSpecials()
    {
        return $this->specials;
    }

    /**
     * @param string $specials
     * @return MilkrunAivatar
     */
    public function setSpecials($specials)
    {
        $this->specials = $specials;
        return $this;
    }

}
