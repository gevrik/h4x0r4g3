<?php

/**
 * GameOptionInstance Entity.
 * Stores the game options for all profiles.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\GameOptionInstanceRepository") */
class GameOptionInstance
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $status;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $changed;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\GameOption")
     */
    protected $gameOption;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $profile;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return GameOptionInstance
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return GameOptionInstance
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * @param \DateTime $changed
     * @return GameOptionInstance
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getGameOption()
    {
        return $this->gameOption;
    }

    /**
     * @param mixed $gameOption
     * @return GameOptionInstance
     */
    public function setGameOption($gameOption)
    {
        $this->gameOption = $gameOption;
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
     * @return GameOptionInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
