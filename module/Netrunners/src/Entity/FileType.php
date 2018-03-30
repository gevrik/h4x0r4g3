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

/**
 * @ORM\Entity(repositoryClass="Netrunners\Repository\FileTypeRepository")
 * @ORM\Table(indexes={@ORM\Index(name="search_idx", columns={"name"})})
 */
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
    const ID_CUSTOM_IDE = 16;
    const ID_SKIMMER = 17;
    const ID_BLOCKCHAINER = 18;
    const ID_IO_TRACER = 19;
    const ID_OBFUSCATOR = 20;
    const ID_CLOAK = 21;
    const ID_LOG_ENCRYPTOR = 22;
    const ID_LOG_DECRYPTOR = 23;
    const ID_PHISHER = 24;
    const ID_BEARTRAP = 25;
    const ID_WILDERSPACE_HUB_PORTAL = 26;
    const ID_RESEARCHER = 27;
    const ID_SIPHON = 28;
    const ID_BACKDOOR = 29;
    const ID_MEDKIT = 30;
    const ID_GUARD_SPAWNER = 31;
    const ID_PROXIFIER = 32;
    const ID_KICKER = 33;
    const ID_BREAKOUT = 34;
    const ID_SMOKESCREEN = 35;
    const ID_VENOM = 36;
    const ID_ANTIDOTE = 37;
    const ID_PUNCHER = 38;
    const ID_STIMULANT = 39;
    const ID_PASSKEY = 40;
    const ID_SPIDER_SPAWNER = 41;

    const STRING_DIRECTORY = 'directory';
    const STRING_CHATCLIENT = 'chatclient';
    const STRING_DATAMINER = 'dataminer';
    const STRING_TEXT = 'text';
    const STRING_ICMP_BLOCKER = 'icmp-blocker';
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
    const STRING_CUSTOM_IDE = 'custom-ide';
    const STRING_SKIMMER = 'skimmer';
    const STRING_BLOCKCHAINER = 'blockchainer';
    const STRING_IO_TRACER = 'io-tracer';
    const STRING_OBFUSCATOR = 'obfuscator';
    const STRING_CLOAK = 'cloak';
    const STRING_LOG_ENCRYPTOR = 'log-encryptor';
    const STRING_LOG_DECRYPTOR = 'log-decryptor';
    const STRING_PHISHER = 'phisher';
    const STRING_BEARTRAP = 'beartrap';
    const STRING_WILDERSPACE_HUB_PORTAL = 'wilderspace-hub-portal';
    const STRING_RESEARCHER = 'researcher';
    const STRING_SIPHON = 'siphon';
    const STRING_BACKDOOR = 'backdoor';
    const STRING_MEDKIT = 'medkit';
    const STRING_GUARD_SPAWNER = 'guard-spawner';
    const STRING_PROXIFIER = 'proxifier';
    const STRING_KICKER = 'kicker';
    const STRING_BREAKOUT = 'breakout';
    const STRING_SMOKESCREEN = 'smokescreen';
    const STRING_VENOM = 'venom';
    const STRING_ANTIDOTE = 'antidote';
    const STRING_PUNCHER = 'puncher';
    const STRING_STIMULANT = 'stimulant';
    const STRING_PASSKEY = 'passkey';
    const STRING_SPIDER_SPAWNER = 'spider-spawner';

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
        self::STRING_CUSTOM_IDE => self::ID_CUSTOM_IDE,
        self::STRING_SKIMMER => self::ID_SKIMMER,
        self::STRING_BLOCKCHAINER => self::ID_BLOCKCHAINER,
        self::STRING_IO_TRACER => self::ID_IO_TRACER,
        self::STRING_OBFUSCATOR => self::ID_OBFUSCATOR,
        self::STRING_CLOAK => self::ID_CLOAK,
        self::STRING_LOG_ENCRYPTOR => self::ID_LOG_ENCRYPTOR,
        self::STRING_LOG_DECRYPTOR => self::ID_LOG_DECRYPTOR,
        self::STRING_PHISHER => self::ID_PHISHER,
        self::STRING_BEARTRAP => self::ID_BEARTRAP,
        self::STRING_WILDERSPACE_HUB_PORTAL => self::ID_WILDERSPACE_HUB_PORTAL,
        self::STRING_RESEARCHER => self::ID_RESEARCHER,
        self::STRING_SIPHON => self::ID_SIPHON,
        self::STRING_BACKDOOR => self::ID_BACKDOOR,
        self::STRING_MEDKIT => self::ID_MEDKIT,
        self::STRING_GUARD_SPAWNER => self::ID_GUARD_SPAWNER,
        self::STRING_PROXIFIER => self::ID_PROXIFIER,
        self::STRING_KICKER => self::ID_KICKER,
        self::STRING_BREAKOUT => self::ID_BREAKOUT,
        self::STRING_SMOKESCREEN => self::ID_SMOKESCREEN,
        self::STRING_VENOM => self::ID_VENOM,
        self::STRING_ANTIDOTE => self::ID_ANTIDOTE,
        self::STRING_PUNCHER => self::ID_PUNCHER,
        self::STRING_STIMULANT => self::ID_STIMULANT,
        self::STRING_PASSKEY => self::ID_PASSKEY,
        self::STRING_SPIDER_SPAWNER => self::ID_SPIDER_SPAWNER,
    ];

    const SUBTYPE_ARMOR_HEAD = 1;
    const SUBTYPE_ARMOR_UPPER_ARM = 2;
    const SUBTYPE_ARMOR_LOWER_ARM = 3;
    const SUBTYPE_ARMOR_HANDS = 4;
    const SUBTYPE_ARMOR_TORSO = 5;
    const SUBTYPE_ARMOR_LEGS = 6;
    const SUBTYPE_ARMOR_SHOES = 7;
    const SUBTYPE_ARMOR_SHOULDERS = 8;

    const SUBTYPE_ARMOR_HEAD_STRING = 'head';
    const SUBTYPE_ARMOR_UPPER_ARM_STRING = 'upper-arms';
    const SUBTYPE_ARMOR_LOWER_ARM_STRING = 'lower-arms';
    const SUBTYPE_ARMOR_HANDS_STRING = 'hands';
    const SUBTYPE_ARMOR_TORSO_STRING = 'torso';
    const SUBTYPE_ARMOR_LEGS_STRING = 'legs';
    const SUBTYPE_ARMOR_SHOES_STRING = 'shoes';
    const SUBTYPE_ARMOR_SHOULDERS_STRING = 'shoulders';

    static $armorSubtypeLookup = [
        self::SUBTYPE_ARMOR_HEAD => self::SUBTYPE_ARMOR_HEAD_STRING,
        self::SUBTYPE_ARMOR_UPPER_ARM => self::SUBTYPE_ARMOR_UPPER_ARM_STRING,
        self::SUBTYPE_ARMOR_LOWER_ARM => self::SUBTYPE_ARMOR_LOWER_ARM_STRING,
        self::SUBTYPE_ARMOR_HANDS => self::SUBTYPE_ARMOR_HANDS_STRING,
        self::SUBTYPE_ARMOR_TORSO => self::SUBTYPE_ARMOR_TORSO_STRING,
        self::SUBTYPE_ARMOR_LEGS => self::SUBTYPE_ARMOR_LEGS_STRING,
        self::SUBTYPE_ARMOR_SHOES => self::SUBTYPE_ARMOR_SHOES_STRING,
        self::SUBTYPE_ARMOR_SHOULDERS => self::SUBTYPE_ARMOR_SHOULDERS_STRING
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

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $stealthing;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $needRecipe;

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
     * @ORM\ManyToMany(targetEntity="Netrunners\Entity\FileCategory")
     */
    protected $fileCategories;


    /**
     *
     */
    public function __construct()
    {
        $this->fileParts = new ArrayCollection();
        $this->optionalFileParts = new ArrayCollection();
        $this->fileCategories = new ArrayCollection();
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

    /**
     * @return int
     */
    public function getStealthing()
    {
        return $this->stealthing;
    }

    /**
     * @param int $stealthing
     * @return FileType
     */
    public function setStealthing($stealthing)
    {
        $this->stealthing = $stealthing;
        return $this;
    }

    /**
     * @return int
     */
    public function getNeedRecipe()
    {
        return $this->needRecipe;
    }

    /**
     * @param int $needRecipe
     * @return FileType
     */
    public function setNeedRecipe($needRecipe)
    {
        $this->needRecipe = $needRecipe;
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

    /**
     * @return mixed
     */
    public function getFileCategories()
    {
        return $this->fileCategories;
    }

    /**
     * @param FileCategory $fileCategory
     */
    public function addFileCategory(FileCategory $fileCategory)
    {
        if (!$this->fileCategories->contains($fileCategory)) $this->fileCategories[] = $fileCategory;
    }

    /**
     * @param FileCategory $fileCategory
     */
    public function removeFileCategory(FileCategory $fileCategory)
    {
        if ($this->fileCategories->contains($fileCategory)) $this->fileCategories->removeElement($fileCategory);
    }

}
