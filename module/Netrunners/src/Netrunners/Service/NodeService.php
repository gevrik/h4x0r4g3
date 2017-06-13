<?php

/**
 * Node Service.
 * The service supplies methods that resolve logic around Node objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use TmoAuth\Entity\User;

class NodeService extends BaseService
{

    const NAME_STRING = "name";
    const LEVEL_STRING = "level";
    const CONNECTIONS_STRING = "connections";
    const FILES_STRING = "files";

    public function showNodeInfo($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', SystemService::SYSTEM_STRING, $currentSystem->getName());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::NAME_STRING, $currentNode->getName());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::LEVEL_STRING, $currentNode->getLevel());
        $returnMessage[] = sprintf('<pre>%s:</pre>', self::CONNECTIONS_STRING);
        $connections = $this->entityManager->getRepository('Netrunners\Entity\Connection')->findBySourceNode($profile->getCurrentNode());
        $counter = 0;
        foreach ($connections as $connection) {
            /** @var Connection $connection */
            $counter++;
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', $counter, $connection->getName());
        }
        $returnMessage[] = sprintf('<pre>%s:</pre>', self::FILES_STRING);
        $files = $this->entityManager->getRepository('Netrunners\Entity\File')->findByNode($profile->getCurrentNode());
        $counter = 0;
        foreach ($files as $file) {
            /** @var File $file */
            $counter++;
            $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', $counter, $file->getName());
        }
        $response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $response;
    }

}
