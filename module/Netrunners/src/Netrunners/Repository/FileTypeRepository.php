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
        $qb->where('ft.codable > 0');
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
     * @return array
     */
    public function findLikeName($keyword)
    {
        $qb = $this->createQueryBuilder('ft');
        $qb->where($qb->expr()->like('ft.name', $qb->expr()->literal($keyword . '%')));
        return $qb->getQuery()->getOneOrNullResult();
    }

}
