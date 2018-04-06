<?php

/**
 * MissionArchetype Entity.
 * Archetypes for missions.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MissionArchetypeRepository") */
class MissionArchetype
{

    const ID_STEAL_FILE = 1;
    const ID_UPLOAD_FILE = 2;
    const ID_PLANT_BACKDOOR = 3;
    const ID_DELETE_FILE = 4;
    const ID_CLEAN_SYSTEM = 5;

    const ID_SUBTYPE_GREY = 0;
    const ID_SUBTYPE_WHITE = 1;
    const ID_SUBTYPE_BLACK = 2;


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
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $subtype;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return MissionArchetype
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
     * @return MissionArchetype
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
     * @return MissionArchetype
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getSubtype()
    {
        return $this->subtype;
    }

    /**
     * @param int $subtype
     * @return MissionArchetype
     */
    public function setSubtype($subtype)
    {
        $this->subtype = $subtype;
        return $this;
    }

}
