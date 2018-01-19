<?php

/**
 * FileType Custom Repository.
 * FileType Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Profile;

class FileTypeRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @return array
     */
    public function findForCoding(Profile $profile)
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->where('ft.codable = 1');
        $qb->orderBy('ft.name', 'ASC');
        $fileTypes = $qb->getQuery()->getResult();
        $availableFileTypes = [];
        $profileFileTypeRecipeRepo = $this->getEntityManager()->getRepository('Netrunners\Entity\ProfileFileTypeRecipe');
        /** @var ProfileFileTypeRecipeRepository $profileFileTypeRecipeRepo */
        foreach ($fileTypes as $fileType) {
            /** @var FileType $fileType */
            if ($fileType->getNeedRecipe()) {
                if ($profileFileTypeRecipeRepo->findOneByProfileAndFileType($profile, $fileType)) {
                    $availableFileTypes[] = $fileType;
                }
            }
            else {
                $availableFileTypes[] = $fileType;
            }
        }
        return $availableFileTypes;
    }

    /**
     * @param $keyword
     * @return FileType|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->where($qb->expr()->like('ft.name', $qb->expr()->literal($keyword . '%')));
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param $fileCategoryId
     * @return array
     */
    public function findByCategoryId($fileCategoryId)
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->innerJoin('ft.fileCategories', 'fc', 'WITH', 'fc.id = :fileCategoryId');
        $qb->setParameter(':fileCategoryId', $fileCategoryId);
        return $qb->getQuery()->getResult();
    }

}
