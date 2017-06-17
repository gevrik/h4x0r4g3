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

    public function findByAddy($addy)
    {
        return $this->findOneBy(['addy', $addy]);
    }

    public function findByProfile(Profile $profile)
    {
        return $this->findBy([
            'profile' => $profile
        ]);
    }

}
