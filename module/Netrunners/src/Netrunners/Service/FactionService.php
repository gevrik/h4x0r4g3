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
use Netrunners\Repository\FactionRepository;
use Netrunners\Repository\ProfileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class FactionService extends BaseService
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
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->factionRepo = $this->entityManager->getRepository('Netrunners\Entity\Faction');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function listFactions($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $messages = [];
            $factions = $this->factionRepo->findAll();
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%-7s|%-6s</pre>',
                $this->translate('name'),
                $this->translate('members'),
                $this->translate('rating')
            );
            foreach ($factions as $faction) {
                /** @var Faction $faction */
                $messages[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-%s">%-32s|%-7s|%-6s</pre>',
                    ($this->user->getProfile()->getFaction() == $faction) ? 'newbie' : 'white',
                    $faction->getName(),
                    $this->profileRepo->countByFaction($faction),
                    $this->getProfileFactionRating($this->user->getProfile(), $faction)
                );
            }
            $this->response = [
                'command' => 'showoutput',
                'message' => $messages
            ];
        }
        return $this->response;
    }

    public function joinFaction($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            // check if they are already in a faction
            if (!$this->response && $profile->getFaction()) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You are already a member of a faction - you need to leave that faction before you can join another one')
                    )
                ];
            }
            $faction = NULL;
            // check if they are in a recruitment node
            if (!$this->response && $profile->getCurrentNode()->getNodeType()->getId() != NodeType::ID_RECRUITMENT) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You must be in a recruitment node of the faction that you want to join')
                    )
                ];
            }
            // check if they are currently blocked from joining a faction
            if (!$this->response && $profile->getFactionJoinBlockDate() > new \DateTime()) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You must wait until [%s] before you can join another faction - use "time" to get the current server time</pre>'),
                        $profile->getFactionJoinBlockDate()->format('Y/m/d H:i:s')
                    )
                ];
            }
            if (!$this->response) {
                $faction = $profile->getCurrentNode()->getSystem()->getFaction();
                /** @var Faction $faction */
            }
            // check if the faction is joinable or invite-only
            if (!$this->response && $faction && !$faction->getJoinable()) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('This faction is invite-only')
                    )
                ];
            }
            // check if it is open recruitment or if they need to write an application
            if (!$this->response && $faction && !$faction->getOpenRecruitment()) {
                // TODO need to write application to join faction
            }
            /* checks passed, we can join the faction */
            if (!$this->response && $faction) {
                $profile->setFaction($faction);
                $this->entityManager->flush($profile);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You have joined [%s]</pre>'),
                        $faction->getName()
                    )
                ];
            }
        }
        return $this->response;
    }

    public function leaveFaction()
    {

    }

}
