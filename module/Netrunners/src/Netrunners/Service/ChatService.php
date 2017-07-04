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
use Netrunners\Repository\FileRepository;
use Ratchet\ConnectionInterface;
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
     * @const CHANNEL_SAY
     */
    const CHANNEL_SAY = 'SAY';

    /**
     * @const CHANNEL_NEWBIE
     */
    const CHANNEL_NEWBIE = 'NEWBIE';

    /**
     * @var FileRepository
     */
    protected $fileRepo;


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
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function globalChat($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        // get profile
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId, true);
        // check if the have a running chat client
        if (!$this->response) {
            if (!$this->fileRepo->findChatClientForProfile($profile)) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You need a running chatclient to use global chat')
                    )
                ];
            }
        }
        if (!$this->response) {
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
                if ($clientUser === $this->user) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'type' => ChatService::CHANNEL_GLOBAL,
                        'message' => $messageContent
                    );
                }
                else {
                    if (!$this->fileRepo->findChatClientForProfile($clientUser->getProfile())) continue;
                    $xresponse = array(
                        'command' => 'showmessageprepend',
                        'type' => ChatService::CHANNEL_GLOBAL,
                        'message' => $messageContent
                    );
                    $wsClient->send(json_encode($xresponse));
                }
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function sayChat($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $ws = $this->getWebsocketServer();
            if (!$this->user) return true;
            $profile = $this->user->getProfile();
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
                if ($clientUser === $this->user) {
                    $this->response = array(
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
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
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
        if (!$messageContent || $messageContent == '') return true;
        $messageContent = $this->prepareMessage($profile, $messageContent, self::CHANNEL_NEWBIE);
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
            if ($clientUser === $this->user) {
                $this->response = array(
                    'command' => 'showmessage',
                    'type' => ChatService::CHANNEL_NEWBIE,
                    'message' => $messageContent
                );
            }
            else {
                $xresponse = array(
                    'command' => 'showmessageprepend',
                    'type' => ChatService::CHANNEL_NEWBIE,
                    'message' => $messageContent
                );
                $wsClient->send(json_encode($xresponse));
            }
        }
        return $this->response;
    }

    /**
     * Prepares a message according to its channel and other options.
     * @param Profile $profile
     * @param $messageContent
     * @param $channel
     * @param bool|true $removeHtmlEntities
     * @return string
     */
    private function prepareMessage(Profile $profile, $messageContent, $channel, $removeHtmlEntities = true)
    {
        $messageContent = ($removeHtmlEntities) ? htmLawed($messageContent, array('safe'=>1, 'elements'=>'strong, em, strike, u')) : $messageContent;
        $messageContent = sprintf('<pre style="white-space: pre-wrap;" class="text-%s">[%s] %s : %s</pre>', strtolower($channel), strtoupper($channel), $profile->getUser()->getDisplayName(), wordwrap($messageContent, 120));
        return $messageContent;
    }

}
