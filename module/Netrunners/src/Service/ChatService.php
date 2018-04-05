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
use Netrunners\Entity\GameOption;
use Netrunners\Entity\Pignore;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\PignoreRepository;
use Netrunners\Repository\ProfileRepository;
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
     * @const CHANNEL_GROUP
     */
    const CHANNEL_GROUP = 'GROUP';

    /**
     * @const CHANNEL_PARTY
     */
    const CHANNEL_PARTY = 'PARTY';

    /**
     * @const CHANNEL_SYSTEM
     */
    const CHANNEL_SYSTEM = 'SYSTEM';

    /**
     * @const CHANNEL_TRADE
     */
    const CHANNEL_TRADE = 'TRADE';

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;

    /**
     * @var PignoreRepository
     */
    protected $pignoreRepo;


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
        $this->pignoreRepo = $this->entityManager->getRepository(Pignore::class);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
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
        if (!$this->getProfileGameOption($profile, GameOption::ID_SHOW_GLOBAL_CHAT)) {
            return $this->gameClientResponse->addMessage($this->translate('You have global chat deactivated in options'))->send();
        }
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
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
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            if (!$this->getProfileGameOption($clientUser->getProfile(), GameOption::ID_SHOW_SYSTEM_CHAT)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function tradeChat($resourceId, $contentArray)
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
        if (!$this->getProfileGameOption($profile, GameOption::ID_SHOW_TRADE_CHAT)) {
            return $this->gameClientResponse->addMessage($this->translate('You have trade chat deactivated in options'))->send();
        }
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        // check if the have a running chat client
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to use global chat'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_TRADE);
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            if (!$this->getProfileGameOption($clientUser->getProfile(), GameOption::ID_SHOW_TRADE_CHAT)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
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
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
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
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if (!$this->hasRole($clientUser, Role::ROLE_ID_MODERATOR)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
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
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
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
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if ($clientUser->getProfile()->getFaction() != $profile->getFaction()) continue;
            if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function partyChat($resourceId, $contentArray)
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
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        if (!$this->clientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be a member of a party to use party chat'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_PARTY);
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            if ($wsClient['partyId'] != $this->clientData->partyId) continue;
            if ($wsClientId == $resourceId) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
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
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_SAY);
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find(User::class, $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if ($clientUser->getProfile()->getCurrentNode() != $profile->getCurrentNode()) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function systemChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to send system messages'))->send();
        }
        if (!$this->getProfileGameOption($profile, GameOption::ID_SHOW_SYSTEM_CHAT)) {
            return $this->gameClientResponse->addMessage($this->translate('You have system chat deactivated in options'))->send();
        }
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_SYSTEM);
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find(User::class, $wsClient['userId']);
            $clientProfile = $clientUser->getProfile();
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if (!$this->fileRepo->findChatClientForProfile($clientProfile)) continue;
            if ($clientProfile->getCurrentNode()->getSystem() != $profile->getCurrentNode()->getSystem()) continue;
            if ($this->isIgnoredBy($clientProfile, $profile)) continue;
            if (!$this->getProfileGameOption($clientUser->getProfile(), GameOption::ID_SHOW_SYSTEM_CHAT)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function emoteChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify an emote message'))->send();
        }
        $messageContent = htmLawed($messageContent, array('safe'=>1, 'elements'=>'strong, em, strike, u'));
        $message = sprintf(
            '<span class="text-say">[%s] %s %s</span>',
            $this->translate('EMOTE'),
            $this->user->getUsername(),
            $messageContent
        );
        $this->gameClientResponse->addMessage($message)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find('TmoAuth\Entity\User', $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if ($clientUser->getProfile()->getCurrentNode() != $profile->getCurrentNode()) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     * @throws \Exception
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
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
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
        if (!$this->fileRepo->findChatClientForProfile($recipient)) {
            return $this->gameClientResponse->addMessage($this->translate('Your contact needs a running chatclient to receive tell messages'))->send();
        }
        if ($recipient->getNoTells()) {
            return $this->gameClientResponse->addMessage($this->translate('That user is currently not receiving any tell messages'))->send();
        }
        if ($this->isIgnoredBy($recipient, $profile)) {
            return $this->gameClientResponse->addMessage($this->translate('That user is currently not receiving any tell messages from you'))->send();
        }
        // logic start
        $ws = $this->getWebsocketServer();
        // prepare message for recipient, send and set client-data
        $recipientMessage = $this->prepareMessage($profile, $messageContent, self::CHANNEL_TELL, true, 'FROM ');
        $this->gameClientResponse->addMessage($recipientMessage)
            ->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND)
            ->setResourceId($recipient->getCurrentResourceId())
            ->send();
        $ws->setClientDataReplyId($recipient->getCurrentResourceId(), $profile->getId());
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
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     * @throws \Exception
     */
    public function replyChat($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $messageContent = NULL;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        $ws = $this->getWebsocketServer();
        // sanity checks
        $recipientId = $ws->getClientDataReplyId($resourceId);
        if (!$recipientId) {
            return $this->gameClientResponse->addMessage($this->translate('Nobody has messaged you'))->send();
        }
        /** @var Profile $recipient */
        $recipient = $this->profileRepo->find($recipientId);
        $messageContent = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to send tell messages'))->send();
        }
        if (!$recipient) {
            return $this->gameClientResponse->addMessage($this->translate('Nobody has messaged you'))->send();
        }
        if (!$messageContent) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        if (!$this->fileRepo->findChatClientForProfile($recipient)) {
            return $this->gameClientResponse->addMessage($this->translate('Your reply target has no running chatclient'))->send();
        }
        if (!$this->isIgnoredBy($recipient, $profile)) {
            return $this->gameClientResponse->addMessage($this->translate('That user is currently not receiving any tell messages from you'))->send();
        }
        // logic start
        // prepare message for recipient, send and set client-data
        $recipientMessage = $this->prepareMessage($profile, $messageContent, self::CHANNEL_TELL, true, 'FROM ');
        $this->gameClientResponse->addMessage($recipientMessage)
            ->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND)
            ->setResourceId($recipient->getCurrentResourceId())
            ->send();
        $ws->setClientDataReplyId($recipient->getCurrentResourceId(), $profile->getId());
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
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function newbieChat($resourceId, $contentArray)
    {
        // get user
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get profile
        $profile = $this->user->getProfile();
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        if (!$this->getProfileGameOption($profile, GameOption::ID_SHOW_NEWBIE_CHAT)) {
            return $this->gameClientResponse->addMessage($this->translate('You have newbie chat deactivated in options'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_NEWBIE);
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find(User::class, $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            if (!$this->getProfileGameOption($clientUser->getProfile(), GameOption::ID_SHOW_NEWBIE_CHAT)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse
            ->setResourceId($profile->getCurrentResourceId())
            ->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)
            ->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     * @throws \Exception
     */
    public function groupChat($resourceId, $contentArray)
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
        if ($profile->getSilenced()) {
            return $this->gameClientResponse->addMessage($this->translate('You are currently silenced'))->send();
        }
        // check if the have a running chat client
        if (!$this->fileRepo->findChatClientForProfile($profile)) {
            return $this->gameClientResponse->addMessage($this->translate('You need a running chatclient to use group chat'))->send();
        }
        if (!$profile->getGroup()) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be a member of a group to use group chat'))->send();
        }
        $messageContent = implode(' ', $contentArray);
        if (!$messageContent || $messageContent == '') {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a message'))->send();
        }
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_GROUP);
        $this->gameClientResponse->addMessage($messageContent)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        foreach ($ws->getClientsData() as $wsClientId => $wsClient) {
            if (!$wsClient['hash']) continue;
            /** @var User $clientUser */
            $clientUser = $this->entityManager->find(User::class, $wsClient['userId']);
            if (!$clientUser) continue;
            if ($wsClientId == $resourceId) continue;
            if ($clientUser->getProfile()->getGroup() != $profile->getGroup()) continue;
            if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
            if ($this->isIgnoredBy($clientUser->getProfile(), $profile)) continue;
            $this->gameClientResponse->setResourceId($wsClientId)->send();
        }
        return $this->gameClientResponse->setResourceId($resourceId)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT)->send();
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

    /**
     * @param Profile $sourceProfile
     * @param Profile $targetProfile
     * @return bool
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function isIgnoredBy(Profile $sourceProfile, Profile $targetProfile)
    {
        $pigCount = $this->pignoreRepo->countBySourceAndTarget($sourceProfile, $targetProfile);
        return ($pigCount >= 1) ? true : false;
    }

}
