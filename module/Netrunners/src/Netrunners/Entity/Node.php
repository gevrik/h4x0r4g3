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
    const ID_CODING = 4;
    const ID_FIREWALL = 5;
    const ID_CPU = 6;
    const ID_MARKET = 7;
    const ID_BB = 8;
    const ID_DATABASE = 9;
    const ID_TERMINAL = 10;
    const ID_PUBLICIO = 11;
    const ID_HOME = 12;
    const ID_AGENT = 13;
    const ID_BANK = 14;
    const ID_INTRUSION = 15;

    const STRING_RAW = "raw";
    const STRING_IO = "input-output";
    const STRING_MEMORY = "memory";
    const STRING_STORAGE = "storage";
    const STRING_CODING = "coding";
    const STRING_FIREWALL = "firewall";
    const STRING_CPU = "cpu";
    const STRING_MARKET = "market";
    const STRING_BB = "bulletin-board";
    const STRING_DATABASE = "database";
    const STRING_TERMINAL = "terminal";
    const STRING_PUBLICIO = "public-io";
    const STRING_HOME = "home";
    const STRING_AGENT = "agent";
    const STRING_BANK = "bank";
    const STRING_INTRUSION = "intrusion";

    static $lookup = [
        self::ID_RAW => self::STRING_RAW,
        self::ID_IO => self::STRING_IO,
        self::ID_MEMORY => self::STRING_MEMORY,
        self::ID_STORAGE => self::STRING_STORAGE,
        self::ID_CODING => self::STRING_CODING,
        self::ID_FIREWALL => self::STRING_FIREWALL,
        self::ID_CPU => self::STRING_CPU,
        self::ID_MARKET => self::STRING_MARKET,
        self::ID_BB => self::STRING_BB,
        self::ID_DATABASE => self::STRING_DATABASE,
        self::ID_TERMINAL => self::STRING_TERMINAL,
        self::ID_PUBLICIO => self::STRING_PUBLICIO,
        self::ID_HOME => self::STRING_HOME,
        self::ID_AGENT => self::STRING_AGENT,
        self::ID_BANK => self::STRING_BANK,
        self::ID_INTRUSION => self::STRING_INTRUSION,
    ];

    static $revLookup = [
        self::STRING_RAW => self::ID_RAW,
        self::STRING_IO => self::ID_IO,
        self::STRING_MEMORY => self::ID_MEMORY,
        self::STRING_STORAGE => self::ID_STORAGE,
        self::STRING_CODING => self::ID_CODING,
        self::STRING_FIREWALL => self::ID_FIREWALL,
        self::STRING_CPU => self::ID_CPU,
        self::STRING_MARKET => self::ID_MARKET,
        self::STRING_BB => self::ID_BB,
        self::STRING_DATABASE => self::ID_DATABASE,
        self::STRING_TERMINAL => self::ID_TERMINAL,
        self::STRING_PUBLICIO => self::ID_PUBLICIO,
        self::STRING_HOME => self::ID_HOME,
        self::STRING_AGENT => self::ID_AGENT,
        self::STRING_BANK => self::ID_BANK,
        self::STRING_INTRUSION => self::ID_INTRUSION,
    ];

    static $data = [
        self::ID_RAW => [
            'cost' => 0,
            'shortname' => 'raw',
        ],
        self::ID_IO => [
            'cost' => 100,
            'shortname' => 'io',
        ],
        self::ID_MEMORY => [
            'cost' => 150,
            'shortname' => 'mem',
        ],
        self::ID_STORAGE => [
            'cost' => 50,
            'shortname' => 'sto',
        ],
        self::ID_CODING => [
            'cost' => 100,
            'shortname' => 'cdi',
        ],
        self::ID_FIREWALL => [
            'cost' => 1000,
            'shortname' => 'fw',
        ],
        self::ID_CPU => [
            'cost' => 250,
            'shortname' => 'cpu',
        ],
        self::ID_MARKET => [
            'cost' => 100,
            'shortname' => 'mrk',
        ],
        self::ID_BB => [
            'cost' => 100,
            'shortname' => 'bb',
        ],
        self::ID_DATABASE => [
            'cost' => 100,
            'shortname' => 'db',
        ],
        self::ID_TERMINAL => [
            'cost' => 100,
            'shortname' => 'trm',
        ],
        self::ID_PUBLICIO => [
            'cost' => 100,
            'shortname' => 'pio',
        ],
        self::ID_HOME => [
            'cost' => 250,
            'shortname' => 'hme',
        ],
        self::ID_AGENT => [
            'cost' => 100,
            'shortname' => 'agn',
        ],
        self::ID_BANK => [
            'cost' => 100,
            'shortname' => 'bnk',
        ],
        self::ID_INTRUSION => [
            'cost' => 100,
            'shortname' => 'int',
        ],
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
     * @ORM\Column(type="text", nullable=true)
     * @var string
     */
    protected $description;

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
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return Node
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
