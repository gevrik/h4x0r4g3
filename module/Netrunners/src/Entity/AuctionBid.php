<?php

/**
 * AuctionBid Entity.
 * This keeps track of bids on auctions.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\AuctionBidRepository") */
class AuctionBid
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
    protected $bid;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $modified;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Auction")
     */
    protected $auction;

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
     * @return AuctionBid
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBid()
    {
        return $this->bid;
    }

    /**
     * @param mixed $bid
     * @return AuctionBid
     */
    public function setBid($bid)
    {
        $this->bid = $bid;
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
     * @return AuctionBid
     */
    public function setAdded($added)
    {
        $this->added = $added;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param mixed $modified
     * @return AuctionBid
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        return $this;
    }

    // ORM

    /**
     * @return Auction
     */
    public function getAuction()
    {
        return $this->auction;
    }

    /**
     * @param mixed $auction
     * @return AuctionBid
     */
    public function setAuction($auction)
    {
        $this->auction = $auction;
        return $this;
    }

    /**
     * @return Profile
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return AuctionBid
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

}
