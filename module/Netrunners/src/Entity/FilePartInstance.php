<?php

/**
 * FilePartInstance Entity.
 * All kinds of information about the parts that users have coded are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FilePartInstanceRepository") */
class FilePartInstance
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

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FilePart")
     */
    protected $filePart;

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
     * @return FilePartInstance
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return FilePartInstance
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getFilePart()
    {
        return $this->filePart;
    }

    /**
     * @param mixed $filePart
     * @return FilePartInstance
     */
    public function setFilePart($filePart)
    {
        $this->filePart = $filePart;
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
     * @return FilePartInstance
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
     * @return FilePartInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
