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

    const STRING_DIRECTORY = 'directory';
    const STRING_CHATCLIENT = 'chatclient';
    const STRING_DATAMINER = 'dataminer';
    const STRING_TEXT = 'text';

    static $revLookup = [
        self::STRING_DIRECTORY => self::ID_DIRECTORY,
        self::STRING_CHATCLIENT => self::ID_CHATCLIENT,
        self::STRING_DATAMINER => self::ID_DATAMINER,
        self::STRING_TEXT => self::ID_TEXT,
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
