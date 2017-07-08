<?php

/**
 * User Profile Entity.
 * All kinds of information about the user is stored in their profile.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;
use TmoAuth\Entity\User;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileRepository") */
class Profile
{

    const DEFAULT_PROFILE_LOCALE = 'en_US';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $credits;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $snippets;

    /**
     * @ORM\Column(type="integer", options={"default":20}, nullable=true)
     * @var int
     */
    protected $skillPoints;

    /**
     * @ORM\Column(type="integer", options={"default":100})
     * @var int
     */
    protected $eeg;

    /**
     * @ORM\Column(type="integer", options={"default":100})
     * @var int
     */
    protected $willpower;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $securityRating;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $email;

    /**
     * @ORM\Column(type="string", options={"default":"en_US"}, nullable=true)
     * @var string
     */
    protected $locale;

    // ORM

    /**
     * @ORM\OneToOne(targetEntity="TmoAuth\Entity\User", inversedBy="profile")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     */
    protected $currentNode;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     */
    protected $homeNode;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Faction")
     */
    protected $faction;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Group")
     */
    protected $group;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $blade;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $blaster;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $shield;


    /**
     * Constructor for Profile.
     */
    public function __construct() {
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Profile
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getCredits()
    {
        return $this->credits;
    }

    /**
     * @param int $credits
     * @return Profile
     */
    public function setCredits($credits)
    {
        $this->credits = $credits;
        return $this;
    }

    /**
     * @return int
     */
    public function getSnippets()
    {
        return $this->snippets;
    }

    /**
     * @param int $snippets
     * @return Profile
     */
    public function setSnippets($snippets)
    {
        $this->snippets = $snippets;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillPoints()
    {
        return $this->skillPoints;
    }

    /**
     * @param int $skillPoints
     * @return Profile
     */
    public function setSkillPoints($skillPoints)
    {
        $this->skillPoints = $skillPoints;
        return $this;
    }

    /**
     * @return int
     */
    public function getEeg()
    {
        return $this->eeg;
    }

    /**
     * @param int $eeg
     * @return Profile
     */
    public function setEeg($eeg)
    {
        $this->eeg = $eeg;
        return $this;
    }

    /**
     * @return int
     */
    public function getWillpower()
    {
        return $this->willpower;
    }

    /**
     * @param int $willpower
     * @return Profile
     */
    public function setWillpower($willpower)
    {
        $this->willpower = $willpower;
        return $this;
    }

    /**
     * @return int
     */
    public function getSecurityRating()
    {
        return $this->securityRating;
    }

    /**
     * @param int $securityRating
     * @return Profile
     */
    public function setSecurityRating($securityRating)
    {
        $this->securityRating = $securityRating;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return Profile
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param mixed $locale
     * @return Profile
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    // ORM

    /**
     * @return NULL|User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param NULL|User $user
     * @return Profile
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return NULL|Node
     */
    public function getCurrentNode()
    {
        return $this->currentNode;
    }

    /**
     * @param NULL|Node $currentNode
     * @return Profile
     */
    public function setCurrentNode($currentNode)
    {
        $this->currentNode = $currentNode;
        return $this;
    }

    /**
     * @return NULL|Node
     */
    public function getHomeNode()
    {
        return $this->homeNode;
    }

    /**
     * @param NULL|Node $homeNode
     * @return Profile
     */
    public function setHomeNode($homeNode)
    {
        $this->homeNode = $homeNode;
        return $this;
    }

    /**
     * @return NULL|Faction
     */
    public function getFaction()
    {
        return $this->faction;
    }

    /**
     * @param NULL|Faction $faction
     * @return Profile
     */
    public function setFaction($faction)
    {
        $this->faction = $faction;
        return $this;
    }

    /**
     * @return NULL|Group
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param NULL|Group $group
     * @return Profile
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return File|NULL
     */
    public function getBlade()
    {
        return $this->blade;
    }

    /**
     * @param mixed $blade
     * @return Profile
     */
    public function setBlade($blade)
    {
        $this->blade = $blade;
        return $this;
    }

    /**
     * @return File|NULL
     */
    public function getBlaster()
    {
        return $this->blaster;
    }

    /**
     * @param mixed $blaster
     * @return Profile
     */
    public function setBlaster($blaster)
    {
        $this->blaster = $blaster;
        return $this;
    }

    /**
     * @return File|NULL
     */
    public function getShield()
    {
        return $this->shield;
    }

    /**
     * @param mixed $shield
     * @return Profile
     */
    public function setShield($shield)
    {
        $this->shield = $shield;
        return $this;
    }

}
