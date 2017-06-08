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

class ChatService extends BaseService
{

    /**
     * @const CHANNEL_GLOBAL
     */
    const CHANNEL_GLOBAL = 'gchat';


    /**
     * @param $clientData
     * @param $contentArray
     * @return bool|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function globalChat($clientData, $contentArray)
    {
        // get user
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // get profile
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') return true;
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_GLOBAL);
        return $messageContent;
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
        $messageContent = ($removeHtmlEntities) ? htmlentities($messageContent) : $messageContent;
        $messageContent = "[" . strtoupper($channel) . "] " . $profile->getUser()->getDisplayName() . ' : ' . $messageContent;
        return $messageContent;
    }

}
