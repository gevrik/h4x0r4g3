<?php

/**
 * Morph Entity.
 * Archetypes of Morphs.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MorphRepository") */
final class Morph
{

    const ID_CASE = 1;
    const ID_SYNTH = 2;
    const ID_ARACHNOID = 3;
    const ID_FLAT = 4;
    const ID_SPLICER = 5;
    const ID_EXALT = 6;
    const ID_PLEASURE = 7;
    const ID_WORKER = 8;
    const ID_NOVACRAB = 9;
    const ID_AQUANAUT = 10;

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

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\MorphCategory")
     */
    protected $morphCategory;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Morph
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
     * @return Morph
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
     * @return Morph
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    // ORM

    /**
     * @return MorphCategory
     */
    public function getMorphCategory()
    {
        return $this->morphCategory;
    }

    /**
     * @param mixed $morphCategory
     * @return Morph
     */
    public function setMorphCategory($morphCategory)
    {
        $this->morphCategory = $morphCategory;
        return $this;
    }

}
