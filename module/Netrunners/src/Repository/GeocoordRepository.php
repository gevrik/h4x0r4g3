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
use Netrunners\Entity\Geocoord;

class GeocoordRepository extends EntityRepository
{

    /**
     * @param $lat
     * @param $lng
     * @param $placeId
     * @return null|object
     */
    public function findOneUnique($lat, $lng, $placeId)
    {
        return $this->findOneBy([
            'lat' => $lat,
            'lng' => $lng,
            'placeId' => $placeId
        ]);
    }

    /**
     * @param null $zone
     * @return Geocoord
     */
    public function findOneRandomInZone($zone = NULL)
    {
        $qb = $this->createQueryBuilder('g');
        if ($zone) {
            $qb->where('g.zone = :zone');
            $qb->setParameter('zone', $zone);
        }
        $result = $qb->getQuery()->getResult();
        shuffle($result);
        return array_shift($result);
    }

}
