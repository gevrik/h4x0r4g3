<?php

/**
 * BannedIp Mapper.
 * Maps the socket server's bannedips property to doctine entities.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Mapper;

class BannedIpMapper extends AbstractMapper
{

    /**
     * BannedIpMapper constructor.
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
        return 'Bannedips';
    }

    /**
     * @param string|null $bannedIp
     * @return bool
     */
    public function isIpBanned($bannedIp = NULL)
    {
        foreach ($this->data as $bannedip) {
            if ($bannedip['ip'] == $bannedIp) {
                return true;
                break;
            }
        }
        return false;
    }

}
