<?php

/**
 * FilePart Entity.
 * All kinds of information about the parts that make up files (programs) are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FilePartRepository") */
class FilePart
{

    const ID_CONTROLLER = 1;
    const ID_FRONTEND = 2;
    const ID_WHITEHAT = 3;
    const ID_BLACKHAT = 4;
    const ID_CRYPTO = 5;
    const ID_DATABASE = 6;
    const ID_ELECTRONICS = 7;
    const ID_FORENSICS = 8;
    const ID_NETWORK = 9;
    const ID_REVERSE = 10;
    const ID_SOCIAL = 11;

    const STRING_CONTROLLER = 'controller';
    const STRING_FRONTEND = 'frontend';
    const STRING_WHITEHAT = 'whitehat';
    const STRING_BLACKHAT = 'blackhat';
    const STRING_CRYPTO = 'crypto';
    const STRING_DATABASE = 'database';
    const STRING_ELECTRONICS = 'electronics';
    const STRING_FORENSICS = 'forensics';
    const STRING_NETWORK = 'network';
    const STRING_REVERSE = 'reverse';
    const STRING_SOCIAL = 'social';

    static $revLookup = [
        self::STRING_CONTROLLER => self::ID_CONTROLLER,
        self::STRING_BLACKHAT => self::ID_BLACKHAT,
        self::STRING_WHITEHAT => self::ID_WHITEHAT,
        self::STRING_FRONTEND => self::ID_FRONTEND,
        self::STRING_CRYPTO => self::ID_CRYPTO,
        self::STRING_DATABASE => self::ID_DATABASE,
        self::STRING_ELECTRONICS => self::ID_ELECTRONICS,
        self::STRING_FORENSICS => self::ID_FORENSICS,
        self::STRING_NETWORK => self::ID_NETWORK,
        self::STRING_REVERSE => self::ID_REVERSE,
        self::STRING_SOCIAL => self::ID_SOCIAL,
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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FilePart
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
     * @return FilePart
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
     * @return FilePart
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
     * @return FilePart
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
     * @return FilePart
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

}
