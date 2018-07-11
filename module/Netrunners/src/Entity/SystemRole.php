<?php

/**
 * SystemRole Entity.
 * Roles allow users to conduct certain actions in the corresponding system.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\SystemRoleRepository") */
class SystemRole
{

    const ALLOWED_FREE_MOVEMENT = 'allowed_free_movement';
    const ALLOWED_BUILDING = 'allowed_building';
    const ALLOWED_HARVESTING = 'allowed_harvesting';
    const ALLOWED_CONNECT = 'allowed_connect';

    const ROLE_ENEMY_ID = 1;
    const ROLE_OWNER_ID = 2;
    const ROLE_GUEST_ID = 3;
    const ROLE_FRIEND_ID = 4;
    const ROLE_HARVESTER_ID = 5;
    const ROLE_ARCHITECT_ID = 6;

    /**
     * @var array
     */
    static $allowedFreeMovement = [
        self::ROLE_OWNER_ID, self::ROLE_GUEST_ID, self::ROLE_FRIEND_ID, self::ROLE_HARVESTER_ID,
        self::ROLE_ARCHITECT_ID
    ];

    /**
     * @var array
     */
    static $allowedBuilding = [
        self::ROLE_OWNER_ID, self::ROLE_ARCHITECT_ID
    ];

    /**
     * @var array
     */
    static $allowedHarvesting = [
        self::ROLE_OWNER_ID, self::ROLE_HARVESTER_ID
    ];

    /**
     * @var array
     */
    static $allowedConnect = [
        self::ROLE_OWNER_ID, self::ROLE_HARVESTER_ID, self::ROLE_ARCHITECT_ID, self::ROLE_FRIEND_ID, self::ROLE_GUEST_ID
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
     * @return SystemRole
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
     * @return SystemRole
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
     * @return SystemRole
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

}
