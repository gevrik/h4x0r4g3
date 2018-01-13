<?php

/**
 * MailMessage Entity.
 * All kinds of information about mails are stored in this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\MailMessageRepository") */
class MailMessage
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
    protected $sentDateTime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $readDateTime;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $author;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $recipient;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\MailMessage", inversedBy="children")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Netrunners\Entity\MailMessage", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\OneToMany(targetEntity="Netrunners\Entity\File", mappedBy="mailMessage")
     */
    protected $attachments;


    /**
     * Constructor for MailMessage.
     */
    public function __construct() {
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
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
     * @return MailMessage
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
     * @return MailMessage
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
     * @return MailMessage
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getSentDateTime()
    {
        return $this->sentDateTime;
    }

    /**
     * @param \DateTime $sentDateTime
     * @return MailMessage
     */
    public function setSentDateTime($sentDateTime)
    {
        $this->sentDateTime = $sentDateTime;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getReadDateTime()
    {
        return $this->readDateTime;
    }

    /**
     * @param \DateTime $readDateTime
     * @return MailMessage
     */
    public function setReadDateTime($readDateTime)
    {
        $this->readDateTime = $readDateTime;
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
     * @return MailMessage
     */
    public function setAuthor($author)
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * @param mixed $recipient
     * @return MailMessage
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
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
     * @return MailMessage
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

    /**
     * @param MailMessage $mailMessage
     */
    public function addChild(MailMessage $mailMessage)
    {
        $this->children[] = $mailMessage;
    }

    /**
     * @param MailMessage $mailMessage
     */
    public function removeChild(MailMessage $mailMessage)
    {
        $this->children->removeElement($mailMessage);
    }

    /**
     * @return mixed
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param File $file
     */
    public function addAttachment(File $file)
    {
        $this->attachments[] = $file;
    }

    /**
     * @param File $file
     */
    public function removeAttachment(File $file)
    {
        $this->attachments->removeElement($file);
    }

}
