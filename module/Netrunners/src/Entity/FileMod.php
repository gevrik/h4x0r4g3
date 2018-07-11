<?php

/**
 * FileMod Entity.
 * Programs can be enhanced by using file-mods. Programs have a property called "slots" which determines, how many
 * mods can be used on a program. This is the lookup table for all available mods. Mods can be coded, but it is very
 * hard to do so. It involves high levels of advanced skills.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FileModRepository") */
class FileMod
{

    const ID_BACKSLASH = 1;
    const ID_INTEGRITY_BOOSTER = 2;
    const ID_TITANKILLER = 3;
    const ID_EXECUTION_BOOSTER = 4;
    const ID_OBFUSCATION = 5;
    const ID_CACHE_MEMORY = 6;

    const STRING_BACKSLASH = 'backslash';
    const STRING_INTEGRITY_BOOSTER = 'integrity-booster';
    const STRING_TITANKILLER = 'titankiller';
    const STRING_EXECUTION_BOOSTER = 'execution-booster';
    const STRING_OBFUSCATION = 'obfuscation-booster';
    const STRING_CACHE_MEMORY = 'cache-memory';

    static $revLookup = [
        self::STRING_BACKSLASH => self::ID_BACKSLASH,
        self::STRING_INTEGRITY_BOOSTER => self::ID_INTEGRITY_BOOSTER,
        self::STRING_TITANKILLER => self::ID_TITANKILLER,
        self::STRING_EXECUTION_BOOSTER => self::ID_EXECUTION_BOOSTER,
        self::STRING_OBFUSCATION => self::ID_OBFUSCATION,
        self::STRING_CACHE_MEMORY => self::ID_CACHE_MEMORY,
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

    // ORM

    /**
     * @ORM\ManyToMany(targetEntity="Netrunners\Entity\FilePart")
     */
    protected $fileParts;


    /**
     *
     */
    public function __construct()
    {
        $this->fileParts = new ArrayCollection();
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
     * @return FileMod
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
     * @return FileMod
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
     * @return FileMod
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

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

}
