<?php

/**
 * Manpage Entity.
 * All kinds of information about manual pages are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Netrunners\Repository\ManpageRepository")
 * @ORM\Table(indexes={@ORM\Index(name="search_idx", columns={"subject"})})
 */
class Manpage
{

    const STATUS_INVALID = 0;
    const STATUS_SUGGESTED = 1;
    const STATUS_REVIEW = 2;
    const STATUS_CHANGES = 3;
    const STATUS_ACCEPTED = 4;

    const STATUS_INVALID_STRING = 'invalid';
    const STATUS_SUGGESTED_STRING = 'suggested';
    const STATUS_REVIEW_STRING = 'review';
    const STATUS_CHANGES_STRING = 'changes';
    const STATUS_ACCEPTED_STRING = 'accepted';

    static $lookup = [
        self::STATUS_INVALID => self::STATUS_INVALID_STRING,
        self::STATUS_SUGGESTED => self::STATUS_SUGGESTED_STRING,
        self::STATUS_REVIEW => self::STATUS_REVIEW_STRING,
        self::STATUS_CHANGES => self::STATUS_CHANGES_STRING,
        self::STATUS_ACCEPTED => self::STATUS_ACCEPTED_STRING,
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
    protected $subject;

    /**
     * @ORM\Column(type="text")
     * @var string
     */
    protected $content;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $createdDateTime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $updatedDateTime;

    /**
     * @ORM\Column(type="integer", options={"default":0}, nullable=true)
     * @var int
     */
    protected $status;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $author;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Manpage", inversedBy="children")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Netrunners\Entity\Manpage", mappedBy="parent")
     */
    protected $children;


    /**
     * Constructor for MailMessage.
     */
    public function __construct() {
        $this->children = new ArrayCollection();
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
     * @return Manpage
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return Manpage
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return Manpage
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedDateTime()
    {
        return $this->createdDateTime;
    }

    /**
     * @param \DateTime $createdDateTime
     * @return Manpage
     */
    public function setCreatedDateTime($createdDateTime)
    {
        $this->createdDateTime = $createdDateTime;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedDateTime()
    {
        return $this->updatedDateTime;
    }

    /**
     * @param \DateTime $updatedDateTime
     * @return Manpage
     */
    public function setUpdatedDateTime($updatedDateTime)
    {
        $this->updatedDateTime = $updatedDateTime;
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
     * @return Manpage
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    // ORM

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param mixed $author
     * @return Manpage
     */
    public function setAuthor($author)
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     * @return Manpage
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**w
     * @param Manpage $manpage
     */
    public function addChild(Manpage $manpage)
    {
        $this->children[] = $manpage;
    }

    /**
     * @param Manpage $manpage
     */
    public function removeChild(Manpage $manpage)
    {
        $this->children->removeElement($manpage);
    }

}
