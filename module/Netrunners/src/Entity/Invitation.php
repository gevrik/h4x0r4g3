<?php

/**
 * Invitation Entity.
 * Player registrations require an invitation code. Existing players gain invitation codes as rewards for
 * certain achievements.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Netrunners\Repository\InvitationRepository")
 * @ORM\Table(name="Invitation_Code")
 */
class Invitation
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
    protected $code;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $given;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $used;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $special;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $givenTo;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $usedBy;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Invitation
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return Invitation
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getGiven()
    {
        return $this->given;
    }

    /**
     * @param \DateTime $given
     * @return Invitation
     */
    public function setGiven($given)
    {
        $this->given = $given;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUsed()
    {
        return $this->used;
    }

    /**
     * @param \DateTime $used
     * @return Invitation
     */
    public function setUsed($used)
    {
        $this->used = $used;
        return $this;
    }

    /**
     * @return int
     */
    public function getSpecial()
    {
        return $this->special;
    }

    /**
     * @param int $special
     * @return Invitation
     */
    public function setSpecial($special)
    {
        $this->special = $special;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getGivenTo()
    {
        return $this->givenTo;
    }

    /**
     * @param mixed $givenTo
     * @return Invitation
     */
    public function setGivenTo($givenTo)
    {
        $this->givenTo = $givenTo;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUsedBy()
    {
        return $this->usedBy;
    }

    /**
     * @param mixed $usedBy
     * @return Invitation
     */
    public function setUsedBy($usedBy)
    {
        $this->usedBy = $usedBy;
        return $this;
    }

}
