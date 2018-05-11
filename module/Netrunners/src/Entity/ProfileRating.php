<?php

/**
 * ProfileRating Entity.
 * This keeps track of which profile has how much rating with another profile.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileRatingRepository") */
final class ProfileRating
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
    protected $changed;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $rating;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $sourceProfile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $targetProfile;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return ProfileRating
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * @param \DateTime $changed
     * @return ProfileRating
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;
        return $this;
    }

    /**
     * @return int
     */
    public function getRating()
    {
        return $this->rating;
    }

    /**
     * @param int $rating
     * @return ProfileRating
     */
    public function setRating($rating)
    {
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceProfile()
    {
        return $this->sourceProfile;
    }

    /**
     * @param mixed $sourceProfile
     * @return ProfileRating
     */
    public function setSourceProfile($sourceProfile)
    {
        $this->sourceProfile = $sourceProfile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTargetProfile()
    {
        return $this->targetProfile;
    }

    /**
     * @param mixed $targetProfile
     * @return ProfileRating
     */
    public function setTargetProfile($targetProfile)
    {
        $this->targetProfile = $targetProfile;
        return $this;
    }

}
