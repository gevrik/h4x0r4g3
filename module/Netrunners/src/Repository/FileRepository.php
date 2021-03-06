<?php

/**
 * File Custom Repository.
 * File Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;

class FileRepository extends EntityRepository
{

    /**
     * Finds all files for the given profile.
     * @param Profile $profile
     * @return array
     */
    public function findByProfile(Profile $profile)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.profile = :profile');
        $qb->setParameter('profile', $profile);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param System $system
     * @return array
     */
    public function findActiveBySystem(System $system)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.system = :system AND f.integrity > 1 AND f.running = 1');
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getResult();
    }

    /**
     * Finds all files for the given npc instance.
     * @param NpcInstance $npc
     * @return array
     */
    public function findByNpc(NpcInstance $npc)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.npc = :npc');
        $qb->setParameter('npc', $npc);
        return $qb->getQuery()->getResult();
    }

    /**
     * Finds all files for the given node.
     * @param Node $node
     * @return array
     */
    public function findByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getResult();
    }

    /**
     * Counts all files for the given node.
     * @param Node $node
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->select($qb->expr()->count('f.id'));
        $qb->where('f.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Counts all files for the given node.
     * @param Node $node
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countRunningByNode(Node $node)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->select($qb->expr()->count('f.id'));
        $qb->where('f.node = :node AND f.running = 1');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Node $node
     * @param Profile $profile
     * @return array
     */
    public function findByNodeOrProfile(Node $node, Profile $profile)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.node = :node');
        $qb->orWhere('f.profile = :profile AND f.system IS NULL AND f.node IS NULL');
        $qb->setParameters([
            'node' => $node,
            'profile' => $profile
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @param Profile $profile
     * @param $name
     * @return array
     */
    public function findByNodeOrProfileAndName(Node $node, Profile $profile, $name)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.node = :node');
        $qb->orWhere('f.profile = :profile AND f.system IS NULL AND f.node IS NULL');
        $qb->andWhere('f.name = :name');
        $qb->setParameters([
            'node' => $node,
            'profile' => $profile,
            'name' => $name
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * Finds the file with the given name in the given system.
     * @param System $system
     * @param bool|false $name
     * @return array
     */
    public function findFileInSystemByName(System $system, $name = false)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.system = :system');
        $qb->setParameter('system', $system);
        if ($name) {
            $qb->andWhere('f.name = :name');
            $qb->setParameter('name', $name);
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * Finds the file with the given name in the given node.
     * @param Node $node
     * @param bool|false $name
     * @return array
     */
    public function findFileInNodeByName(Node $node, $name = false)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.node = :node');
        $qb->setParameter('node', $node);
        if ($name) {
            $qb->andWhere('f.name = :name');
            $qb->setParameter('name', $name);
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all running programs of the given type in the given system.
     * @param System $system
     * @param bool|true $running
     * @param int $fileType
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findRunningFilesInSystemByType(System $system, $running = true, $fileType)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.system = :system');
        if ($running) {
            $qb->andWhere('f.running = :running');
            $qb->setParameter('running', $running);
        }
        if ($fileType) {
            $qb->andWhere('f.fileType = :type');
            $fileTypeObject = $this->_em->find('Netrunners\Entity\FileType', $fileType);
            $qb->setParameter('type', $fileTypeObject);
        }
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getResult();
    }

    /**
     * Returns the first running chat client for the given profile or null.
     * @param Profile $profile
     * @return null|File
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findChatClientForProfile(Profile $profile)
    {
        $chatclient = $this->getEntityManager()->find(FileType::class, FileType::ID_CHATCLIENT);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.running != 0 AND f.integrity >= 1 AND f.profile = :profile');
        $qb->setParameters([
            'fileType' => $chatclient,
            'profile' => $profile
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param Node $node
     * @param Profile $profile
     * @param int $fileTypeId
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findOneRunningInNodeByTypeAndProfile(Node $node, Profile $profile, $fileTypeId)
    {
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.running = 1 AND f.integrity >= 1 AND f.profile = :profile AND f.node = :node');
        $qb->setParameters([
            'fileType' => $fileType,
            'profile' => $profile,
            'node' => $node
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param Node $node
     * @param int $fileTypeId
     * @return mixed
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findRunningInNodeByType(Node $node, $fileTypeId)
    {
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.running = 1 AND f.integrity >= 1 AND f.node = :node');
        $qb->setParameters([
            'fileType' => $fileType,
            'node' => $node
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @return mixed
     */
    public function findRunningInNode(Node $node)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.running = 1 AND f.integrity >= 1 AND f.node = :node');
        $qb->setParameter('node', $node);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Node $node
     * @param $fileTypeId
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function getTotalRunningLevelInNodeByType(Node $node, $fileTypeId)
    {
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->select('SUM(f.level)');
        $qb->where('f.fileType = :fileType AND f.running = 1 AND f.integrity >= 1 AND f.node = :node');
        $qb->setParameters([
            'fileType' => $fileType,
            'node' => $node
        ]);
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param Node $node
     * @param $fileModId
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findRunningInNodeByMod(Node $node, $fileModId)
    {
        $fileMod = $this->_em->find('Netrunners\Entity\FileMod', $fileModId);
        $qb = $this->createQueryBuilder('f');
        $qb->select('f');
        $qb->innerJoin('Netrunners\Entity\FileModInstance', 'fmi', 'WITH', 'fmi.file = f');
        $qb->where('f.running = 1 AND f.integrity >= 1 AND f.node = :node AND fmi.fileMod = :fileMod');
        $qb->setParameters([
            'fileMod' => $fileMod,
            'node' => $node
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $profile
     * @param $fileTypeId
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findByProfileAndType(Profile $profile, $fileTypeId)
    {
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.profile = :profile');
        $qb->setParameters([
            'fileType' => $fileType,
            'profile' => $profile
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param Profile $profile
     * @param $fileTypeId
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findRunningByProfileAndType(Profile $profile, $fileTypeId)
    {
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.profile = :profile AND f.running = 1 AND f.integrity >= 1');
        $qb->setParameters([
            'fileType' => $fileType,
            'profile' => $profile
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param NpcInstance $npc
     * @param int $fileTypeId
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findOneForHarvesting(NpcInstance $npc, $fileTypeId)
    {
        $miner = NULL;
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.node = :node AND f.running = 1 AND f.integrity >= 1 AND f.data IS NOT NULL');
        $qb->setParameters([
            'fileType' => $fileType,
            'node' => $npc->getNode()
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param NpcInstance $npc
     * @param $fileTypeId
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function findOneForRepair(NpcInstance $npc, $fileTypeId)
    {
        $miner = NULL;
        $fileType = $this->_em->find('Netrunners\Entity\FileType', $fileTypeId);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.node = :node AND f.integrity < f.maxIntegrity');
        $qb->setParameters([
            'fileType' => $fileType,
            'node' => $npc->getNode()
        ]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $fileCategoryId
     * @return array
     */
    public function findByCategory($fileCategoryId)
    {
        $fileCategoryRepo = $this->_em->getRepository('Netrunners\Entity\FileCategory');
        /** @var FileCategoryRepository $fileCategoryRepo */
        $fileCategory = $fileCategoryRepo->find($fileCategoryId);
        // now create query
        $qb = $this->createQueryBuilder('f');
        $qb->leftJoin('f.fileType', 'ft');
        $qb->where(':fileCategory MEMBER OF ft.fileCategories');
        $qb->setParameter('fileCategory', $fileCategory);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param $fileCategoryId
     * @return array
     */
    public function findByCategoryForLoop($fileCategoryId)
    {
        $fileCategoryRepo = $this->_em->getRepository('Netrunners\Entity\FileCategory');
        /** @var FileCategoryRepository $fileCategoryRepo */
        $fileCategory = $fileCategoryRepo->find($fileCategoryId);
        // now create query
        $qb = $this->createQueryBuilder('f');
        $qb->leftJoin('f.fileType', 'ft');
        $qb->where(':fileCategory MEMBER OF ft.fileCategories AND f.integrity >= 1 AND f.running = 1');
        $qb->setParameter('fileCategory', $fileCategory);
        return $qb->getQuery()->getResult();
    }

}
