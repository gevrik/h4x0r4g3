<?php

/**
 * System Service.
 * The service supplies methods that resolve logic around System objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use TmoAuth\Entity\User;

class SystemService extends BaseService
{

    /**
     * @const DEFAULT_CPU
     */
    const DEFAULT_CPU = 1;

    /**
     * @const DEFAULT_MEMORY
     */
    const DEFAULT_MEMORY = 16;

    /**
     * @const DEFAULT_STORAGE
     */
    const DEFAULT_STORAGE = 32;

    const CPU_STRING = 'cpu';

    const MEMORY_STRING = 'memory';

    const STORAGE_STRING = 'storage';

    const SYSTEM_STRING = 'system';

    const ADDY_STRING = 'address';


    public function showSystemStats($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentSystem = $profile->getCurrentDirectory()->getSystem();
        /** @var System $currentSystem */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::SYSTEM_STRING, $currentSystem->getName());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::ADDY_STRING, $currentSystem->getAddy());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::CPU_STRING, $currentSystem->getCpu());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::MEMORY_STRING, $currentSystem->getMemory());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::STORAGE_STRING, $currentSystem->getStorage());
        $response = array(
            'command' => 'system',
            'message' => $returnMessage
        );
        return $response;
    }

}
