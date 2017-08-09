<?php

/**
 * Sytem Entity.
 * All kinds of information about a user's system is stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\SystemRepository") */
class System
{

    const DEFAULT_MAX_SYSTEM_SIZE = 64;

    const GROUP_MAX_SYSTEM_SIZE = 128;

    const FACTION_MAX_SYSTEM_SIZE = 256;

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
     * @ORM\Column(type="string")
     * @var string
     */
    protected $addy;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $alertLevel;

    /**
     * @ORM\Column(type="integer", options={"default":64}, nullable=true)
     * @var int
     */
    protected $maxSize;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $noclaim;

    /**
     * @ORM\Column(type="integer", options={"default":100}, nullable=true)
     * @var int
     */
    protected $integrity;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     **/
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     **/
    protected $faction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Group")
     **/
    protected $group;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return System
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
     * @return System
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getAddy()
    {
        return $this->addy;
    }

    /**
     * @param string $addy
     * @return System
     */
    public function setAddy($addy)
    {
        $this->addy = $addy;
        return $this;
    }

    /**
     * @return int
     */
    public function getAlertLevel()
    {
        return $this->alertLevel;
    }

    /**
     * @param int $alertLevel
     * @return System
     */
    public function setAlertLevel($alertLevel)
    {
        $this->alertLevel = $alertLevel;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxSize()
    {
        return $this->maxSize;
    }

    /**
     * @param int $maxSize
     * @return System
     */
    public function setMaxSize($maxSize)
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    /**
     * @return int
     */
    public function getNoclaim()
    {
        return $this->noclaim;
    }

    /**
     * @param int $noclaim
     * @return System
     */
    public function setNoclaim($noclaim)
    {
        $this->noclaim = $noclaim;
        return $this;
    }

    /**
     * @return int
     */
    public function getIntegrity()
    {
        return $this->integrity;
    }

    /**
     * @param int $integrity
     * @return System
     */
    public function setIntegrity($integrity)
    {
        $this->integrity = $integrity;
        return $this;
    }

    // ORM

    /**
     * @return Profile|null
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return System
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
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
     * @return System
     */
    public function setFaction($faction)
    {
        $this->faction = $faction;
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
     * @return System
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

}
