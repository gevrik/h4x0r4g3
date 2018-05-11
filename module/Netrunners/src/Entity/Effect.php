<?php

/**
 * Effect Entity.
 * Effect archetypes for profile effects.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\EffectRepository") */
final class Effect
{

    const ID_STUNNED = 1;
    const ID_DAMAGE_OVER_TIME = 2;

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
    protected $expireTimer;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $dimishTimer;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $diminishValue;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $immuneTimer;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $defaultRating;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Effect
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
     * @return Effect
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
     * @return Effect
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getExpireTimer()
    {
        return $this->expireTimer;
    }

    /**
     * @param int $expireTimer
     * @return Effect
     */
    public function setExpireTimer($expireTimer)
    {
        $this->expireTimer = $expireTimer;
        return $this;
    }

    /**
     * @return int
     */
    public function getDimishTimer()
    {
        return $this->dimishTimer;
    }

    /**
     * @param int $dimishTimer
     * @return Effect
     */
    public function setDimishTimer($dimishTimer)
    {
        $this->dimishTimer = $dimishTimer;
        return $this;
    }

    /**
     * @return string
     */
    public function getDiminishValue()
    {
        return $this->diminishValue;
    }

    /**
     * @param string $diminishValue
     * @return Effect
     */
    public function setDiminishValue($diminishValue)
    {
        $this->diminishValue = $diminishValue;
        return $this;
    }

    /**
     * @return int
     */
    public function getImmuneTimer()
    {
        return $this->immuneTimer;
    }

    /**
     * @param int $immuneTimer
     * @return Effect
     */
    public function setImmuneTimer($immuneTimer)
    {
        $this->immuneTimer = $immuneTimer;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultRating()
    {
        return $this->defaultRating;
    }

    /**
     * @param string $defaultRating
     * @return Effect
     */
    public function setDefaultRating($defaultRating)
    {
        $this->defaultRating = $defaultRating;
        return $this;
    }

}
