<?php

/**
 * FactionService.
 * This service resolves logic around the factions.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Faction;
use Netrunners\Entity\NodeType;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FactionRepository;
use Netrunners\Repository\ProfileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class FactionService extends BaseService
{

    /**
     * @var FactionRepository
     */
    protected $factionRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;


    /**
     * FactionService constructor.
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
        $this->factionRepo = $this->entityManager->getRepository('Netrunners\Entity\Faction');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
    }

    /**
     * @param $resourceId
     * @return array|bool|false|\Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listFactions($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $factions = $this->factionRepo->findAll();
        $message = sprintf(
            '%-32s|%-7s|%-6s',
            $this->translate('NAME'),
            $this->translate('MEMBERS'),
            $this->translate('RATING')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        foreach ($factions as $faction) {
            /** @var Faction $faction */
            $message = sprintf(
                '<span class="text-%s">%-32s|%-7s|%-6s</span>',
                ($this->user->getProfile()->getFaction() == $faction) ? 'newbie' : 'white',
                $faction->getName(),
                $this->profileRepo->countByFaction($faction),
                $this->getProfileFactionRating($this->user->getProfile(), $faction)
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function joinFaction($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        // check if they are already in a faction
        if ($profile->getFaction()) {
            $message = $this->translate('You are already a member of a faction - you need to leave that faction before you can join another one');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $faction = NULL;
        // check if they are in a recruitment node
        if ($profile->getCurrentNode()->getNodeType()->getId() != NodeType::ID_RECRUITMENT) {
            $message = $this->translate('You must be in a recruitment node of the faction that you want to join');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are currently blocked from joining a faction
        if ($profile->getFactionJoinBlockDate() > new \DateTime()) {
            $message = sprintf(
                $this->translate('You must wait until [%s] before you can join another faction - use "time" to get the current server time'),
                $profile->getFactionJoinBlockDate()->format('Y/m/d H:i:s')
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $faction = $profile->getCurrentNode()->getSystem()->getFaction();
        /** @var Faction $faction */
        // check if the faction is joinable or invite-only
        if (!$faction->getJoinable()) {
            $message = $this->translate('This faction is invite-only');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if it is open recruitment or if they need to write an application
        if (!$faction->getOpenRecruitment()) {
            $message = $this->translate('You can not join this faction without an invitation');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* checks passed, we can join the faction */
        $profile->setFaction($faction);
        $this->entityManager->flush($profile);
        $message = sprintf(
            $this->translate('You have joined [%s]'),
            $faction->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has joined [%s]'),
            $this->user->getUsername(),
            $faction->getName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function leaveFaction($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        // check if they are already in a faction
        $faction = $profile->getFaction();
        if (!$faction) {
            $message = $this->translate('You are not a member of any faction');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $profile->setFaction(null);
        $factionJoinBlockData = new \DateTime();
        $factionJoinBlockData->add(new \DateInterval('PT1D'));
        $profile->setFactionJoinBlockDate($factionJoinBlockData);
        $message = sprintf(
            $this->translate('You have left [%s]'),
            $faction->getName()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

}
