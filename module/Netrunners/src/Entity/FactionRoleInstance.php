<?php

/**
 * FactionRoleInstance Entity.
 * This keeps track of which profile has which role in a faction.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FactionRoleInstanceRepository") */
final class FactionRoleInstance
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

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $faction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FactionRole")
     */
    protected $factionRole;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $member;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $changer;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FactionRoleInstance
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
     * @return FactionRoleInstance
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFaction()
    {
        return $this->faction;
    }

    /**
     * @param mixed $faction
     * @return FactionRoleInstance
     */
    public function setFaction($faction)
    {
        $this->faction = $faction;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFactionRole()
    {
        return $this->factionRole;
    }

    /**
     * @param mixed $factionRole
     * @return FactionRoleInstance
     */
    public function setFactionRole($factionRole)
    {
        $this->factionRole = $factionRole;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMember()
    {
        return $this->member;
    }

    /**
     * @param mixed $member
     * @return FactionRoleInstance
     */
    public function setMember($member)
    {
        $this->member = $member;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChanger()
    {
        return $this->changer;
    }

    /**
     * @param mixed $changer
     * @return FactionRoleInstance
     */
    public function setChanger($changer)
    {
        $this->changer = $changer;
        return $this;
    }

}
