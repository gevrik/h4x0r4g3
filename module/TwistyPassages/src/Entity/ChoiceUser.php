<?php

/**
 * ChoiceUser Entity.
 * This bridge entity keeps track of which user has chosen which choices.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace TwistyPassages\Entity;

use Doctrine\ORM\Mapping as ORM;
use TmoAuth\Entity\User;

/** @ORM\Entity */
class ChoiceUser
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
     * @ORM\ManyToOne(targetEntity="TmoAuth\Entity\User")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="TwistyPassages\Entity\Story")
     */
    protected $story;

    /**
     * @ORM\ManyToOne(targetEntity="TwistyPassages\Entity\Choice")
     */
    protected $choice;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return ChoiceUser
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
     * @return ChoiceUser
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    // ORM

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     * @return ChoiceUser
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Story
     */
    public function getStory()
    {
        return $this->story;
    }

    /**
     * @param mixed $story
     * @return ChoiceUser
     */
    public function setStory($story)
    {
        $this->story = $story;
        return $this;
    }

    /**
     * @return Choice
     */
    public function getChoice()
    {
        return $this->choice;
    }

    /**
     * @param mixed $choice
     * @return ChoiceUser
     */
    public function setChoice($choice)
    {
        $this->choice = $choice;
        return $this;
    }

}
