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
     */
    public function findChatClientForProfile(Profile $profile)
    {
        $chatclient = $this->getEntityManager()->find('Netrunners\Entity\FileType', FileType::ID_CHATCLIENT);
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.fileType = :fileType AND f.running IS NOT NULL AND f.integrity >= 1 AND f.profile = :profile');
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
     * @param Profile $profile
     * @param $fileTypeId
     * @return array
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
     * @param NpcInstance $npc
     * @param $fileTypeId
     * @return array
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

}
