<?php

/**
 * MilkrunAivatarInstance Entity.
 * AIVATAR instances for milkruns.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MilkrunAivatarInstanceRepository") */
class MilkrunAivatarInstance
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
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $maxEeg;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $currentEeg;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $maxAttack;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $currentAttack;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $maxArmor;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $currentArmor;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $specials;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $completed;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $pointsearned;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $pointsused;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $modified;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $upgrades;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\MilkrunAivatar")
     */
    protected $milkrunAivatar;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return MilkrunAivatarInstance
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
     * @return MilkrunAivatarInstance
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @return MilkrunAivatarInstance
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
     * @return MilkrunAivatarInstance
     */
    public function setCurrentEeg($currentEeg)
    {
        $this->currentEeg = $currentEeg;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAttack()
    {
        return $this->maxAttack;
    }

    /**
     * @param int $maxAttack
     * @return MilkrunAivatarInstance
     */
    public function setMaxAttack($maxAttack)
    {
        $this->maxAttack = $maxAttack;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentAttack()
    {
        return $this->currentAttack;
    }

    /**
     * @param int $currentAttack
     * @return MilkrunAivatarInstance
     */
    public function setCurrentAttack($currentAttack)
    {
        $this->currentAttack = $currentAttack;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxArmor()
    {
        return $this->maxArmor;
    }

    /**
     * @param int $maxArmor
     * @return MilkrunAivatarInstance
     */
    public function setMaxArmor($maxArmor)
    {
        $this->maxArmor = $maxArmor;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentArmor()
    {
        return $this->currentArmor;
    }

    /**
     * @param int $currentArmor
     * @return MilkrunAivatarInstance
     */
    public function setCurrentArmor($currentArmor)
    {
        $this->currentArmor = $currentArmor;
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
     * @return MilkrunAivatarInstance
     */
    public function setSpecials($specials)
    {
        $this->specials = $specials;
        return $this;
    }

    /**
     * @return int
     */
    public function getCompleted()
    {
        return $this->completed;
    }

    /**
     * @param int $completed
     * @return MilkrunAivatarInstance
     */
    public function setCompleted($completed)
    {
        $this->completed = $completed;
        return $this;
    }

    /**
     * @return int
     */
    public function getPointsearned()
    {
        return $this->pointsearned;
    }

    /**
     * @param int $pointsearned
     * @return MilkrunAivatarInstance
     */
    public function setPointsearned($pointsearned)
    {
        $this->pointsearned = $pointsearned;
        return $this;
    }

    /**
     * @return int
     */
    public function getPointsused()
    {
        return $this->pointsused;
    }

    /**
     * @param int $pointsused
     * @return MilkrunAivatarInstance
     */
    public function setPointsused($pointsused)
    {
        $this->pointsused = $pointsused;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param \DateTime $created
     * @return MilkrunAivatarInstance
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param \DateTime $modified
     * @return MilkrunAivatarInstance
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        return $this;
    }

    /**
     * @return int
     */
    public function getUpgrades()
    {
        return $this->upgrades;
    }

    /**
     * @param int $upgrades
     * @return MilkrunAivatarInstance
     */
    public function setUpgrades($upgrades)
    {
        $this->upgrades = $upgrades;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return MilkrunAivatarInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMilkrunAivatar()
    {
        return $this->milkrunAivatar;
    }

    /**
     * @param mixed $milkrunAivatar
     * @return MilkrunAivatarInstance
     */
    public function setMilkrunAivatar($milkrunAivatar)
    {
        $this->milkrunAivatar = $milkrunAivatar;
        return $this;
    }

}
