<?php

/**
 * Notification Entity.
 * All kinds of information about player notifications are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\NotificationRepository") */
class Notification
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
    protected $subject;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $severity;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $sentDateTime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $readDateTime;

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
     * @return Notification
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return Notification
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * @param string $severity
     * @return Notification
     */
    public function setSeverity($severity)
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getSentDateTime()
    {
        return $this->sentDateTime;
    }

    /**
     * @param \DateTime $sentDateTime
     * @return Notification
     */
    public function setSentDateTime($sentDateTime)
    {
        $this->sentDateTime = $sentDateTime;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getReadDateTime()
    {
        return $this->readDateTime;
    }

    /**
     * @param \DateTime $readDateTime
     * @return Notification
     */
    public function setReadDateTime($readDateTime)
    {
        $this->readDateTime = $readDateTime;
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
     * @return Notification
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
