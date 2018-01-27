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
use TwistyPassages\Entity\Story;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileRepository") */
class Profile
{

    const DEFAULT_PROFILE_LOCALE = 'en_US';

    const SECURITY_RATING_MAX = 100;

    const SECURITY_RATING_NETWATCH_THRESHOLD = 80;

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
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $bankBalance;

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

    /**
     * @ORM\Column(type="string", options={"default":"0.6"}, nullable=true)
     * @var string
     */
    protected $bgopacity;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $stealthing;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $factionJoinBlockDate;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $completedMilkruns;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $faileddMilkruns;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $completedMissions;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $failedMissions;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $currentResourceId;

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
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $headArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $shoulderArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $upperArmArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $lowerArmArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $handArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $torsoArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $legArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $shoesArmor;

    /**
     * @ORM\OneToOne(targetEntity="Netrunners\Entity\MilkrunAivatarInstance")
     */
    protected $defaultMilkrunAivatar;

    /**
     * @ORM\ManyToOne(targetEntity="TwistyPassages\Entity\Story")
     */
    protected $currentPlayStory;

    /**
     * @ORM\ManyToOne(targetEntity="TwistyPassages\Entity\Story")
     */
    protected $currentEditorStory;


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
     * @return int
     */
    public function getBankBalance()
    {
        return $this->bankBalance;
    }

    /**
     * @param int $bankBalance
     * @return Profile
     */
    public function setBankBalance($bankBalance)
    {
        $this->bankBalance = $bankBalance;
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

    /**
     * @return string
     */
    public function getBgopacity()
    {
        return $this->bgopacity;
    }

    /**
     * @param string $bgopacity
     * @return Profile
     */
    public function setBgopacity($bgopacity)
    {
        $this->bgopacity = $bgopacity;
        return $this;
    }

    /**
     * @return int
     */
    public function getStealthing()
    {
        return $this->stealthing;
    }

    /**
     * @param int $stealthing
     * @return Profile
     */
    public function setStealthing($stealthing)
    {
        $this->stealthing = $stealthing;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getFactionJoinBlockDate()
    {
        return $this->factionJoinBlockDate;
    }

    /**
     * @param \DateTime $factionJoinBlockDate
     * @return Profile
     */
    public function setFactionJoinBlockDate($factionJoinBlockDate)
    {
        $this->factionJoinBlockDate = $factionJoinBlockDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getCompletedMilkruns()
    {
        return $this->completedMilkruns;
    }

    /**
     * @param int $completedMilkruns
     * @return Profile
     */
    public function setCompletedMilkruns($completedMilkruns)
    {
        $this->completedMilkruns = $completedMilkruns;
        return $this;
    }

    /**
     * @return int
     */
    public function getFaileddMilkruns()
    {
        return $this->faileddMilkruns;
    }

    /**
     * @param int $faileddMilkruns
     * @return Profile
     */
    public function setFaileddMilkruns($faileddMilkruns)
    {
        $this->faileddMilkruns = $faileddMilkruns;
        return $this;
    }

    /**
     * @return int
     */
    public function getCompletedMissions()
    {
        return $this->completedMissions;
    }

    /**
     * @param int $completedMissions
     * @return Profile
     */
    public function setCompletedMissions($completedMissions)
    {
        $this->completedMissions = $completedMissions;
        return $this;
    }

    /**
     * @return int
     */
    public function getFailedMissions()
    {
        return $this->failedMissions;
    }

    /**
     * @param int $failedMissions
     * @return Profile
     */
    public function setFailedMissions($failedMissions)
    {
        $this->failedMissions = $failedMissions;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentResourceId()
    {
        return $this->currentResourceId;
    }

    /**
     * @param int $currentResourceId
     * @return Profile
     */
    public function setCurrentResourceId($currentResourceId)
    {
        $this->currentResourceId = $currentResourceId;
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

    /**
     * @return mixed
     */
    public function getHeadArmor()
    {
        return $this->headArmor;
    }

    /**
     * @param mixed $headArmor
     * @return Profile
     */
    public function setHeadArmor($headArmor)
    {
        $this->headArmor = $headArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getShoulderArmor()
    {
        return $this->shoulderArmor;
    }

    /**
     * @param mixed $shoulderArmor
     * @return Profile
     */
    public function setShoulderArmor($shoulderArmor)
    {
        $this->shoulderArmor = $shoulderArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpperArmArmor()
    {
        return $this->upperArmArmor;
    }

    /**
     * @param mixed $upperArmArmor
     * @return Profile
     */
    public function setUpperArmArmor($upperArmArmor)
    {
        $this->upperArmArmor = $upperArmArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLowerArmArmor()
    {
        return $this->lowerArmArmor;
    }

    /**
     * @param mixed $lowerArmArmor
     * @return Profile
     */
    public function setLowerArmArmor($lowerArmArmor)
    {
        $this->lowerArmArmor = $lowerArmArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHandArmor()
    {
        return $this->handArmor;
    }

    /**
     * @param mixed $handArmor
     * @return Profile
     */
    public function setHandArmor($handArmor)
    {
        $this->handArmor = $handArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTorsoArmor()
    {
        return $this->torsoArmor;
    }

    /**
     * @param mixed $torsoArmor
     * @return Profile
     */
    public function setTorsoArmor($torsoArmor)
    {
        $this->torsoArmor = $torsoArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLegArmor()
    {
        return $this->legArmor;
    }

    /**
     * @param mixed $legArmor
     * @return Profile
     */
    public function setLegArmor($legArmor)
    {
        $this->legArmor = $legArmor;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getShoesArmor()
    {
        return $this->shoesArmor;
    }

    /**
     * @param mixed $shoesArmor
     * @return Profile
     */
    public function setShoesArmor($shoesArmor)
    {
        $this->shoesArmor = $shoesArmor;
        return $this;
    }

    /**
     * @return MilkrunAivatarInstance|null
     */
    public function getDefaultMilkrunAivatar()
    {
        return $this->defaultMilkrunAivatar;
    }

    /**
     * @param mixed $defaultMilkrunAivatar
     * @return Profile
     */
    public function setDefaultMilkrunAivatar($defaultMilkrunAivatar)
    {
        $this->defaultMilkrunAivatar = $defaultMilkrunAivatar;
        return $this;
    }

    /**
     * @return null|Story
     */
    public function getCurrentPlayStory()
    {
        return $this->currentPlayStory;
    }

    /**
     * @param Story $currentPlayStory
     * @return Profile
     */
    public function setCurrentPlayStory($currentPlayStory)
    {
        $this->currentPlayStory = $currentPlayStory;
        return $this;
    }

    /**
     * @return null|Story
     */
    public function getCurrentEditorStory()
    {
        return $this->currentEditorStory;
    }

    /**
     * @param Story $currentEditorStory
     * @return Profile
     */
    public function setCurrentEditorStory($currentEditorStory)
    {
        $this->currentEditorStory = $currentEditorStory;
        return $this;
    }

}
