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
use Netrunners\Entity\Profile;

class AuctionBidRepository extends EntityRepository
{

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

}
