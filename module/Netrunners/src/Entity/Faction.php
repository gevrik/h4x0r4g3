<?php

/**
 * Faction Entity.
 * All kinds of information about the game's factions are saved in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FactionRepository") */
final class Faction
{

    const ID_AZTECHNOLOGY = 1;
    const ID_GANGERS = 2;
    const ID_EUROCORP = 3;
    const ID_MAFIA = 4;
    const ID_ASIAN_COALITION = 5;
    const ID_YAKUZA = 6;
    const ID_AIVATARS = 7;
    const ID_NETWATCH = 8;

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
    protected $playerRun;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $joinable;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $credits;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $snippets;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $openRecruitment;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Faction
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
     * @return Faction
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
     * @return Faction
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getPlayerRun()
    {
        return $this->playerRun;
    }

    /**
     * @param int $playerRun
     * @return Faction
     */
    public function setPlayerRun($playerRun)
    {
        $this->playerRun = $playerRun;
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
     * @return Faction
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
     * @return Faction
     */
    public function setSnippets($snippets)
    {
        $this->snippets = $snippets;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * @param \DateTime $added
     * @return Faction
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return int
     */
    public function getJoinable()
    {
        return $this->joinable;
    }

    /**
     * @param int $joinable
     * @return Faction
     */
    public function setJoinable($joinable)
    {
        $this->joinable = $joinable;
        return $this;
    }

    /**
     * @return int
     */
    public function getOpenRecruitment()
    {
        return $this->openRecruitment;
    }

    /**
     * @param int $openRecruitment
     * @return Faction
     */
    public function setOpenRecruitment($openRecruitment)
    {
        $this->openRecruitment = $openRecruitment;
        return $this;
    }

}
