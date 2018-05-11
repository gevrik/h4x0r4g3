<?php

/**
 * Milkrun Entity.
 * A Milkrun is a small mission that involves a mini-game. This entity holds the archetypes for these milkruns.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MilkrunRepository") */
final class Milkrun
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
     * @ORM\Column(type="text")
     * @var string
     */
    protected $description;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $credits;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $snippets;

    /**
     * @ORM\Column(type="integer", options={"default":1})
     * @var int
     */
    protected $level;

    /**
     * @ORM\Column(type="integer", options={"default":3600}, nullable=true)
     * @var int
     */
    protected $timer;

    // ORM

    /**
     * Determines which faction role can take this milkrun.
     * If this is not set, then every role in the faction can take on the milkrun.
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FactionRole")
     */
    protected $factionRole;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Milkrun
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
     * @return Milkrun
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
     * @return Milkrun
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getCredits()
    {
        return $this->credits;
    }

    /**
     * @param int $credits
     * @return Milkrun
     */
    public function setCredits($credits)
    {
        $this->credits = $credits;
        return $this;
    }

    /**
     * @return int
     */
    public function getSnippets()
    {
        return $this->snippets;
    }

    /**
     * @param int $snippets
     * @return Milkrun
     */
    public function setSnippets($snippets)
    {
        $this->snippets = $snippets;
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
     * @return Milkrun
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return int
     */
    public function getTimer()
    {
        return $this->timer;
    }

    /**
     * @param int $timer
     * @return Milkrun
     */
    public function setTimer($timer)
    {
        $this->timer = $timer;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getFactionRole()
    {
        return $this->factionRole;
    }

    /**
     * @param mixed $factionRole
     * @return Milkrun
     */
    public function setFactionRole($factionRole)
    {
        $this->factionRole = $factionRole;
        return $this;
    }

}
