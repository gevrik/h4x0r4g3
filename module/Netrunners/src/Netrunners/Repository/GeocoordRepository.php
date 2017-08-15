<?php

/**
 * GeocoordRepository Custom Repository.
 * GeocoordRepository Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;

class GeocoordRepository extends EntityRepository
{

    public function findOneUnique($lat, $lng, $placeId)
    {
        return $this->findOneBy([
            'lat' => $lat,
            'lng' => $lng,
            'placeId' => $placeId
        ]);
    }

}
