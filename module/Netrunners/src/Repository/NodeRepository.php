<?php

/**
 * Node Custom Repository.
 * Node Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;

class NodeRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findForConnectCommand(Profile $profile)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->leftJoin('n.system', 's');
        $qb->where('n.nodeType = :type');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', NodeType::ID_PUBLICIO);
        $pType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', NodeType::ID_IO);
        $qb->setParameter('type', $nodeType);
        $qb->orWhere('s.profile = :profile AND n.nodeType = :ptype');
        $qb->setParameter('profile', $profile);
        if ($profile->getFaction()) { // TODO make this only show ios that the faction role is allowed to access
            $qb->orWhere('s.faction = :faction AND n.nodeType = :ptype');
            $qb->setParameter('faction', $profile->getFaction());
        }
        if ($profile->getGroup()) { // TODO make this only show ios that the group role is allowed to access
            $qb->orWhere('s.group = :group AND n.nodeType = :ptype');
            $qb->setParameter('group', $profile->getGroup());
        }
        $qb->setParameter('ptype', $pType);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @return array
     */
    public function findBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->select($qb->expr()->count('n.id'));
        $qb->where('n.system = :system');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param $type
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findByType($type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.nodeType = :type');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', $type);
        $qb->setParameter('type', $nodeType);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @param $type
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findBySystemAndType(System $system, $type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->where('n.nodeType = :type and n.system = :system');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', $type);
        $qb->setParameters([
            'type' => $nodeType,
            'system' => $system
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function getTotalCpuLevels(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->select('SUM(n.level)');
        $qb->where('n.nodeType = :type and n.system = :system');
        $nodeType = $this->getEntityManager()->find(NodeType::class, NodeType::ID_CPU);
        $qb->setParameters([
            'type' => $nodeType,
            'system' => $system
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param System $system
     * @param $type
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function countBySystemAndType(System $system, $type)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->select($qb->expr()->count('n.id'));
        $qb->where('n.nodeType = :type and n.system = :system');
        $nodeType = $this->getEntityManager()->find('Netrunners\Entity\NodeType', $type);
        $qb->setParameters([
            'type' => $nodeType,
            'system' => $system
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param System $system
     * @param $nodeTypeId
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function getSumResourceLevelsForSystem(System $system, $nodeTypeId)
    {
        $nodeType = $this->_em->find('Netrunners\Entity\NodeType', $nodeTypeId);
        $qb = $this->createQueryBuilder('n');
        $qb->select('SUM(n.level) as totalAmount');
        $qb->where('n.system = :system AND n.nodeType = :nodeType');
        $qb->setParameters([
            'system' => $system,
            'nodeType' => $nodeType
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param System $system
     * @return Node
     */
    public function getRandomNodeForMission(System $system)
    {
        $nodes = $this->findBySystem($system);
        array_shift($nodes);
        array_shift($nodes);
        $targetNodeId = mt_rand(0, count($nodes)-1);
        return (isset($nodes[$targetNodeId])) ? $nodes[$targetNodeId] : NULL;
    }

    /**
     * @param System $system
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getAverageNodeLevelOfSystem(System $system)
    {
        $qb = $this->createQueryBuilder('n');
        $qb->select($qb->expr()->avg('n.level'));
        $qb->where('n.system = :system');
        $qb->setParameter('system', $system);
        return round($qb->getQuery()->getSingleScalarResult(), 2);
    }

}
