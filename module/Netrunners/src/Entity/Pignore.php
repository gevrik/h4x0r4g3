<?php

/**
 * Pignore Entity.
 * This keeps track of which profile has ignored which other profiles.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\PignoreRepository") */
final class Pignore
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
     * @return Pignore
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
     * @return Pignore
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
    public function getSourceProfile()
    {
        return $this->sourceProfile;
    }

    /**
     * @param mixed $sourceProfile
     * @return Pignore
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
     * @return Pignore
     */
    public function setTargetProfile($targetProfile)
    {
        $this->targetProfile = $targetProfile;
        return $this;
    }

}
