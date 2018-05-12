<?php

/**
 * SystemRoleInstance Entity.
 * This keeps track of which profile has which role in a system.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\SystemRoleInstanceRepository") */
class SystemRoleInstance
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
    protected $added;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $expires;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\System")
     */
    protected $system;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\SystemRole")
     */
    protected $systemRole;

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
     * @return SystemRoleInstance
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return SystemRoleInstance
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * @param \DateTime $expires
     * @return SystemRoleInstance
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @param mixed $system
     * @return SystemRoleInstance
     */
    public function setSystem($system)
    {
        $this->system = $system;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSystemRole()
    {
        return $this->systemRole;
    }

    /**
     * @param mixed $systemRole
     * @return SystemRoleInstance
     */
    public function setSystemRole($systemRole)
    {
        $this->systemRole = $systemRole;
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
     * @return SystemRoleInstance
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
