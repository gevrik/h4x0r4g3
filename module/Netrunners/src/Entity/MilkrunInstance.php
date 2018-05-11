<?php

/**
 * MilkrunInstance Entity.
 * This keeps track of which profile has been assigned which milkrun.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MilkrunInstanceRepository") */
class MilkrunInstance
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
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Milkrun")
     */
    protected $milkrun;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\MilkrunAivatarInstance")
     */
    protected $milkrunAivatarInstance;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return MilkrunInstance
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
     * @return MilkrunInstance
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
     * @return MilkrunInstance
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
     * @return MilkrunInstance
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
     * @return MilkrunInstance
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
     * @return MilkrunInstance
     */
    public function setExpired($expired)
    {
        $this->expired = $expired;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getSourceFaction()
    {
        return $this->sourceFaction;
    }

    /**
     * @param mixed $sourceFaction
     * @return MilkrunInstance
     */
    public function setSourceFaction($sourceFaction)
    {
        $this->sourceFaction = $sourceFaction;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTargetFaction()
    {
        return $this->targetFaction;
    }

    /**
     * @param mixed $targetFaction
     * @return MilkrunInstance
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
     * @return MilkrunInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed|Milkrun
     */
    public function getMilkrun()
    {
        return $this->milkrun;
    }

    /**
     * @param mixed $milkrun
     * @return MilkrunInstance
     */
    public function setMilkrun($milkrun)
    {
        $this->milkrun = $milkrun;
        return $this;
    }

    /**
     * @return MilkrunAivatarInstance|null
     */
    public function getMilkrunAivatarInstance()
    {
        return $this->milkrunAivatarInstance;
    }

    /**
     * @param mixed $milkrunAivatarInstance
     * @return MilkrunInstance
     */
    public function setMilkrunAivatarInstance($milkrunAivatarInstance)
    {
        $this->milkrunAivatarInstance = $milkrunAivatarInstance;
        return $this;
    }

}
