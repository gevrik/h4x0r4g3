<?php

/**
 * Connection Service.
 * The service supplies methods that resolve logic around Connection objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Connection;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use TmoAuth\Entity\User;

class ConnectionService extends BaseService
{

    /**
     * @param $clientData
     * @param $contentArray
     * @return array|bool
     */
    public function useConnection($clientData, $contentArray)
    {
        // TODO check for codegate and permission
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        $response = false;
        /* connections can be given by name or number, so we need to handle both */
        // get parameter
        $parameter = array_shift($contentArray);
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($currentNode);
        $connection = false;
        if ($searchByNumber) {
            if (isset($connections[$parameter-1])) {
                $connection = $connections[$parameter-1];
            }
        }
        else {
            foreach ($connections as $pconnection) {
                /** @var Connection $pconnection */
                if ($pconnection->getName() == $parameter) {
                    $connection = $pconnection;
                    break;
                }
            }
        }
        if (!$connection) {
            $response = array(
                'command' => 'showMessage',
                'type' => 'warning',
                'message' => sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "No such connection")
            );
        }
        else {
            $profile->setCurrentNode($connection->getTargetNode());
            $this->entityManager->flush($profile);
            $response = array(
                'command' => 'cd',
                'type' => 'default',
                'message' => false
            );
        }
        return $response;
    }

}
