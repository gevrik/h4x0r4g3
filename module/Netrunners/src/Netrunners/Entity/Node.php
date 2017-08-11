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

/**
 * @ORM\Entity(repositoryClass="Netrunners\Repository\NodeRepository")
 * @ORM\Table(indexes={@ORM\Index(name="search_idx", columns={"name"})})
 */
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
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $nomob;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $nopvp;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $noclaim;

    /**
     * @ORM\Column(type="integer", options={"default":100}, nullable=true)
     * @var int
     */
    protected $integrity;

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
     * Used for wilderspace claimage, normally profile is retrieved via system.
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     **/
    protected $profile;

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
     * @return int
     */
    public function getNomob()
    {
        return $this->nomob;
    }

    /**
     * @param int $nomob
     * @return Node
     */
    public function setNomob($nomob)
    {
        $this->nomob = $nomob;
        return $this;
    }

    /**
     * @return int
     */
    public function getNopvp()
    {
        return $this->nopvp;
    }

    /**
     * @param int $nopvp
     * @return Node
     */
    public function setNopvp($nopvp)
    {
        $this->nopvp = $nopvp;
        return $this;
    }

    /**
     * @return int
     */
    public function getNoclaim()
    {
        return $this->noclaim;
    }

    /**
     * @param int $noclaim
     * @return Node
     */
    public function setNoclaim($noclaim)
    {
        $this->noclaim = $noclaim;
        return $this;
    }

    /**
     * @return int
     */
    public function getIntegrity()
    {
        return $this->integrity;
    }

    /**
     * @param int $integrity
     * @return Node
     */
    public function setIntegrity($integrity)
    {
        $this->integrity = $integrity;
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
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return Node
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
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
