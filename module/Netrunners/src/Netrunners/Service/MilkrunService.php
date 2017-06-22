<?php

/**
 * Milkrun Service.
 * The service supplies methods that resolve logic around Milkruns.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use TmoAuth\Entity\User;

class MilkrunService extends BaseService
{

    public function requestMilkrun($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = $this->isActionBlocked($resourceId);
        if (!$response && $profile->getCurrentNode()->getType() != Node::ID_AGENT) {
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You need to be in an agent node to request a milkrun</pre>');
            $response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        if (!$response) {
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Requesting a milkrun, please wait...</pre>');
            $response = array(
                'command' => 'showmessage',
                'message' => $returnMessage
            );
        }
        return $response;
    }

}
