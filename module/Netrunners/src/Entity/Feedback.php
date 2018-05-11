<?php

/**
 * Feedback Entity.
 * Player can send typo-, idea- and bug-reports to the admins via this entity.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FeedbackRepository") */
final class Feedback
{

    const TYPE_TYPO_ID = 1;
    const TYPE_IDEA_ID = 2;
    const TYPE_BUG_ID = 3;

    const TYPE_TYPO_STRING = 'typo';
    const TYPE_IDEA_STRING = 'idea';
    const TYPE_BUG_STRING = 'bug';

    static $lookup = [
        self::TYPE_TYPO_ID => self::TYPE_TYPO_STRING,
        self::TYPE_IDEA_ID => self::TYPE_IDEA_STRING,
        self::TYPE_BUG_ID => self::TYPE_BUG_STRING,
    ];

    const STATUS_SUBMITTED_ID = 1;
    const STATUS_ONGOING_ID = 2;
    const STATUS_CLOSED_ID = 3;
    const STATUS_COMPLETED_ID = 4;

    const STATUS_SUBMITTED_STRING = 'submitted';
    const STATUS_ONGOING_STRING = 'ongoing';
    const STATUS_CLOSED_STRING = 'closed';
    const STATUS_COMPLETED_STRING = 'completed';

    static $statusLookup = [
        self::STATUS_SUBMITTED_ID => self::STATUS_SUBMITTED_STRING,
        self::STATUS_ONGOING_ID => self::STATUS_ONGOING_STRING,
        self::STATUS_CLOSED_ID => self::STATUS_CLOSED_STRING,
        self::STATUS_COMPLETED_ID => self::STATUS_COMPLETED_STRING,
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
    protected $description;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $type;

    /**
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @var string
     */
    protected $internalData;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
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
     * @return Feedback
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
     * @return Feedback
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return Feedback
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
     * @return Feedback
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return Feedback
     */
    public function setType($type)
    {
        $this->type = $type;
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
     * @return Feedback
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getInternalData()
    {
        return $this->internalData;
    }

    /**
     * @param string $internalData
     * @return Feedback
     */
    public function setInternalData($internalData)
    {
        $this->internalData = $internalData;
        return $this;
    }

    // ORM

    /**
     * @return Profile
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param Profile $profile
     * @return Feedback
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
