<?php

/**
 * PlaySession Entity.
 * Keeps track of how long each profile has spent online.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\PlaySessionRepository") */
final class PlaySession
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $start;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $end;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $ipAddy;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $socketId;

    // ORM

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
     * @return PlaySession
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param \DateTime $start
     * @return PlaySession
     */
    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @param \DateTime $end
     * @return PlaySession
     */
    public function setEnd($end)
    {
        $this->end = $end;
        return $this;
    }

    /**
     * @return string
     */
    public function getIpAddy()
    {
        return $this->ipAddy;
    }

    /**
     * @param string $ipAddy
     * @return PlaySession
     */
    public function setIpAddy($ipAddy)
    {
        $this->ipAddy = $ipAddy;
        return $this;
    }

    /**
     * @return string
     */
    public function getSocketId()
    {
        return $this->socketId;
    }

    /**
     * @param string $socketId
     * @return PlaySession
     */
    public function setSocketId($socketId)
    {
        $this->socketId = $socketId;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return PlaySession
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
