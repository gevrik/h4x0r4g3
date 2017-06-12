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
use Netrunners\Entity\System;

class FileRepository extends EntityRepository
{

    /**
     * Finds the file with the given name in the given system.
     * @param System $system
     * @param File $currentDirectory
     * @param bool|false $name
     * @param bool|true $includeBin
     * @return array
     */
    public function findFileInSystemByName(System $system, File $currentDirectory, $name = false, $includeBin = true)
    {
        $binDirectory = $this->findOneBy(array(
            'system' => $system,
            'name' => 'bin'
        ));
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.parent = :currentDirectory');
        $qb->setParameter('currentDirectory', $currentDirectory);
        if ($includeBin) {
            $qb->orWhere('f.parent = :binDirectory');
            $qb->setParameter('binDirectory', $binDirectory);
        }
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
