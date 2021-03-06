<?php

/**
 * Connection Entity.
 * Connections connect nodes within their system.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ConnectionRepository") */
class Connection
{

    const TYPE_NORMAL = 0;
    const TYPE_CODEGATE = 1;

    const STRING_NORMAL = "connection";
    const STRING_CODEGATE = "codegate";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

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

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $isOpen;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     **/
    protected $sourceNode;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     **/
    protected $targetNode;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Connection
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return Connection
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
     * @return Connection
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
     * @return Connection
     */
    public function setCreated($created)
    {
        $this->created = $created;
        return $this;
    }

    /**
     * @return int
     */
    public function getisOpen()
    {
        return $this->isOpen;
    }

    /**
     * @param int $isOpen
     * @return Connection
     */
    public function setIsOpen($isOpen)
    {
        $this->isOpen = $isOpen;
        return $this;
    }

    // ORM

    /**
     * @return NULL|Node
     */
    public function getSourceNode()
    {
        return $this->sourceNode;
    }

    /**
     * @param NULL|Node $sourceNode
     * @return Connection
     */
    public function setSourceNode($sourceNode)
    {
        $this->sourceNode = $sourceNode;
        return $this;
    }

    /**
     * @return NULL|Node
     */
    public function getTargetNode()
    {
        return $this->targetNode;
    }

    /**
     * @param NULL|Node $targetNode
     * @return Connection
     */
    public function setTargetNode($targetNode)
    {
        $this->targetNode = $targetNode;
        return $this;
    }

}
