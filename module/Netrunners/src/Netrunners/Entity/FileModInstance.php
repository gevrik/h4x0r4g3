<?php

/**
 * FileModInstance Entity.
 * This keeps track of which mods have been used on which file.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FileModInstanceRepository") */
class FileModInstance
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $file;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FileMod")
     */
    protected $fileMod;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $coder;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FileModInstance
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param mixed $level
     * @return FileModInstance
     */
    public function setLevel($level)
    {
        $this->level = $level;
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
     * @return FileModInstance
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     * @return FileModInstance
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return FileMod
     */
    public function getFileMod()
    {
        return $this->fileMod;
    }

    /**
     * @param mixed $fileMod
     * @return FileModInstance
     */
    public function setFileMod($fileMod)
    {
        $this->fileMod = $fileMod;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCoder()
    {
        return $this->coder;
    }

    /**
     * @param mixed $coder
     * @return FileModInstance
     */
    public function setCoder($coder)
    {
        $this->coder = $coder;
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
     * @return FileModInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
