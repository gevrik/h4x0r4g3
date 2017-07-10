<?php

/**
 * Combat Service.
 * The service supplies methods that resolve logic around combat.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\NpcRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class CombatService extends BaseService
{

    /**
     * @var NpcRepository
     */
    protected $npcRepo;

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;


    /**
     * CombatService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->npcRepo = $this->entityManager->getRepository('Netrunners\Entity\Npc');
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function attackCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            // get parameter
            $parameter = $this->getNextParameter($contentArray, false);
            $searchByNumber = false;
            if (is_numeric($parameter)) {
                $searchByNumber = true;
            }
            $npcs = $this->npcInstanceRepo->findBy([
                'node' => $currentNode
            ]);
            $npc = false;
            if ($searchByNumber) {
                if (isset($npcs[$parameter - 1])) {
                    $npc = $npcs[$parameter - 1];
                }
            }
            else {
                foreach ($npcs as $xnpc) {
                    /** @var Npc $xnpc */
                    if ($xnpc->getName() == $parameter) {
                        $npc = $xnpc;
                        break;
                    }
                }
            }
            if (!$this->response && !$npc) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('No such entity')
                    )
                );
            }
            if (!$this->response) {
                $this->getWebsocketServer()->addCombatant($profile, $npc, $resourceId);
                $this->getWebsocketServer()->addCombatant($npc, $profile, NULL, $resourceId);
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You attack [%s]</pre>'),
                        $npc->getName()
                    )
                );
            }
        }
        return $this->response;
    }

    /**
     * @param NpcInstance|Profile $attacker
     * @param NpcInstance|Profile $defender
     * @return array
     */
    public function resolveCombatRound($attacker, $defender)
    {
        if (!$attacker || !$defender) return [NULL, NULL];
        // init vars
        $skillRating = 0;
        $blade = NULL;
        $defenseRating = 0;
        $damage = 1;
        $attackerMessage = NULL;
        $defenderMessage = NULL;
        $defenderName = ($defender instanceof NpcInstance) ? $defender->getName() : $defender->getUser()->getUsername();
        $attackerName = ($attacker instanceof NpcInstance) ? $attacker->getName() : $attacker->getUser()->getUsername();
        // modifier for profile attacker
        if ($attacker instanceof Profile) {
            $skillRating += $this->getSkillRating($attacker, Skill::ID_BLADES);
            $blade = $attacker->getBlade();
            if ($blade) {
                $skillRating += $blade->getLevel();
                $damage = ceil(round($blade->getIntegrity()/10));
            }
        }
        // modifier for npc attacker
        if ($attacker instanceof NpcInstance) {
            $skillRating += $this->getSkillRating($attacker, Skill::ID_BLADES);
        }
        if ($defender instanceof Profile) {
            $defenseRating += ($defender->getBlade()) ? $defender->getBlade()->getLevel() : 0;
        }
        if ($defender instanceof NpcInstance) {
            $defenseRating = $this->getSkillRating($defender, Skill::ID_BLADES);
        }
        $roll = mt_rand(1, 100);
        if ($roll <= ($skillRating - $defenseRating)) {
            // hit
            if ($attacker instanceof Profile) {
                $this->learnFromSuccess($attacker, ['skills' => ['blades']], -50);
            }
            if ($defender instanceof Profile) {
                $health = $defender->getEeg();
                $newHealth = $health - $damage;
                if ($newHealth <= 0) {
                    // profile flatlined - remove combatants
                    if ($attacker instanceof Profile) {
                        $attackerMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You flatlined [%s] with [%s] damage</pre>'),
                            $defenderName,
                            $damage
                        );
                    }
                    $this->getWebsocketServer()->removeCombatant($defender);
                    $this->getWebsocketServer()->removeCombatant($attacker);
                    $this->flatlineProfile($defender);
                    $defenderMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">You  have been flatlined by [%s] with [%s] damage</pre>'),
                        $attackerName,
                        $damage
                    );
                }
                else {
                    if ($attacker instanceof Profile) {
                        $attackerMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You hit [%s] for [%s] damage</pre>'),
                            $defenderName,
                            $damage
                        );
                    }
                    $defenderMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">[%s] hits you for [%s] damage</pre>'),
                        $attackerName,
                        $damage
                    );
                    $defender->setEeg($newHealth);
                }
            }
            if ($defender instanceof NpcInstance) {
                $health = $defender->getCurrentEeg();
                $newHealth = $health - $damage;
                if ($newHealth <= 0) {
                    // npc instance flatlined - remove combatants
                    $attackerMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You flatlined [%s] with [%s] damage</pre>'),
                        $defenderName,
                        $damage
                    );
                    $this->getWebsocketServer()->removeCombatant($defender);
                    $this->getWebsocketServer()->removeCombatant($attacker);
                    $this->entityManager->remove($defender);
                }
                else {
                    $attackerMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You hit [%s] for [%s] damage</pre>'),
                        $defenderName,
                        $damage
                    );
                    $defender->setCurrentEeg($newHealth);
                }
            }
        }
        else {
            // missed
            if ($attacker instanceof Profile) {
                $this->learnFromFailure($attacker, ['skills' => ['blades']], -50);
                $attackerMessage = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">You miss [%s]</pre>'),
                    $defenderName
                );
            }
            if ($defender instanceof Profile) {
                $defenderMessage = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] misses you</pre>'),
                    $attackerName
                );
            }
        }
        $this->entityManager->flush();
        return [$attackerMessage, $defenderMessage];
    }

    private function flatlineProfile(Profile $profile)
    {
        $profile->setEeg(10);
        $this->entityManager->flush($profile);
        $this->movePlayerToTargetNode(NULL, NULL , $profile, $profile->getCurrentNode(), $profile->getHomeNode());
    }

}
