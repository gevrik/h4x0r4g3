<?php

/**
 * System Custom Repository.
 * System Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;

class SystemRepository extends EntityRepository
{

    /**
     * @param $addy
     * @return null|object
     */
    public function findByAddy($addy)
    {
        $result = $this->findOneBy([
            'addy' => $addy
        ]);
        return $result;
    }

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
