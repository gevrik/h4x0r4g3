<?php

/**
 * GroupRoleInstance Entity.
 * This keeps track of which profile has which role in a group.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\GroupRoleInstanceRepository") */
final class GroupRoleInstance
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
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Group")
     */
    protected $group;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\GroupRole")
     */
    protected $groupRole;

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
     * @return GroupRoleInstance
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
     * @return GroupRoleInstance
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param mixed $group
     * @return GroupRoleInstance
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return GroupRole
     */
    public function getGroupRole()
    {
        return $this->groupRole;
    }

    /**
     * @param mixed $groupRole
     * @return GroupRoleInstance
     */
    public function setGroupRole($groupRole)
    {
        $this->groupRole = $groupRole;
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
     * @return GroupRoleInstance
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
     * @return GroupRoleInstance
     */
    public function setChanger($changer)
    {
        $this->changer = $changer;
        return $this;
    }

}
