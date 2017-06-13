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
use Netrunners\Entity\System;

class FileRepository extends EntityRepository
{

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
