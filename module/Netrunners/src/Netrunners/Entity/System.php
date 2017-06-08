<?php

/**
 * Sytem Entity.
 * All kinds of information about a user's system is stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\SystemRepository") */
class System
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
     * @ORM\Column(type="integer", options={"default":1})
     * @var int
     */
    protected $cpu;

    /**
     * @ORM\Column(type="integer", options={"default":16})
     * @var int
     */
    protected $memory;

    /**
     * @ORM\Column(type="integer", options={"default":32})
     * @var int
     */
    protected $storage;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $addy;

    // ORM

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\Profile", inversedBy="system")
     **/
    protected $profile;

    /**
     * @ORM\OneToMany(targetEntity="Netrunners\Entity\File", mappedBy="system")
     **/
    protected $files;


    /**
     * Constructor for System.
     */
    public function __construct() {
        $this->files = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return System
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
     * @return System
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int
     */
    public function getCpu()
    {
        return $this->cpu;
    }

    /**
     * @param int $cpu
     * @return System
     */
    public function setCpu($cpu)
    {
        $this->cpu = $cpu;
        return $this;
    }

    /**
     * @return int
     */
    public function getMemory()
    {
        return $this->memory;
    }

    /**
     * @param int $memory
     * @return System
     */
    public function setMemory($memory)
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * @return int
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param int $storage
     * @return System
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * @return string
     */
    public function getAddy()
    {
        return $this->addy;
    }

    /**
     * @param string $addy
     * @return System
     */
    public function setAddy($addy)
    {
        $this->addy = $addy;
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
     * @return System
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param File $file
     */
    public function addFile(File $file)
    {
        $this->files[] = $file;
    }

    /**
     * @param File $file
     */
    public function removeFile(File $file)
    {
        $this->files->removeElement($file);
    }

}
