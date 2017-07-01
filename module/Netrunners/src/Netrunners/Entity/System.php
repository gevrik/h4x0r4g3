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

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     **/
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
     * @return System
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
