<?php

/**
 * StoryUser Entity.
 * This bridge entity keeps track of which user has started which stories.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace TwistyPassages\Entity;

use Doctrine\ORM\Mapping as ORM;
use TmoAuth\Entity\User;

/** @ORM\Entity */
class StoryUser
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

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $completed;

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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return StoryUser
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
     * @return StoryUser
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCompleted()
    {
        return $this->completed;
    }

    /**
     * @param \DateTime $completed
     * @return StoryUser
     */
    public function setCompleted($completed)
    {
        $this->completed = $completed;
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
     * @return StoryUser
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
     * @return StoryUser
     */
    public function setStory($story)
    {
        $this->story = $story;
        return $this;
    }

}
