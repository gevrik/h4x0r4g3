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
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\NodeType")
     **/
    protected $nodeType;


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
     * @return NULL|System
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @param NULL|System $system
     * @return Node
     */
    public function setSystem($system)
    {
        $this->system = $system;
        return $this;
    }

    /**
     * @return NULL|NodeType
     */
    public function getNodeType()
    {
        return $this->nodeType;
    }

    /**
     * @param NULL|NodeType $nodeType
     * @return Node
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;
        return $this;
    }

}
