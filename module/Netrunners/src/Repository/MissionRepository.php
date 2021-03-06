<?php

/**
 * Mission Custom Repository.
 * Mission Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;

class MissionRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findCurrentMission(Profile $profile)
    {
        $qb = $this->createQueryBuilder('m');
        $qb->where('m.profile = :profile AND m.completed IS NULL AND m.expires > :now');
        $qb->setParameters([
            'profile' => $profile,
            'now' => new \DateTime()
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param File $file
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByTargetFile(File $file)
    {
        $qb = $this->createQueryBuilder('m');
        $qb->where('m.targetFile = :file');
        $qb->setParameter('file', $file);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array
     */
    public function findForExpiredLoop()
    {
        $qb = $this->createQueryBuilder('m');
        $qb->where('m.completed IS NULL AND m.expires <= :now AND m.expired IS NULL');
        $qb->setParameter('now', new \DateTime());
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @return array
     */
    public function findForMissionListCommand(Node $node)
    {
        $qb = $this->createQueryBuilder('m');
        $qb->where('m.completed IS NULL AND m.expires <= :now AND m.expired IS NULL AND m.agentNode = :agentNode');
        $qb->setParameters([
            'now' => new \DateTime(),
            'agentNode' => $node
        ]);
        return $qb->getQuery()->getResult();
    }

}
