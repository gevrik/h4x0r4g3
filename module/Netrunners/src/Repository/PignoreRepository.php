<?php

/**
 * Pignore Custom Repository.
 * Pignore Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class PignoreRepository extends EntityRepository
{

    /**
     * Finds all ignores issued by the given profile.
     * @param Profile $profile
     * @return array
     */
    public function findForPignoreList(Profile $profile)
    {
        $qb = $this->createQueryBuilder('pig');
        $qb->leftJoin('pig.targetProfile', 'tp');
        $qb->leftJoin('tp.user', 'u');
        $qb->select('u.username');
        $qb->where('pig.sourceProfile = :profile');
        $qb->setParameter('profile', $profile);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $sourceProfile
     * @param Profile $targetProfile
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countBySourceAndTarget(Profile $sourceProfile, Profile $targetProfile)
    {
        $qb = $this->createQueryBuilder('pig');
        $qb->select($qb->expr()->count('pig.id'));
        $qb->where('pig.sourceProfile = :sourceProfile AND pig.targetProfile = :targetProfile');
        $qb->setParameters([
            'sourceProfile' => $sourceProfile,
            'targetProfile' => $targetProfile
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

}
