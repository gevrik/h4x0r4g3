<?php

/**
 * File Entity.
 * All kinds of information about files are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FileRepository") */
class File
{

    /**
     * @const TYPE_DIRECTORY
     */
    const TYPE_DIRECTORY = 1;

    /**
     * @const TYPE_CHAT_CLIENT
     */
    const TYPE_CHAT_CLIENT = 2;

    /**
     * @const TYPE_DATA_MINER
     */
    const TYPE_DATA_MINER = 3;

    const TYPE_KEY_LABEL = 'label';

    const TYPE_KEY_CODABLE = 'codable';

    /**
     * Data for file type.
     * @var array
     */
    static $fileTypeData = array(
        array(self::TYPE_KEY_LABEL => 'INVALID', self::TYPE_KEY_CODABLE => false),
        array(self::TYPE_KEY_LABEL => 'directory', self::TYPE_KEY_CODABLE => false),
        array(self::TYPE_KEY_LABEL => 'chatclient', self::TYPE_KEY_CODABLE => true),
        array(self::TYPE_KEY_LABEL => 'dataminer', self::TYPE_KEY_CODABLE => true),
    );

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
    protected $size;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $type;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $maxIntegrity;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $integrity;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $modified;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $executable;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $running;

    /**
     * @ORM\Column(type="integer", options={"default":1})
     * @var int
     */
    protected $version;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $coder;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\System", inversedBy="files")
     */
    protected $system;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\MailMessage", inversedBy="attachments")
     */
    protected $mailMessage;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\File", inversedBy="children")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Netrunners\Entity\File", mappedBy="parent")
     */
    protected $children;


    /**
     * Constructor for System.
     */
    public function __construct() {
        $this->children = new ArrayCollection();
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
     * @return File
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
     * @return File
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return File
     */
    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return File
     */
    public function setType($type)
    {
        $this->type = $type;
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
     * @return File
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxIntegrity()
    {
        return $this->maxIntegrity;
    }

    /**
     * @param int $maxIntegrity
     * @return File
     */
    public function setMaxIntegrity($maxIntegrity)
    {
        $this->maxIntegrity = $maxIntegrity;
        return $this;
    }

    /**
     * @return int
     */
    public function getIntegrity()
    {
        return $this->integrity;
    }

    /**
     * @param int $integrity
     * @return File
     */
    public function setIntegrity($integrity)
    {
        $this->integrity = $integrity;
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
     * @return File
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
     * @return File
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        return $this;
    }

    /**
     * @return int
     */
    public function getExecutable()
    {
        return $this->executable;
    }

    /**
     * @param int $executable
     * @return File
     */
    public function setExecutable($executable)
    {
        $this->executable = $executable;
        return $this;
    }

    /**
     * @return int
     */
    public function getRunning()
    {
        return $this->running;
    }

    /**
     * @param int $running
     * @return File
     */
    public function setRunning($running)
    {
        $this->running = $running;
        return $this;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param int $version
     * @return File
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getCoder()
    {
        return $this->coder;
    }

    /**
     * @param mixed $coder
     * @return File
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
     * @return File
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @param mixed $system
     * @return File
     */
    public function setSystem($system)
    {
        $this->system = $system;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMailMessage()
    {
        return $this->mailMessage;
    }

    /**
     * @param mixed $mailMessage
     * @return File
     */
    public function setMailMessage($mailMessage)
    {
        $this->mailMessage = $mailMessage;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     * @return File
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param File $file
     */
    public function addChild(File $file)
    {
        $this->children[] = $file;
    }

    /**
     * @param File $file
     */
    public function removeChild(File $file)
    {
        $this->children->removeElement($file);
    }

}
