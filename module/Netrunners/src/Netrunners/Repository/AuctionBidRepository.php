<?php

/**
 * AuctionBid Custom Repository.
 * AuctionBid Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Auction;
use Netrunners\Entity\AuctionBid;
use Netrunners\Entity\Profile;

class AuctionBidRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @return array
     */
    public function findActiveByProfile(Profile $profile)
    {
        $bids = $this->findBy([
            'profile' => $profile
        ]);
        $activeBids = [];
        $currentAuction = NULL;
        $now = new \DateTime();
        foreach ($bids as $bid) {
            /** @var AuctionBid $bid */
            if ($bid->getAuction() != $currentAuction) $currentAuction = $bid->getAuction();
            /** @var Auction $currentAuction */
            // check if they are no longer valid
            if ($currentAuction->getBought() || $currentAuction->getExpires() < $now) continue;
            $activeBids[] = $bid;
        }
        return $activeBids;
    }

    /**
     * @param Auction $auction
     * @return array
     */
    public function findByAuction(Auction $auction)
    {
        $qb = $this->createQueryBuilder('ab');
        $qb->where('ab.auction = :auction');
        $qb->setParameters([
            'auction' => $auction
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Auction $auction
     * @return array
     */
    public function countByAuction(Auction $auction)
    {
        $qb = $this->createQueryBuilder('ab');
        $qb->select($qb->expr()->count('ab.id'));
        $qb->where('ab.auction = :auction');
        $qb->setParameters([
            'auction' => $auction
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Auction $auction
     * @param Profile $profile
     * @return mixed
     */
    public function findByAuctionAndProfile(Auction $auction, Profile $profile)
    {
        $qb = $this->createQueryBuilder('ab');
        $qb->where('ab.profile = :profile AND ab.auction = :auction');
        $qb->setParameters([
            'profile' => $profile,
            'auction' => $auction
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param Auction $auction
     * @return mixed
     */
    public function findHighBid(Auction $auction)
    {
        $qb = $this->createQueryBuilder('ab');
        $qb->select($qb->expr()->max('ab.bid'));
        $qb->where('ab.auction = :auction');
        $qb->setParameter('auction', $auction);
        return $qb->getQuery()->getSingleScalarResult();
    }

}
