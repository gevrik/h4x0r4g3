<?php

/**
 * System Custom Repository.
 * System Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Faction;
use Netrunners\Entity\Profile;

class SystemRepository extends EntityRepository
{

    /**
     * @param $addy
     * @return null|object
     */
    public function findByAddy($addy)
    {
        $result = $this->findOneBy([
            'addy' => $addy
        ]);
        return $result;
    }

    /**
     * @param Profile $profile
     * @return array
     */
    public function findByProfile(Profile $profile)
    {
        return $this->findBy([
            'profile' => $profile
        ]);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countLikeName($name)
    {
        $qb = $this->createQueryBuilder('s');
        $qb->select($qb->expr()->count('s.id'));
        $qb->where($qb->expr()->like('s.name', $qb->expr()->literal($name . '%')));
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Faction $faction
     * @param bool $removeHq
     * @return array
     */
    public function findByTargetFaction(Faction $faction, $removeHq = true)
    {
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.faction = :faction');
        $qb->setParameter('faction', $faction);
        $qb->orderBy('s.id', 'ASC');
        $result = $qb->getQuery()->getResult();
        if ($removeHq) array_shift($result);
        return $result;
    }

}
