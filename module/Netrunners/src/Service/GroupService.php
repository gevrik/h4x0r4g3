<?php

/**
 * GroupService.
 * This service resolves logic around the player groups (guilds).
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Group;
use Netrunners\Entity\GroupRole;
use Netrunners\Entity\GroupRoleInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\GroupRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class GroupService extends BaseService
{

    const GROUP_CREATION_COST = 100000;

    /**
     * @var GroupRepository
     */
    protected $groupRepo;


    /**
     * GroupService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->groupRepo = $this->entityManager->getRepository('Netrunners\Entity\Group');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createGroup($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if they are already in a group
        if ($profile->getGroup()) {
            $message = $this->translate('You are already a member of a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are in a faction system
        if (!$currentSystem->getFaction()) {
            $message = $this->translate('You must be in a faction system to create a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are in a faction
        if (!$profile->getFaction()) {
            $message = $this->translate('You must be a member of a faction to create a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they are in a fitting faction system
        if ($profile->getFaction() != $currentSystem->getFaction()) {
            $message = $this->translate('You must be in a system of your faction to create a group');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they have enough credits
        if ($profile->getCredits() < self::GROUP_CREATION_COST) {
            $message = sprintf(
                $this->translate('You need %s credits to create a group'),
                self::GROUP_CREATION_COST
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the new name
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$newName) {
            $message = $this->translate('Please specify a name for the group (alpha-numeric only, 3-chars-min, 19-chars-max)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $checkResult = $this->stringChecker($newName, 19, 3);
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        $utilityService = $this->getWebsocketServer()->getUtilityService();
        // create a new addy
        $addy = $utilityService->getRandomAddress(32);
        $maxTries = 100;
        $tries = 0;
        while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
            $addy = $utilityService->getRandomAddress(32);
            $tries++;
            if ($tries >= $maxTries) {
                $message = $this->translate('Unable to initialize the group system! Please contact an administrator!');
                return $this->gameClientResponse->addMessage($message)->send();
            }
        }
        $newName = str_replace(' ', '_', $newName);
        $faction = $currentSystem->getFaction();
        $group = new Group();
        $group->setCredits(ceil(round(self::GROUP_CREATION_COST / 2)));
        $group->setSnippets(0);
        $group->setAdded(new \DateTime());
        $group->setDescription('this group does not have a description');
        $group->setFaction($faction);
        $group->setName($newName);
        $this->entityManager->persist($group);
        // founder role
        $groupRole = $this->entityManager->find('Netrunners\Entity\GroupRole', GroupRole::ROLE_FOUNDER_ID);
        /** @var GroupRole $groupRole */
        $gri = new GroupRoleInstance();
        $gri->setAdded(new \DateTime());
        $gri->setChanger(NULL);
        $gri->setGroup($group);
        $gri->setGroupRole($groupRole);
        $gri->setMember($profile);
        $this->entityManager->persist($gri);
        // leader role
        $groupRole = $this->entityManager->find('Netrunners\Entity\GroupRole', GroupRole::ROLE_LEADER_ID);
        /** @var GroupRole $groupRole */
        $gri = new GroupRoleInstance();
        $gri->setAdded(new \DateTime());
        $gri->setChanger(NULL);
        $gri->setGroup($group);
        $gri->setGroupRole($groupRole);
        $gri->setMember($profile);
        $this->entityManager->persist($gri);
        // create group system
        $system = new System();
        $system->setProfile(NULL);
        $system->setName($newName . '_headquarters');
        $system->setAddy($addy);
        $system->setGroup($group);
        $system->setFaction(NULL);
        $system->setMaxSize(System::GROUP_MAX_SYSTEM_SIZE);
        $system->setAlertLevel(0);
        $system->setNoclaim(false);
        $system->setGeocoords(NULL); // TODO add geocoords
        $this->entityManager->persist($system);
        // default cpu node
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU);
        /** @var NodeType $nodeType */
        $cpuNode = new Node();
        $cpuNode->setCreated(new \DateTime());
        $cpuNode->setLevel(1);
        $cpuNode->setName($nodeType->getName());
        $cpuNode->setSystem($system);
        $cpuNode->setNodeType($nodeType);
        $this->entityManager->persist($cpuNode);
        // default private io node
        $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_IO);
        /** @var NodeType $nodeType */
        $ioNode = new Node();
        $ioNode->setCreated(new \DateTime());
        $ioNode->setLevel(5);
        $ioNode->setName($nodeType->getName());
        $ioNode->setSystem($system);
        $ioNode->setNodeType($nodeType);
        $this->entityManager->persist($ioNode);
        // connection between nodes
        $connection = new Connection();
        $connection->setCreated(new \DateTime());
        $connection->setLevel(5);
        $connection->setIsOpen(NULL);
        $connection->setSourceNode($cpuNode);
        $connection->setTargetNode($ioNode);
        $connection->setType(Connection::TYPE_CODEGATE);
        $this->entityManager->persist($connection);
        $connection = new Connection();
        $connection->setCreated(new \DateTime());
        $connection->setLevel(5);
        $connection->setIsOpen(NULL);
        $connection->setTargetNode($cpuNode);
        $connection->setSourceNode($ioNode);
        $connection->setType(Connection::TYPE_CODEGATE);
        $this->entityManager->persist($connection);
        $profile->setGroup($group);
        $this->entityManager->flush();
        $this->movePlayerToTargetNodeNew(NULL, $profile, NULL, $currentNode, $ioNode);
        $message = sprintf(
            'group %s has been created - you have been taken to the new group system [%s]',
            $newName,
            $addy
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
        return $this->gameClientResponse->send();
    }

}
