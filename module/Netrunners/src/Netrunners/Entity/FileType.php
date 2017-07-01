<?php

/**
 * FileType Entity.
 * All kinds of information about file types are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FileTypeRepository") */
class FileType
{

    const ID_DIRECTORY = 1;
    const ID_CHATCLIENT = 2;
    const ID_DATAMINER = 3;
    const ID_TEXT = 4;
    const ID_ICMP_BLOCKER = 5;
    const ID_COINMINER = 6;
    const ID_CODEBLADE = 7;
    const ID_CODEBLASTER = 8;
    const ID_CODEARMOR = 9;
    const ID_CODESHIELD = 10;
    const ID_SYSMAPPER = 11;
    const ID_PORTSCANNER = 12;
    const ID_JACKHAMMER = 13;
    const ID_WORMER = 14;
    const ID_CODEBREAKER = 15;

    const STRING_DIRECTORY = 'directory';
    const STRING_CHATCLIENT = 'chatclient';
    const STRING_DATAMINER = 'dataminer';
    const STRING_TEXT = 'text';
    const STRING_ICMP_BLOCKER = 'icmpblocker';
    const STRING_COINMINER = 'coinminer';
    const STRING_CODEBLADE = 'codeblade';
    const STRING_CODEBLASTER = 'codeblaster';
    const STRING_CODEARMOR = 'codearmor';
    const STRING_CODESHIELD = 'codeshield';
    const STRING_SYSMAPPER = 'sysmapper';
    const STRING_PORTSCANNER = 'portscanner';
    const STRING_JACKHAMMER = 'jackhammer';
    const STRING_WORMER = 'wormer';
    const STRING_CODEBREAKER = 'codebreaker';

    static $revLookup = [
        self::STRING_DIRECTORY => self::ID_DIRECTORY,
        self::STRING_CHATCLIENT => self::ID_CHATCLIENT,
        self::STRING_DATAMINER => self::ID_DATAMINER,
        self::STRING_TEXT => self::ID_TEXT,
        self::STRING_ICMP_BLOCKER => self::ID_ICMP_BLOCKER,
        self::STRING_COINMINER => self::ID_COINMINER,
        self::STRING_CODEBLADE => self::ID_CODEBLADE,
        self::STRING_CODEBLASTER => self::ID_CODEBLASTER,
        self::STRING_CODEARMOR => self::ID_CODEARMOR,
        self::STRING_CODESHIELD => self::ID_CODESHIELD,
        self::STRING_SYSMAPPER => self::ID_SYSMAPPER,
        self::STRING_PORTSCANNER => self::ID_PORTSCANNER,
        self::STRING_JACKHAMMER => self::ID_JACKHAMMER,
        self::STRING_WORMER => self::ID_WORMER,
        self::STRING_CODEBREAKER => self::ID_CODEBREAKER,
    ];

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
     * @ORM\Column(type="text")
     * @var string
     */
    protected $description;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $codable;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $executable;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $size;

    /**
     * @ORM\Column(type="integer", options={"default":1})
     * @var int
     */
    protected $executionTime;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $fullblock;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $blocking;

    // ORM

    /**
     * @ORM\ManyToMany(targetEntity="Netrunners\Entity\FilePart")
     */
    protected $fileParts;

    /**
     * @ORM\ManyToMany(targetEntity="Netrunners\Entity\FilePart")
     * @ORM\JoinTable(name="filetype_optionalfilepart")
     */
    protected $optionalFileParts;


    /**
     *
     */
    public function __construct()
    {
        $this->fileParts = new ArrayCollection();
        $this->optionalFileParts = new ArrayCollection();
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
     * @return FileType
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
     * @return FileType
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return FileType
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getCodable()
    {
        return $this->codable;
    }

    /**
     * @param int $codable
     * @return FileType
     */
    public function setCodable($codable)
    {
        $this->codable = $codable;
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
     * @return FileType
     */
    public function setExecutable($executable)
    {
        $this->executable = $executable;
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
     * @return FileType
     */
    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @return int
     */
    public function getExecutionTime()
    {
        return $this->executionTime;
    }

    /**
     * @param int $executionTime
     * @return FileType
     */
    public function setExecutionTime($executionTime)
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getFullblock()
    {
        return $this->fullblock;
    }

    /**
     * @param int $fullblock
     * @return FileType
     */
    public function setFullblock($fullblock)
    {
        $this->fullblock = $fullblock;
        return $this;
    }

    /**
     * @return int
     */
    public function getBlocking()
    {
        return $this->blocking;
    }

    /**
     * @param int $blocking
     * @return FileType
     */
    public function setBlocking($blocking)
    {
        $this->blocking = $blocking;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getFileParts()
    {
        return $this->fileParts;
    }

    /**
     * @param FilePart $filePart
     */
    public function addFilePart(FilePart $filePart)
    {
        if (!$this->fileParts->contains($filePart)) $this->fileParts[] = $filePart;
    }

    /**
     * @param FilePart $filePart
     */
    public function removeFilePart(FilePart $filePart)
    {
        if ($this->fileParts->contains($filePart)) $this->fileParts->removeElement($filePart);
    }

    /**
     * @return mixed
     */
    public function getOptionalFileParts()
    {
        return $this->optionalFileParts;
    }

    /**
     * @param FilePart $filePart
     */
    public function addOptionalFilePart(FilePart $filePart)
    {
        if (!$this->fileParts->contains($filePart)) $this->fileParts[] = $filePart;
    }

    /**
     * @param FilePart $filePart
     */
    public function removeOptionalFilePart(FilePart $filePart)
    {
        if ($this->fileParts->contains($filePart)) $this->fileParts->removeElement($filePart);
    }

}
