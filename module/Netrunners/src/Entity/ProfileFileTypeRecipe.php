<?php

/**
 * ProfileFileTypeRecipe Entity.
 * This keeps track of which profile has acquired which file type recipe.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileFileTypeRecipeRepository") */
final class ProfileFileTypeRecipe
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
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $runs;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FileType")
     */
    protected $fileType;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return ProfileFileTypeRecipe
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
     * @return ProfileFileTypeRecipe
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return int
     */
    public function getRuns()
    {
        return $this->runs;
    }

    /**
     * @param int $runs
     * @return ProfileFileTypeRecipe
     */
    public function setRuns($runs)
    {
        $this->runs = $runs;
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
     * @return ProfileFileTypeRecipe
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFileType()
    {
        return $this->fileType;
    }

    /**
     * @param mixed $fileType
     * @return ProfileFileTypeRecipe
     */
    public function setFileType($fileType)
    {
        $this->fileType = $fileType;
        return $this;
    }

}
