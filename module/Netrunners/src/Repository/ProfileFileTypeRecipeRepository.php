<?php

/**
 * ProfileFileTypeRecipe Custom Repository.
 * ProfileFileTypeRecipe Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Profile;

class ProfileFileTypeRecipeRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @param FileType $fileType
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByProfileAndFileType(Profile $profile, FileType $fileType)
    {
        $qb = $this->createQueryBuilder('pftr');
        $qb->where('pftr.profile = :profile AND pftr.fileType = :fileType AND pftr.runs >= 1');
        $qb->setParameters([
            'profile' => $profile,
            'fileType' => $fileType
        ]);
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

}
