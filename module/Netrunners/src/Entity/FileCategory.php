<?php

/**
 * FileCategory Entity.
 * All kinds of information about the categories of the files are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Netrunners\Repository\FileCategoryRepository")
 * @ORM\Table(indexes={@ORM\Index(name="search_idx", columns={"name"})})
 */
class FileCategory
{

    const ID_UTILITY = 1;
    const ID_MINER = 2;
    const ID_DEFENSE = 3;
    const ID_EQUIPMENT = 4;
    const ID_FORENSICS = 5;
    const ID_INTRUSION = 6;
    const ID_BYPASS = 7;
    const ID_MALWARE = 8;
    const ID_TRACER = 9;
    const ID_NODE_UPGRADE = 10;
    const ID_EXOTIC = 11;
    const ID_STEALTH = 12;
    const ID_SPAWNER = 13;
    const ID_COMBAT = 14;
    const ID_PASSKEY = 15;
    const ID_TEXT = 16;

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
    protected $researchable;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FileCategory
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
     * @return FileCategory
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
     * @return FileCategory
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getResearchable()
    {
        return $this->researchable;
    }

    /**
     * @param int $researchable
     * @return FileCategory
     */
    public function setResearchable($researchable)
    {
        $this->researchable = $researchable;
        return $this;
    }

}
