<?php

/**
 * Bounty Entity.
 * This keeps track of bounties places on players.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\BountyRepository") */
final class Bounty
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
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $amount;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $claimed;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $placer;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $target;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $claimer;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Bounty
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
     * @return Bounty
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     * @return Bounty
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getClaimed()
    {
        return $this->claimed;
    }

    /**
     * @param \DateTime|null $claimed
     * @return Bounty
     */
    public function setClaimed($claimed)
    {
        $this->claimed = $claimed;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPlacer()
    {
        return $this->placer;
    }

    /**
     * @param mixed $placer
     * @return Bounty
     */
    public function setPlacer($placer)
    {
        $this->placer = $placer;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param mixed $target
     * @return Bounty
     */
    public function setTarget($target)
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClaimer()
    {
        return $this->claimer;
    }

    /**
     * @param mixed $claimer
     * @return Bounty
     */
    public function setClaimer($claimer)
    {
        $this->claimer = $claimer;
        return $this;
    }

}
