<?php

/**
 * Choice Entity.
 * Choice Entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace TwistyPassages\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
class Choice
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
    protected $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @var string
     */
    protected $description;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $status;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    // ORM

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
     * @return Choice
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return Choice
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     * @return Choice
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     * @return Choice
     */
    public function setStatus(int $status)
    {
        $this->status = $status;
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
     * @return Choice
     */
    public function setAdded(\DateTime $added)
    {
        $this->added = $added;
        return $this;
    }

    // ORM

    /**
     * @return null|Story
     */
    public function getStory()
    {
        return $this->story;
    }

    /**
     * @param Story $story
     * @return Choice
     */
    public function setStory($story)
    {
        $this->story = $story;
        return $this;
    }

}
