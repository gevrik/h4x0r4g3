<?php

/**
 * Chat Service.
 * The service supplies methods that resolve logic around the in-game chat.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Profile;
use Ratchet\ConnectionInterface;
use TmoAuth\Entity\User;

class ChatService extends BaseService
{

    /**
     * @const CHANNEL_GLOBAL
     */
    const CHANNEL_GLOBAL = 'GCHAT';

    /**
     * @const CHANNEL_SAY
     */
    const CHANNEL_SAY = 'SAY';

    /**
     * @const CHANNEL_NEWBIE
     */
    const CHANNEL_NEWBIE = 'NEWBIE';

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function globalChat($resourceId, $contentArray)
    {
        $response = $this->isActionBlocked($resourceId, true);
        if (!$response) {
            // get user
            $ws = $this->getWebsocketServer();
            $clientData = $ws->getClientData($resourceId);
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            if (!$user) return true;
            // get profile
            $profile = $user->getProfile();
            /** @var Profile $profile */
            $messageContent = implode(' ', $contentArray);
            if (!$messageContent || $messageContent == '') return true;
            $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_GLOBAL);
            $wsClients = $ws->getClients();
            $wsClientsData = $ws->getClientsData();
            foreach ($wsClients as $wsClient) {
                /** @var ConnectionInterface $wsClient */
                /** @noinspection PhpUndefinedFieldInspection */
                if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
                /** @noinspection PhpUndefinedFieldInspection */
                $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
                if (!$clientUser) continue;
                /** @var User $clientUser */
                if ($clientUser == $user) {
                    $response = array(
                        'command' => 'showmessage',
                        'type' => ChatService::CHANNEL_GLOBAL,
                        'message' => $messageContent
                    );
                }
                else {
                    $xresponse = array(
                        'command' => 'showmessageprepend',
                        'type' => ChatService::CHANNEL_GLOBAL,
                        'message' => $messageContent
                    );
                    $wsClient->send(json_encode($xresponse));
                }
            }
        }
        return $response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function sayChat($resourceId, $contentArray)
    {
        $response = $this->isActionBlocked($resourceId, true);
        if (!$response) {
            // get user
            $ws = $this->getWebsocketServer();
            $clientData = $ws->getClientData($resourceId);
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            if (!$user) return true;
            // get profile
            $profile = $user->getProfile();
            /** @var Profile $profile */
            $messageContent = implode(' ', $contentArray);
            if (!$messageContent || $messageContent == '') return true;
            $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_SAY);
            $wsClients = $ws->getClients();
            $wsClientsData = $ws->getClientsData();
            foreach ($wsClients as $wsClient) {
                /** @var ConnectionInterface $wsClient */
                /** @noinspection PhpUndefinedFieldInspection */
                if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
                /** @noinspection PhpUndefinedFieldInspection */
                $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
                if (!$clientUser) continue;
                /** @var User $clientUser */
                // skip if they are not in the same node
                if ($clientUser->getProfile()->getCurrentNode() != $profile->getCurrentNode()) continue;
                if ($clientUser == $user) {
                    $response = array(
                        'command' => 'showmessage',
                        'type' => ChatService::CHANNEL_SAY,
                        'message' => $messageContent
                    );
                }
                else {
                    $xresponse = array(
                        'command' => 'showmessageprepend',
                        'type' => ChatService::CHANNEL_SAY,
                        'message' => $messageContent
                    );
                    $wsClient->send(json_encode($xresponse));
                }
            }
        }
        return $response;
    }

    /**
     * Prepares a message according to its channel and other options.
     * @param Profile $profile
     * @param $messageContent
     * @param $channel
     * @param bool|true $removeHtmlEntities
     * @return string
     */
    protected function prepareMessage(Profile $profile, $messageContent, $channel, $removeHtmlEntities = true)
    {
        $messageContent = ($removeHtmlEntities) ? htmLawed($messageContent, array('safe'=>1, 'elements'=>'strong, em, strike, u')) : $messageContent;
        $messageContent = sprintf('<pre style="white-space: pre-wrap;" class="text-%s">[%s] %s : %s</pre>', strtolower($channel), strtoupper($channel), $profile->getUser()->getDisplayName(), wordwrap($messageContent, 120));
        return $messageContent;
    }

}
