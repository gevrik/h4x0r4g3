<?php

/**
 * Profile Service.
 * The service supplies methods that resolve logic around Profile objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ProfileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class PartyService extends BaseService
{

    /**
     * @var ProfileRepository
     */
    protected $profileRepository;

    /**
     * PartyService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator, $entityGenerator);
        $this->profileRepository = $this->entityManager->getRepository('Netrunners\Entity\Profile');
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createParty($resourceId)
    {
        // init service
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /** @var Profile $profile */
        $profile = $this->user->getProfile();
        // check if profile is already involved with a party
        if ($this->clientData->partyId !== null) {
            $message = $this->translate('You are already a member of a party...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good - we can create the party
        $party = [
            'leader' => $profile->getId(),
            'members' => [$profile->getId() => ['following'=>false]],
            'invitations' => [],
            'requests' => [],
            'created' => new \DateTime(),
            'effects' => [],
            'loottype' => null,
            'lootdata' => [],
        ];
        $partyId = $this->getWebsocketServer()->addParty($party);
        $this->getWebsocketServer()->setClientData($resourceId, 'partyId', $partyId);
        $message = $this->translate('You created a party...');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $xmessage = sprintf(
            $this->translate('[%s] created a party'),
            $profile->getUser()->getDisplayName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $xmessage, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function leaveParty($resourceId)
    {
        // init service
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /** @var Profile $profile */
        $profile = $this->user->getProfile();
        // check if they are in a party
        if ($this->clientData->partyId === null) {
            $message = $this->translate('You are not in a party...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $partyId = $this->clientData->partyId;
        $partyData = $this->getWebsocketServer()->getParty($partyId);
        unset($partyData['members'][$profile->getId()]);
        if ($partyData['leader'] == $profile->getId()) {
            if (empty($partyData['members'])) {
                $this->getWebsocketServer()->removeParty($partyId);
            }
            else {
                end($partyData['members']);
                $newLeaderId = key($partyData['members']);
                $partyData['leader'] = $newLeaderId;
                $partyData['members'][$newLeaderId]['following'] = false;
            }
        }
        $this->getWebsocketServer()->setClientData($resourceId, 'partyId', null);
        $this->getWebsocketServer()->setParty($partyId, $partyData);
        $message = $this->translate('You have left the party...');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $xmessage = sprintf(
            $this->translate('[%s] has left the party'),
            $profile->getUser()->getDisplayName()
        );
        $this->messageEveryoneInParty($partyId, $xmessage);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function partyCommand($resourceId)
    {
        // init service
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are in a party
        if ($this->clientData->partyId === null) {
            $message = $this->translate('You are not in a party...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $partyId = $this->clientData->partyId;
        $partyData = $this->getWebsocketServer()->getParty($partyId);
        $headerMessage = sprintf(
            '%-32s|%-4s|%-4s|%-32s|%-32s',
            $this->translate('NAME'),
            $this->translate('EEG'),
            $this->translate('WP'),
            $this->translate('SYSTEM'),
            $this->translate('NODE')
        );
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $returnMessage = [];
        foreach ($partyData['members'] as $memberProfileId => $memberData) {
            /** @var Profile $memberProfile */
            $memberProfile = $this->profileRepository->find($memberProfileId);
            $memberNode = $memberProfile->getCurrentNode();
            $classText = 'white';
            if ($memberProfileId == $this->user->getProfile()->getId()) {
                $classText = 'say';
            }
            else {
                if ($memberProfileId == $partyData['leader']) {
                    $classText = 'newbie';
                }
            }
            $returnMessage[] = sprintf(
                '<span class="text-%s">%-32s|%-4s|%-4s|%-32s|%-32s</span>',
                $classText,
                $memberProfile->getUser()->getUsername(),
                $memberProfile->getEeg(),
                $memberProfile->getWillpower(),
                $memberNode->getSystem()->getName(),
                $memberNode->getName()
            );
        }
        return $this->gameClientResponse->addMessages($returnMessage)->send();
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
    public function partyInviteCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are in a party and if they are the leader
        if (!$this->clientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('You are not in a party'))->send();
        }
        $party = $this->getWebsocketServer()->getParty($this->clientData->partyId);
        if ($party['leader'] != $profile->getId()) {
            return $this->gameClientResponse->addMessage($this->translate('You must be the leader of the party to invite other users'))->send();
        }
        // get invitee name
        $parameter = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$parameter) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the name of the user that you want to invite'))->send();
        }
        $targetProfile = $this->profileRepository->findLikeName($parameter);
        // sanity checks
        if (!$targetProfile) {
            return $this->gameClientResponse->addMessage($this->translate('No such user online'))->send();
        }
        if ($targetProfile === $profile) {
            return $this->gameClientResponse->addMessage($this->translate('We are starting to worry about you...'))->send();
        }
        $inviteeClientData = $this->getWebsocketServer()->getClientData($targetProfile->getCurrentResourceId());
        if (!$inviteeClientData) {
            return $this->gameClientResponse->addMessage($this->translate('No such user online'))->send();
        }
        if ($inviteeClientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('That user is already a member of another party'))->send();
        }
        if (array_key_exists($targetProfile->getId(), $party['invitations'])) {
            return $this->gameClientResponse->addMessage($this->translate('That user has already been invited to the party'))->send();
        }
        // all seems good, we now check if the target profile has already requested an invite
        if (array_key_exists($targetProfile->getId(), $party['requests'])) {
            // target profile already requested an invite, add them
            $party['members'][$targetProfile->getId()] = ['following'=>false];
            unset($party['requests'][$targetProfile->getId()]);
            if (array_key_exists($targetProfile->getId(), $party['invitations'])) {
                unset($party['invitations'][$targetProfile->getId()]);
            }
            $this->getWebsocketServer()->setClientData($targetProfile->getCurrentResourceId(), 'partyId', $this->clientData->partyId);
            $this->getWebsocketServer()->setParty($this->clientData->partyId, $party);
            $xmessage = $this->translate(sprintf('[%s] has accepted you into their party', $profile->getUser()->getUsername()));
            $message = $this->translate(sprintf('You have accepted [%s] into your party', $targetProfile->getUser()->getUsername()));
        }
        else {
            // add profile to invitations
            $party['invitations'][$targetProfile->getId()] = [];
            $this->getWebsocketServer()->setParty($this->clientData->partyId, $party);
            $xmessage = $this->translate(sprintf('[%s] has invited you to a party', $profile->getUser()->getUsername()));
            $message = $this->translate(sprintf('You have invited [%s] to your party', $targetProfile->getUser()->getUsername()));
        }
        // TODO check party size with leadership skill
        $this->messageProfileNew($targetProfile, $xmessage, GameClientResponse::CLASS_INFO);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
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
    public function partyRequestCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are in a party and if they are the leader
        if ($this->clientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('You are already in a party'))->send();
        }
        // get leader name
        $parameter = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$parameter) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the name of the party leader that you want to join'))->send();
        }
        $leaderProfile = $this->profileRepository->findLikeName($parameter);
        // sanity checks
        if (!$leaderProfile) {
            return $this->gameClientResponse->addMessage($this->translate('No such user online'))->send();
        }
        if ($leaderProfile === $profile) {
            return $this->gameClientResponse->addMessage($this->translate('We are starting to worry about you...'))->send();
        }
        $leaderClientData = $this->getWebsocketServer()->getClientData($leaderProfile->getCurrentResourceId());
        if (!$leaderClientData) {
            return $this->gameClientResponse->addMessage($this->translate('No such user online'))->send();
        }
        if (!$leaderClientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('That user is not in a party'))->send();
        }
        $party = $this->getWebsocketServer()->getParty($leaderClientData->partyId);
        if ($party['leader'] != $leaderProfile->getId()) {
            return $this->gameClientResponse->addMessage($this->translate('That user is not leading a party'))->send();
        }
        if (array_key_exists($profile->getId(), $party['requests'])) {
            return $this->gameClientResponse->addMessage($this->translate('You have already requested to join that party'))->send();
        }
        // all seems good, we now check if the profile has already been invited
        if (array_key_exists($profile->getId(), $party['invitations'])) {
            // profile already invited, add them
            $party['members'][$profile->getId()] = ['following'=>false];
            unset($party['invitations'][$profile->getId()]);
            if (array_key_exists($profile->getId(), $party['requests'])) {
                unset($party['requests'][$profile->getId()]);
            }
            $this->getWebsocketServer()->setClientData($profile->getCurrentResourceId(), 'partyId', $leaderClientData->partyId);
            $this->getWebsocketServer()->setParty($leaderClientData->partyId, $party);
            $message = $this->translate(sprintf('You have joined the party of [%s]', $leaderProfile->getUser()->getUsername()));
            $xmessage = $this->translate(sprintf('[%s] has joined your party', $profile->getUser()->getUsername()));
        }
        else {
            // add profile to requests
            $party['requests'][$profile->getId()] = [];
            $this->getWebsocketServer()->setParty($leaderClientData->partyId, $party);
            $message = $this->translate(sprintf('You have asked [%s] to join their party', $leaderProfile->getUser()->getUsername()));
            $xmessage = $this->translate(sprintf('[%s] wants to join your party', $profile->getUser()->getUsername()));
        }
        // TODO check party size with leadership skill
        $this->messageProfileNew($leaderProfile, $xmessage, GameClientResponse::CLASS_ATTENTION);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
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
    public function partyKickCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are in a party and if they are the leader
        if (!$this->clientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('You are not in a party'))->send();
        }
        $party = $this->getWebsocketServer()->getParty($this->clientData->partyId);
        if ($party['leader'] != $profile->getId()) {
            return $this->gameClientResponse->addMessage($this->translate('You must be the leader of the party to invite other users'))->send();
        }
        // get target name
        $parameter = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$parameter) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the name of the user that you want to kick'))->send();
        }
        $targetProfile = $this->profileRepository->findLikeName($parameter);
        // sanity checks
        if (!$targetProfile) {
            return $this->gameClientResponse->addMessage($this->translate('No such user online'))->send();
        }
        if ($targetProfile === $profile) {
            return $this->gameClientResponse->addMessage($this->translate('We are starting to worry about you...'))->send();
        }
        $targetClientData = $this->getWebsocketServer()->getClientData($targetProfile->getCurrentResourceId());
        if (!$targetClientData) {
            return $this->gameClientResponse->addMessage($this->translate('No such user online'))->send();
        }
        if ($targetClientData->partyId != $this->clientData->partyId) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid user for kick'))->send();
        }
        // all seems good, we now kick the user from the party
        unset($party['members'][$targetProfile->getId()]);
        $this->getWebsocketServer()->setClientData($targetProfile->getCurrentResourceId(), 'partyId', null);
        $this->getWebsocketServer()->setParty($this->clientData->partyId, $party);
        $xmessage = $this->translate(sprintf('[%s] has kicked you from their party', $profile->getUser()->getUsername()));
        $message = $this->translate(sprintf('You have kicked [%s] from your party', $targetProfile->getUser()->getUsername()));
        $this->messageProfileNew($targetProfile, $xmessage, GameClientResponse::CLASS_INFO);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function partyFollowCommand($resourceId)
    {
        // init service
        $this->initService($resourceId);
        if (!$this->user) return false;
        /** @var Profile $profile */
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are in a party
        if ($this->clientData->partyId === null) {
            $message = $this->translate('You are not in a party...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $partyId = $this->clientData->partyId;
        $partyData = $this->getWebsocketServer()->getParty($partyId);
        if ($partyData['leader'] == $profile->getId()) {
            $message = $this->translate('Unable to follow yourself...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($partyData['members'][$profile->getId()]['following']) {
            $partyData['members'][$profile->getId()]['following'] = false;
            $message = $this->translate('You stop following the party leader...');
        }
        else {
            $partyData['members'][$profile->getId()]['following'] = true;
            $message = $this->translate('You start following the party leader...');
        }
        $this->getWebsocketServer()->setParty($partyId, $partyData);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

}
