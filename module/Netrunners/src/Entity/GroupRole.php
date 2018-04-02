<?php

/**
 * GroupRole Entity.
 * When a player joins a player-run group, they will be assigned roles (ranks). Ranks affect the standing and allowed
 * actions for a player in a group.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\GroupRoleRepository") */
class GroupRole
{

    const ROLE_LEADER_ID = 1;
    const ROLE_COUNCIL_ID = 2;
    const ROLE_ADMIN_OFFICE_ID = 3;
    const ROLE_COMM_OFFICE_ID = 4;
    const ROLE_RECRUITMENT_ID = 5;
    const ROLE_BANK_MANAGER_ID = 6;
    const ROLE_FOUNDER_ID = 7;
    const ROLE_MEMBER_ID = 8;
    const ROLE_NEWBIE_ID = 9;

    /**
     * @var array
     */
    static $allowedToggleRecruitment = [
        self::ROLE_LEADER_ID, self::ROLE_FOUNDER_ID, self::ROLE_RECRUITMENT_ID
    ];

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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return GroupRole
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
     * @return GroupRole
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
     * @return GroupRole
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

}
