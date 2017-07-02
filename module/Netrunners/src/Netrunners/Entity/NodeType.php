<?php

/**
 * NodeType Entity.
 * Systems are made up of many different types of nodes.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\NodeTypeRepository") */
class NodeType
{

    const ID_RAW = 1;
    const ID_IO = 2;
    const ID_MEMORY = 3;
    const ID_STORAGE = 4;
    const ID_CODING = 5;
    const ID_FIREWALL = 6;
    const ID_CPU = 7;
    const ID_MARKET = 8;
    const ID_BB = 9;
    const ID_DATABASE = 10;
    const ID_TERMINAL = 11;
    const ID_PUBLICIO = 12;
    const ID_HOME = 13;
    const ID_AGENT = 14;
    const ID_BANK = 15;
    const ID_INTRUSION = 16;
    const ID_MONITORING = 17;

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
     * @ORM\Column(type="string")
     * @var string
     */
    protected $shortName;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @var string
     */
    protected $description;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"default":0})
     * @var int
     */
    protected $cost;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return NodeType
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
     * @return NodeType
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * @param string $shortName
     * @return NodeType
     */
    public function setShortName($shortName)
    {
        $this->shortName = $shortName;
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
     * @return NodeType
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     * @param int $cost
     * @return NodeType
     */
    public function setCost($cost)
    {
        $this->cost = $cost;
        return $this;
    }

}
