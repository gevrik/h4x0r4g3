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
    const CHANNEL_GLOBAL = 'gchat';


    public function globalChat($resourceId, $contentArray)
    {
        // get user
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') return true;
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_GLOBAL);
        $wsClients = $this->getWebsocketServer()->getClients();
        $wsClientsData = $this->getWebsocketServer()->getClientsData();
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
                $response = array(
                    'command' => 'showmessageprepend',
                    'type' => ChatService::CHANNEL_GLOBAL,
                    'message' => $messageContent
                );
            }
            $wsClient->send(json_encode($response));
        }
        return true;
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
        $messageContent = "[" . strtoupper($channel) . "] " . $profile->getUser()->getDisplayName() . ' : ' . $messageContent;
        return $messageContent;
    }

}
