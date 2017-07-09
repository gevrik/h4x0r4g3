<?php

/**
 * ProfileEffect Entity.
 * Keeps track of which effects are on which profile or npc.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ProfileEffectRepository") */
class ProfileEffect
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
    protected $expires;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $rating;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     **/
    protected $profile;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\NpcInstance")
     **/
    protected $npcInstance;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Effect")
     **/
    protected $effect;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return ProfileEffect
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * @param \DateTime $expires
     * @return ProfileEffect
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;
        return $this;
    }

    /**
     * @return int
     */
    public function getRating()
    {
        return $this->rating;
    }

    /**
     * @param int $rating
     * @return ProfileEffect
     */
    public function setRating($rating)
    {
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return ProfileEffect
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNpcInstance()
    {
        return $this->npcInstance;
    }

    /**
     * @param mixed $npcInstance
     * @return ProfileEffect
     */
    public function setNpcInstance($npcInstance)
    {
        $this->npcInstance = $npcInstance;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEffect()
    {
        return $this->effect;
    }

    /**
     * @param mixed $effect
     * @return ProfileEffect
     */
    public function setEffect($effect)
    {
        $this->effect = $effect;
        return $this;
    }

}
