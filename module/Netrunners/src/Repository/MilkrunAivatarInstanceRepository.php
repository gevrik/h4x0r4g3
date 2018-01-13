<?php

/**
 * MilkrunAivatarInstance Custom Repository.
 * MilkrunAivatarInstance Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class MilkrunAivatarInstanceRepository extends EntityRepository
{

    /**
     * @param Profile $profile
     * @return array
     */
    public function findByProfile(Profile $profile)
    {
        return $this->findBy([
            'profile' => $profile
        ]);
    }

}
