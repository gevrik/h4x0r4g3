<?php

/**
 * ProfileFactionRating Entity.
 * This keeps track of which profile has how much rating with each faction.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileFactionRatingRepository") */
class ProfileFactionRating
{

    const SOURCE_ID_PLOFILE = 1;
    const SOURCE_ID_MILKRUN = 2;

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
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $sourceRating;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $targetRating;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $source;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\MilkrunInstance")
     */
    protected $milkrunInstance;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $rater;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $sourceFaction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $targetFaction;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return ProfileFactionRating
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
     * @return ProfileFactionRating
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return int
     */
    public function getSourceRating()
    {
        return $this->sourceRating;
    }

    /**
     * @param int $sourceRating
     * @return ProfileFactionRating
     */
    public function setSourceRating($sourceRating)
    {
        $this->sourceRating = $sourceRating;
        return $this;
    }

    /**
     * @return int
     */
    public function getTargetRating()
    {
        return $this->targetRating;
    }

    /**
     * @param int $targetRating
     * @return ProfileFactionRating
     */
    public function setTargetRating($targetRating)
    {
        $this->targetRating = $targetRating;
        return $this;
    }

    /**
     * @return int
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param int $source
     * @return ProfileFactionRating
     */
    public function setSource($source)
    {
        $this->source = $source;
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
     * @return ProfileFactionRating
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMilkrunInstance()
    {
        return $this->milkrunInstance;
    }

    /**
     * @param mixed $milkrunInstance
     * @return ProfileFactionRating
     */
    public function setMilkrunInstance($milkrunInstance)
    {
        $this->milkrunInstance = $milkrunInstance;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRater()
    {
        return $this->rater;
    }

    /**
     * @param mixed $rater
     * @return ProfileFactionRating
     */
    public function setRater($rater)
    {
        $this->rater = $rater;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceFaction()
    {
        return $this->sourceFaction;
    }

    /**
     * @param mixed $sourceFaction
     * @return ProfileFactionRating
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
     * @return ProfileFactionRating
     */
    public function setTargetFaction($targetFaction)
    {
        $this->targetFaction = $targetFaction;
        return $this;
    }

}
