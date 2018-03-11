<?php

/**
 * Geocoord Mapper.
 * Maps the socket server's geocoords property to doctine entities.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Mapper;

class GeocoordMapper extends AbstractMapper
{

    /**
     * GeocoordMapper constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getPoolName()
    {
        return 'Geocoords';
    }

    /**
     * @param $lat
     * @param $lng
     * @param $placeid
     * @return bool|mixed
     */
    public function findByLatLngPlace($lat, $lng, $placeid)
    {
        foreach ($this->data as $geocoord) {
            if ($geocoord['lat'] == $lat && $geocoord['lng'] == $lng && $geocoord['placeId'] == $placeid) {
                return $geocoord;
                break;
            }
        }
        return false;
    }

}
