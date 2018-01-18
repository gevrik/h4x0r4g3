<?php

/**
 * ProfileEffect Custom Repository.
 * ProfileEffect Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;

class ProfileEffectRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @param $effectId
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findOneByProfileAndEffect(Profile $profile, $effectId)
    {
        $effect = $this->_em->find('Netrunners\Entity\Effect', $effectId);
        $qb = $this->createQueryBuilder('pe');
        $qb->where('pe.profile = :profile AND pe.effect = :effect');
        $qb->setParameters([
            'profile' => $profile,
            'effect' => $effect
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param NpcInstance $npc
     * @param $effectId
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findOneByNpcAndEffect(NpcInstance $npc, $effectId)
    {
        $effect = $this->_em->find('Netrunners\Entity\Effect', $effectId);
        $qb = $this->createQueryBuilder('pe');
        $qb->where('pe.npcInstance = :npc AND pe.effect = :effect');
        $qb->setParameters([
            'npc' => $npc,
            'effect' => $effect
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
