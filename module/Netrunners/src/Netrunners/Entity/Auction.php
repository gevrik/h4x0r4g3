<?php

/**
 * Auction Entity.
 * This keeps track of file auctions.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\AuctionRepository") */
class Auction
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $startingPrice;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $currentPrice;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $buyoutPrice;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $expires;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $bought;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $claimed;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\File")
     */
    protected $file;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Node")
     */
    protected $node;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $auctioneer;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Profile")
     */
    protected $buyer;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Auction
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getStartingPrice()
    {
        return $this->startingPrice;
    }

    /**
     * @param int $startingPrice
     * @return Auction
     */
    public function setStartingPrice($startingPrice)
    {
        $this->startingPrice = $startingPrice;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentPrice()
    {
        return $this->currentPrice;
    }

    /**
     * @param int $currentPrice
     * @return Auction
     */
    public function setCurrentPrice($currentPrice)
    {
        $this->currentPrice = $currentPrice;
        return $this;
    }

    /**
     * @return int
     */
    public function getBuyoutPrice()
    {
        return $this->buyoutPrice;
    }

    /**
     * @param int $buyoutPrice
     * @return Auction
     */
    public function setBuyoutPrice($buyoutPrice)
    {
        $this->buyoutPrice = $buyoutPrice;
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
     * @return Auction
     */
    public function setAdded($added)
    {
        $this->added = $added;
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
     * @return Auction
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getBought()
    {
        return $this->bought;
    }

    /**
     * @param \DateTime $bought
     * @return Auction
     */
    public function setBought($bought)
    {
        $this->bought = $bought;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getClaimed()
    {
        return $this->claimed;
    }

    /**
     * @param \DateTime $claimed
     * @return Auction
     */
    public function setClaimed($claimed)
    {
        $this->claimed = $claimed;
        return $this;
    }

    // ORM

    /**
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     * @return Auction
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return Node
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param mixed $node
     * @return Auction
     */
    public function setNode($node)
    {
        $this->node = $node;
        return $this;
    }

    /**
     * @return Profile
     */
    public function getAuctioneer()
    {
        return $this->auctioneer;
    }

    /**
     * @param mixed $auctioneer
     * @return Auction
     */
    public function setAuctioneer($auctioneer)
    {
        $this->auctioneer = $auctioneer;
        return $this;
    }

    /**
     * @return Profile|null
     */
    public function getBuyer()
    {
        return $this->buyer;
    }

    /**
     * @param mixed $buyer
     * @return Auction
     */
    public function setBuyer($buyer)
    {
        $this->buyer = $buyer;
        return $this;
    }

}
