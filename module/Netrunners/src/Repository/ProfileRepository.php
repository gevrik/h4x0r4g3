<?php

/**
 * Profile Custom Repository.
 * Profile Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Faction;
use Netrunners\Entity\Group;
use Netrunners\Entity\Node;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;

class ProfileRepository extends EntityRepository
{

    /**
     * Returns all profiles that are currently connected to the given system.
     * @param System $system
     * @return array
     */
    public function findByCurrentSystem(System $system)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.currentSystem = :currentSystem');
        $qb->setParameter('currentSystem', $system);
        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all profiles that are currently connected to the given node.
     * A profile can be given, this will exclude the given profile from the results.
     * @param Node $node
     * @param Profile|NpcInstance|NULL $profile
     * @param bool $onlyOnline
     * @return array
     */
    public function findByCurrentNode(Node $node, $profile = NULL, $onlyOnline = false)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.currentNode = :currentNode');
        $qb->setParameter('currentNode', $node);
        if ($profile instanceof Profile) {
            $qb->andWhere('p.id != :profileId');
            $qb->setParameter('profileId', $profile->getId());
        }
        if ($onlyOnline) {
            $qb->andWhere('p.currentResourceId IS NOT NULL');
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @param Profile|NULL $profile
     * @return array
     */
    public function findByNodeOrderedByResourceId(Node $node, Profile $profile = NULL)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->where('p.currentNode = :node AND p.currentResourceId IS NOT NULL');
        $qb->setParameter('node', $node);
        if ($profile) {
            $qb->andWhere('p!= :profile');
            $qb->setParameter('profile', $profile);
        }
        $qb->orderBy('p.currentResourceId', 'ASC');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @param Profile|NULL $profile
     * @param bool $onlyOnline
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByCurrentNode(Node $node, Profile $profile = NULL, $onlyOnline = false)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select($qb->expr()->count('p.id'));
        $qb->where('p.currentNode = :currentNode');
        $qb->setParameter('currentNode', $node);
        if ($onlyOnline) {
            $qb->andWhere('p.currentResourceId IS NOT NULL');
        }
        if ($profile) {
            $qb->andWhere('p.id != :profileId');
            $qb->setParameter('profileId', $profile->getId());
        }
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Faction $faction
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByFaction(Faction $faction)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select($qb->expr()->count('p.id'));
        $qb->where('p.faction = :faction');
        $qb->setParameter('faction', $faction);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Group $group
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByGroup(Group $group)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->select($qb->expr()->count('p.id'));
        $qb->where('p.grgoup = :group');
        $qb->setParameter('group', $group);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string $keyword
     * @param Profile|null $profile
     * @param bool $onlineOnly
     * @return Profile|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLikeName($keyword, Profile $profile = NULL, $onlineOnly = false)
    {
        $qb = $this->createQueryBuilder('p');
        $qb->leftJoin('p.user', 'u');
        $qb->where($qb->expr()->like('u.username', $qb->expr()->literal($keyword . '%')));
        if ($onlineOnly) {
            $qb->andWhere('p.currentResourceId IS NOT NULL');
        }
        if ($profile) {
            $qb->andWhere('p.id != :profileId');
            $qb->setParameter('profileId', $profile->getId());
        }
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
