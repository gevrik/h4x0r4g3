<?php

/**
 * FileMod Custom Repository.
 * FileMod Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\FileMod;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeMod;
use Netrunners\Entity\Profile;

class FileModRepository extends EntityRepository
{

    /**
     * @param Profile|NULL $profile
     * @return array
     */
    public function findForCoding(Profile $profile = NULL)
    {
        $qb = $this->createQueryBuilder('fm');
        return $qb->getQuery()->getResult();
    }

    /**
     * @param FileType $fileType
     * @return array
     */
    public function listForTypeCommand(FileType $fileType)
    {
        $result = [];
        $fileTypeModRepo = $this->_em->getRepository('Netrunners\Entity\FileTypeMod');
        /** @var FileTypeModRepository $fileTypeModRepo */
        $fileMods = $this->findAll();
        foreach ($fileMods as $fileMod) {
            $countFtm = $fileTypeModRepo->countByFileMod($fileMod);
            if ($countFtm < 1) {
                $result[] = $fileMod;
            }
            $ftms = $fileTypeModRepo->findByFileType($fileType);
            foreach ($ftms as $ftm) {
                /** @var FileTypeMod $ftm */
                $result[] = $ftm->getFileMod();
            }
        }
        return $result;
    }

    /**
     * @param $keyword
     * @return FileMod|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('fm');
        $qb->where($qb->expr()->like('fm.name', $qb->expr()->literal($keyword . '%')));
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
