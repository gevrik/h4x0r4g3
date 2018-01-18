<?php

/**
 * ProfileFactionRating Custom Repository.
 * ProfileFactionRating Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Faction;
use Netrunners\Entity\Profile;

class ProfileFactionRatingRepository extends EntityRepository
{

    /**
     * Returns the faction rating as a number for given profile and faction.
     * @param Profile $profile
     * @param Faction $faction
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getProfileFactionRating(Profile $profile, Faction $faction)
    {
        $qb = $this->createQueryBuilder('pfr');
        $qb->select('SUM(pfr.sourceRating) as sourceRatings');
        $qb->where('pfr.sourceFaction = :sourceFaction AND pfr.profile = :profile');
        $qb->setParameters([
            'sourceFaction' => $faction,
            'profile' => $profile
        ]);
        $sourceRatings = $qb->getQuery()->getSingleScalarResult();
        $qb = $this->createQueryBuilder('pfr');
        $qb->select('SUM(pfr.targetRating) as targetRatings');
        $qb->where('pfr.targetFaction = :targetFaction AND pfr.profile = :profile');
        $qb->setParameters([
            'targetFaction' => $faction,
            'profile' => $profile
        ]);
        $targetRatings = $qb->getQuery()->getSingleScalarResult();
        return $sourceRatings + $targetRatings;
    }

}
