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
     * @param null|FileType $fileType
     * @return array
     */
    public function findRunningFilesInSystemByType(System $system, $running = true, FileType $fileType = NULL)
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.system = :system');
        if ($running) {
            $qb->andWhere('f.running = :running');
            $qb->setParameter('running', $running);
        }
        if ($fileType) {
            $qb->andWhere('f.fileType = :type');
            $qb->setParameter('type', $fileType);
        }
        $qb->setParameter('system', $system);
        return $qb->getQuery()->getResult();
    }

}
