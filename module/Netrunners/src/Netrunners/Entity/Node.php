<?php

/**
 * Node Entity.
 * Systems are made up of many different type of nodes.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\NodeRepository") */
class Node
{

    const ID_RAW = 0;
    const ID_IO = 1;
    const ID_MEMORY = 2;
    const ID_STORAGE = 3;
    const ID_SERVICE = 4;
    const ID_FIREWALL = 5;
    const ID_CPU = 5;

    const STRING_RAW = "raw";
    const STRING_IO = "input-output";
    const STRING_MEMORY = "memory";
    const STRING_STORAGE = "storage";
    const STRING_SERVICE = "service";
    const STRING_FIREWALL = "firewall";
    const STRING_CPU = "cpu";

    static $revLookup = [
        self::STRING_RAW => self::ID_RAW,
        self::STRING_IO => self::ID_IO,
        self::STRING_MEMORY => self::ID_MEMORY,
        self::STRING_STORAGE => self::ID_STORAGE,
        self::STRING_SERVICE => self::ID_SERVICE,
        self::STRING_FIREWALL => self::ID_FIREWALL,
        self::STRING_CPU => self::ID_CPU,
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
     * @ORM\Column(type="integer", nullable=true, options={"default":0})
     * @var int
     */
    protected $type;

    /**
     * @ORM\Column(type="integer", options={"default":1})
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $created;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\System")
     **/
    protected $system;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Node
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
     * @return Node
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * @return Node
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
     * @return Node
     */
    public function setLevel($level)
    {
        $this->level = $level;
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
     * @return Node
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @param mixed $system
     * @return Node
     */
    public function setSystem($system)
    {
        $this->system = $system;
        return $this;
    }

}
