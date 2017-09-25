<?php

/**
 * Chat Service.
 * The service supplies methods that resolve logic around the in-game chat.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\ProfileRepository;
use Ratchet\ConnectionInterface;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class ChatService extends BaseService
{

    /**
     * @const CHANNEL_GLOBAL
     */
    const CHANNEL_GLOBAL = 'GCHAT';

    /**
     * @const CHANNEL_MODERATOR
     */
    const CHANNEL_MODERATOR = 'MODCHAT';

    /**
     * @const CHANNEL_SAY
     */
    const CHANNEL_SAY = 'SAY';

    /**
     * @const CHANNEL_TELL
     */
    const CHANNEL_TELL = 'TELL';

    /**
     * @const CHANNEL_NEWBIE
     */
    const CHANNEL_NEWBIE = 'NEWBIE';

    /**
     * @const CHANNEL_FACTION
     */
    const CHANNEL_FACTION = 'FACTION';

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;


    /**
     * ChatService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function globalChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get profile
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if the have a running chat client
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to use global chat'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_GLOBAL);
        $wsClients = $ws->getClients();
        $wsClientsData = $ws->getClientsData();
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($wsClients as $wsClient) {
            /** @noinspection PhpUndefinedFieldInspection */
            if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
            if (!$clientUser) continue;
            /** @var User $clientUser */
            if ($clientUser === $this->user) continue;
            if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $this->gameClientResponse->setResourceId($wsClient->resourceId);
            $this->gameClientResponse->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function moderatorChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get profile
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if the have mod role
        if (!$this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            return $this->gameClientResponse->addMessage($this->translate('Access denied'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_MODERATOR);
        $wsClients = $ws->getClients();
        $wsClientsData = $ws->getClientsData();
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($wsClients as $wsClient) {
            /** @var ConnectionInterface $wsClient */
            /** @noinspection PhpUndefinedFieldInspection */
            if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
            if (!$clientUser) continue;
            /** @var User $clientUser */
            if (!$this->hasRole($clientUser, Role::ROLE_ID_MODERATOR)) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $this->gameClientResponse->setResourceId($wsClient->resourceId);
            $this->gameClientResponse->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function factionChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get profile
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if the have a running chat client
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to use faction chat'))->send();
        }
        if (!$profile->getFaction()) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be a member of a faction to use faction chat'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_FACTION);
        $wsClients = $ws->getClients();
        $wsClientsData = $ws->getClientsData();
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($wsClients as $wsClient) {
            /** @var ConnectionInterface $wsClient */
            /** @noinspection PhpUndefinedFieldInspection */
            if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
            if (!$clientUser) continue;
            /** @var User $clientUser */
            if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
            if ($clientUser->getProfile()->getFaction() != $profile->getFaction()) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $this->gameClientResponse->setResourceId($wsClient->resourceId);
            $this->gameClientResponse->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function sayChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_SAY);
        $wsClients = $ws->getClients();
        $wsClientsData = $ws->getClientsData();
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
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
            /** @noinspection PhpUndefinedFieldInspection */
            $this->gameClientResponse->setResourceId($wsClient->resourceId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function tellChat($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $messageContent = NULL;
        $recipient = NULL;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // sanity checks
        list($contentArray, $recipientName) = $this->getNextParameter($contentArray, true, false, false, true);
        $recipient = $this->profileRepo->findLikeName($recipientName, $profile, true);
        $messageContent = $this->getNextParameter($contentArray, false, false, true);
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to send tell messages'))->send();
        }
        if (!$recipientName) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a recipient by name'))->send();
        }
        if (!$recipient) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid recipient for message - might be offline or non-existent'))->send();
        }
        if (!$messageContent) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        // logic start
        $ws = $this->getWebsocketServer();
        // prepare message for recipient, send and set client-data
        $recipientMessage = $this->prepareMessage($profile, $messageContent, self::CHANNEL_TELL, true, 'FROM ');
        $this->gameClientResponse->addMessage($recipientMessage)
            ->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND)
            ->setResourceId($recipient->getCurrentResourceId())
            ->send();
        $ws->setClientData($recipient->getCurrentResourceId(), 'replyId', $profile->getId());
        // create response for sender
        $senderMessage = $this->prepareMessage($recipient, $messageContent, self::CHANNEL_TELL, true, 'TO ');
        // TODO add ignore system and anonymous flag
        return $this->gameClientResponse
            ->reset()
            ->setResourceId($resourceId)
            ->addMessage($senderMessage)
            ->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function newbieChat($resourceId, $contentArray)
    {
        // get user
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get profile
        $profile = $this->user->getProfile();
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_NEWBIE);
        $wsClients = $ws->getClients();
        $wsClientsData = $ws->getClientsData();
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($wsClients as $wsClient) {
            /** @var ConnectionInterface $wsClient */
            /** @noinspection PhpUndefinedFieldInspection */
            if (!$wsClientsData[$wsClient->resourceId]['hash']) continue;
            /** @noinspection PhpUndefinedFieldInspection */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClientsData[$wsClient->resourceId]['userId']);
            if (!$clientUser) continue;
            /** @var User $clientUser */
            /** @noinspection PhpUndefinedFieldInspection */
            $this->gameClientResponse->setResourceId($wsClient->resourceId)->send();
        }
        return $this->gameClientResponse
            ->setResourceId($profile->getCurrentResourceId())
            ->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)
            ->send();
    }

    /**
     * Prepares a message according to its channel and other options.
     * @param Profile $profile
     * @param $messageContent
     * @param $channel
     * @param bool|true $removeHtmlEntities
     * @param string $textAddition
     * @return string
     */
    private function prepareMessage(Profile $profile, $messageContent, $channel, $removeHtmlEntities = true, $textAddition = '')
    {
        $messageContent = ($removeHtmlEntities) ? htmLawed($messageContent, array('safe'=>1, 'elements'=>'strong, em, strike, u')) : $messageContent;
        $messageContent = sprintf(
            '<span class="text-%s">[%s] %s%s : %s</span>',
            strtolower($channel),
            strtoupper($channel),
            $textAddition,
            $profile->getUser()->getDisplayName(),
            wordwrap($messageContent, 120)
        );
        return $messageContent;
    }

}
