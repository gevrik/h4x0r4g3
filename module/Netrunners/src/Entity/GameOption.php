<?php

/**
 * GameOption Entity.
 * These are the game options that players can toggle for the profile. For instance, they can turn off music or
 * sound effects.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\GameOptionRepository") */
class GameOption
{

    const ID_SOUND = 1;
    const ID_MUSIC = 2;
    const ID_SURVEY = 3;
    const ID_BGOPACITY = 4;
    const ID_SHOW_NEWBIE_CHAT = 5;

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
    protected $defaultStatus;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $defaultValue;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return GameOption
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
     * @return GameOption
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
     * @return GameOption
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultStatus()
    {
        return $this->defaultStatus;
    }

    /**
     * @param string $defaultStatus
     * @return GameOption
     */
    public function setDefaultStatus($defaultStatus)
    {
        $this->defaultStatus = $defaultStatus;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @param string $defaultValue
     * @return GameOption
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

}
