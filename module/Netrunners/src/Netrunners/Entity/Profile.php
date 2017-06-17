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

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileRepository") */
class Profile
{

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
     * @ORM\Column(type="integer", options={"default":30}, nullable=true)
     * @var int
     */
    protected $skillCoding;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillBlackhat;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillWhitehat;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillNetworking;

    /**
     * @ORM\Column(type="integer", options={"default":30}, nullable=true)
     * @var int
     */
    protected $skillComputing;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillDatabases;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillElectronics;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillForensics;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillSocialEngineering;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillCryptography;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillReverseEngineering;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillAdvancedNetworking;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $skillAdvancedCoding;

    /**
     * @ORM\Column(type="integer", options={"default":20}, nullable=true)
     * @var int
     */
    protected $skillPoints;

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
    public function getSkillCoding()
    {
        return $this->skillCoding;
    }

    /**
     * @param int $skillCoding
     * @return Profile
     */
    public function setSkillCoding($skillCoding)
    {
        $this->skillCoding = $skillCoding;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillBlackhat()
    {
        return $this->skillBlackhat;
    }

    /**
     * @param int $skillBlackhat
     * @return Profile
     */
    public function setSkillBlackhat($skillBlackhat)
    {
        $this->skillBlackhat = $skillBlackhat;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillWhitehat()
    {
        return $this->skillWhitehat;
    }

    /**
     * @param int $skillWhitehat
     * @return Profile
     */
    public function setSkillWhitehat($skillWhitehat)
    {
        $this->skillWhitehat = $skillWhitehat;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillNetworking()
    {
        return $this->skillNetworking;
    }

    /**
     * @param int $skillNetworking
     * @return Profile
     */
    public function setSkillNetworking($skillNetworking)
    {
        $this->skillNetworking = $skillNetworking;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillComputing()
    {
        return $this->skillComputing;
    }

    /**
     * @param int $skillComputing
     * @return Profile
     */
    public function setSkillComputing($skillComputing)
    {
        $this->skillComputing = $skillComputing;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillDatabases()
    {
        return $this->skillDatabases;
    }

    /**
     * @param int $skillDatabases
     * @return Profile
     */
    public function setSkillDatabases($skillDatabases)
    {
        $this->skillDatabases = $skillDatabases;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillElectronics()
    {
        return $this->skillElectronics;
    }

    /**
     * @param int $skillElectronics
     * @return Profile
     */
    public function setSkillElectronics($skillElectronics)
    {
        $this->skillElectronics = $skillElectronics;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillForensics()
    {
        return $this->skillForensics;
    }

    /**
     * @param int $skillForensics
     * @return Profile
     */
    public function setSkillForensics($skillForensics)
    {
        $this->skillForensics = $skillForensics;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillSocialEngineering()
    {
        return $this->skillSocialEngineering;
    }

    /**
     * @param int $skillSocialEngineering
     * @return Profile
     */
    public function setSkillSocialEngineering($skillSocialEngineering)
    {
        $this->skillSocialEngineering = $skillSocialEngineering;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillCryptography()
    {
        return $this->skillCryptography;
    }

    /**
     * @param int $skillCryptography
     * @return Profile
     */
    public function setSkillCryptography($skillCryptography)
    {
        $this->skillCryptography = $skillCryptography;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillReverseEngineering()
    {
        return $this->skillReverseEngineering;
    }

    /**
     * @param int $skillReverseEngineering
     * @return Profile
     */
    public function setSkillReverseEngineering($skillReverseEngineering)
    {
        $this->skillReverseEngineering = $skillReverseEngineering;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillAdvancedNetworking()
    {
        return $this->skillAdvancedNetworking;
    }

    /**
     * @param int $skillAdvancedNetworking
     * @return Profile
     */
    public function setSkillAdvancedNetworking($skillAdvancedNetworking)
    {
        $this->skillAdvancedNetworking = $skillAdvancedNetworking;
        return $this;
    }

    /**
     * @return int
     */
    public function getSkillAdvancedCoding()
    {
        return $this->skillAdvancedCoding;
    }

    /**
     * @param int $skillAdvancedCoding
     * @return Profile
     */
    public function setSkillAdvancedCoding($skillAdvancedCoding)
    {
        $this->skillAdvancedCoding = $skillAdvancedCoding;
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

    // ORM

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     * @return Profile
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurrentNode()
    {
        return $this->currentNode;
    }

    /**
     * @param mixed $currentNode
     * @return Profile
     */
    public function setCurrentNode($currentNode)
    {
        $this->currentNode = $currentNode;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHomeNode()
    {
        return $this->homeNode;
    }

    /**
     * @param mixed $homeNode
     * @return Profile
     */
    public function setHomeNode($homeNode)
    {
        $this->homeNode = $homeNode;
        return $this;
    }

}
