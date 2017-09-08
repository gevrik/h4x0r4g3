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


    public function createGroup($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        $this->response = $this->isActionBlocked($resourceId);
        // check if they are already in a group
        if (!$this->response && $profile->getGroup()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You are already a member of a group')
                )
            );
        }
        // check if they are in a faction system
        if (!$this->response && !$currentSystem->getFaction()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You must be in a faction system to create a group')
                )
            );
        }
        // check if they are in a faction
        if (!$this->response && !$profile->getFaction()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You must be a member of a faction to create a group')
                )
            );
        }
        // check if they are in a fitting faction system
        if (!$this->response && $profile->getFaction() != $currentSystem->getFaction()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You must be in a system of your faction to create a group')
                )
            );
        }
        // check if they have enough credits
        if (!$this->response && $profile->getCredits() < self::GROUP_CREATION_COST) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You need %s credits to create a group</pre>'),
                    self::GROUP_CREATION_COST
                )
            );
        }
        // now get the new name
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$this->response && !$newName) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a name for the group (alpha-numeric only, 3-chars-min, 19-chars-max)')
                )
            );
        }
        $this->stringChecker($newName, 19, 3);
        if (!$this->response) {
            $utilityService = $this->getWebsocketServer()->getUtilityService();
            // create a new addy
            $addy = $utilityService->getRandomAddress(32);
            $maxTries = 100;
            $tries = 0;
            while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
                $addy = $utilityService->getRandomAddress(32);
                $tries++;
                if ($tries >= $maxTries) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Unable to initialize the group system! Please contact an administrator!</pre>'
                    );
                }
            }
            if (!$this->response) {
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
                $this->movePlayerToTargetNode(NULL, $profile, NULL, $currentNode, $ioNode);
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">group %s has been created - you have been taken to the new group system [%s]</pre>',
                        $newName,
                        $addy
                    )
                );
            }
        }
        return $this->response;
    }

}
