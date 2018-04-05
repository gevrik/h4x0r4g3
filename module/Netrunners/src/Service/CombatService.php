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
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Model\GameClientResponse;
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
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function attackCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $npc = $this->findNpcByNameOrNumberInCurrentNode($parameter);
        if (!$npc) {
            return $this->gameClientResponse->addMessage($this->translate('No such entity'))->send();
        }
        $this->getWebsocketServer()->addCombatant($profile, $npc, $resourceId);
        if (!$this->isInCombat($npc)) $this->getWebsocketServer()->addCombatant($npc, $profile, NULL, $resourceId);
        $message = sprintf(
            $this->translate('You attack [%s]'),
            $npc->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] attacks [%s]'),
            $this->user->getUsername(),
            $npc->getName()
        );
        $this->messageEveryoneInNodeNew(
            $profile->getCurrentNode(),
            $message,
            GameClientResponse::CLASS_MUTED,
            NULL,
            $profile->getId()
        );
        return $this->gameClientResponse->send();
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
    public function slayCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $targetProfile = $this->findProfileByNameOrNumberInCurrentNode($parameter);
        if (!$targetProfile) {
            return $this->gameClientResponse->addMessage($this->translate('No such user'))->send();
        }
        if ($targetProfile === $profile) {
            return $this->gameClientResponse->addMessage($this->translate('We are starting to worry about you...'))->send();
        }
        $this->getWebsocketServer()->addCombatant($profile, $targetProfile, $resourceId, $targetProfile->getCurrentResourceId());
        if (!$this->isInCombat($targetProfile)) $this->getWebsocketServer()->addCombatant($targetProfile, $profile, $targetProfile->getCurrentResourceId(), $resourceId);
        $message = sprintf(
            $this->translate('You attack [%s]'),
            $targetProfile->getUser()->getUsername()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $defenderMessage = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-attention">[%s] attacks you</pre>'),
            $this->user->getUsername()
        );
        $this->messageProfileNew($targetProfile, $defenderMessage, GameClientResponse::CLASS_ATTENTION);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] attacks [%s]'),
            $this->user->getUsername(),
            $targetProfile->getUser()->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, NULL, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param NpcInstance|Profile $attacker
     * @param NpcInstance|Profile $defender
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function resolveCombatRound($attacker, $defender)
    {
        if (!$attacker || !$defender) return [NULL, NULL, NULL, NULL];
        $ws = $this->getWebsocketServer();
        // init vars
        $skillRating = 30;
        $blade = NULL;
        $defenseRating = 0;
        $frayRating = 0;
        $damage = 1;
        $attackerMessage = NULL;
        $defenderMessage = NULL;
        $nodeMessage = NULL;
        $defenderName = ($defender instanceof NpcInstance) ? $defender->getName() : $defender->getUser()->getUsername();
        $attackerName = ($attacker instanceof NpcInstance) ? $attacker->getName() : $attacker->getUser()->getUsername();
        $flyToDefender = false;
        // modifier for profile attacker
        //var_dump($attackerName . ' attacking ' . $defenderName);
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
            $blade = $attacker->getBladeModule();
            if ($blade) {
                $skillRating += $blade->getLevel();
                $damage = ceil(round($blade->getIntegrity()/10));
            }
            else {
                $damage = $attacker->getLevel();
            }
        }
        // defense modifier for profile defender - and check if we need to add another combatant
        if ($defender instanceof Profile) {
            // defender auto-attack back if not in combat
            if (!$this->isInCombat($defender)) $ws->addCombatant(
                $defender,
                $attacker,
                $defender->getCurrentResourceId(),
                ($attacker instanceof Profile) ? $attacker->getCurrentResourceId() : NULL
            );
            $defenseRating += ($defender->getBlade()) ? $defender->getBlade()->getLevel() : 0;
            $frayRating = $this->getSkillRating($defender, Skill::ID_FRAY);
        }
        // defense modifier for npc defender - and check if we need to add another combatant
        if ($defender instanceof NpcInstance) {
            // defender auto-attack back if not in combat
            if (!$this->isInCombat($defender)) $ws->addCombatant(
                $defender,
                $attacker,
                NULL,
                ($attacker instanceof Profile) ? $attacker->getCurrentResourceId() : NULL
            );
            $defenseRating = $this->getSkillRating($defender, Skill::ID_BLADES);
            $frayRating = $this->getSkillRating($defender, Skill::ID_FRAY);
        }
        // start rolling the dice
        $roll = mt_rand(1, 100);
        if ($roll <= ($skillRating - $defenseRating)) {

            /* hit */
            // attacker is profile
            if ($attacker instanceof Profile) {
                $this->learnFromSuccess($attacker, ['skills' => ['blades']], -50);
            }
            // defender is profile
            if ($defender instanceof Profile) {
                if ($this->makePercentRollAgainstTarget($frayRating)) {
                    if ($attacker instanceof Profile) {
                        $attackerMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">[%s] evades your attack</pre>'),
                            $defenderName
                        );
                    }
                    $this->learnFromSuccess($defender, ['skills' => ['fray']], -50);
                    $defenderMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You evade the attack by [%s]</pre>'),
                        $attackerName
                    );
                    $nodeMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] evaded the attack from [%s]</pre>'),
                        $defenderName,
                        $attackerName
                    );
                }
                else {
                    // defender unable to evade, damage - first check for shield
                    $health = $defender->getEeg();
                    if ($defenderShield = $defender->getShield()) {
                        $defShieldLevel = $defenderShield->getLevel();
                        $defShieldInt = $defenderShield->getIntegrity();
                        $mitigatedDamage = ceil(round(($damage / 100) * $defShieldLevel));
                        $mitigatedDamage = $this->checkValueMinMax($mitigatedDamage, 1, $defShieldInt);
                        $damage = $damage - $mitigatedDamage;
                        $this->lowerIntegrityOfFile($defenderShield, 100, $mitigatedDamage);
                    }
                    // now check for armor
                    $hitLocation = $this->determineHitLocation();
                    $damage = $this->processArmorOnHit($defender, $hitLocation, $damage);
                    // all checks complete, apply remaining damage
                    $newHealth = $health - $damage;
                    if ($newHealth <= 0) {
                        // profile flatlined - remove combatants
                        if ($attacker instanceof Profile) {
                            $attackerMessage = sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You flatlined [%s] with [%s] damage in the [%s]</pre>'),
                                $defenderName,
                                $damage,
                                $hitLocation
                            );
                        }
                        $ws->removeCombatant($defender);
                        $this->flatlineProfile($defender);
                        $defenderMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">You have been flatlined by [%s] with [%s] damage in the [%s]</pre>'),
                            $attackerName,
                            $damage,
                            $hitLocation
                        );
                        $flyToDefender = true;
                        $nodeMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] flatlined [%s] with a hit in the [%s]</pre>'),
                            $attackerName,
                            $defenderName,
                            $hitLocation
                        );
                    }
                    else {
                        if ($attacker instanceof Profile) {
                            $attackerMessage = sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You hit [%s] for [%s] damage in the [%s]</pre>'),
                                $defenderName,
                                $damage,
                                $hitLocation
                            );
                        }
                        $defenderMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">[%s] hits you for [%s] damage in the [%s]</pre>'),
                            $attackerName,
                            $damage,
                            $hitLocation
                        );
                        $nodeMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] hits [%s] in the [%s]</pre>'),
                            $attackerName,
                            $defenderName,
                            $hitLocation
                        );
                        $defender->setEeg($newHealth);
                    }
                }
            }
            // defender is npc
            if ($defender instanceof NpcInstance) {
                if ($this->makePercentRollAgainstTarget($frayRating)) {
                    if ($attacker instanceof Profile) {
                        $attackerMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">[%s] evades your attack</pre>'),
                            $defenderName
                        );
                    }
                    $nodeMessage = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] evaded the attack from [%s]</pre>'),
                        $defenderName,
                        $attackerName
                    );
                }
                else {
                    $hitLocation = $this->determineHitLocation();
                    $health = $defender->getCurrentEeg();
                    $newHealth = $health - $damage;
                    if ($newHealth <= 0) {
                        // npc instance flatlined - remove combatants
                        if ($attacker instanceof Profile) {
                            $attackerMessage = sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You flatlined [%s] with [%s] damage in the [%s]</pre>'),
                                $defenderName,
                                $damage,
                                $hitLocation
                            );
                        }
                        $ws->removeCombatant($defender);
                        $this->flatlineNpcInstance($defender, $attacker);
                        $nodeMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] flatlines [%s] with a hit in the [%s]</pre>'),
                            $attackerName,
                            $defenderName,
                            $hitLocation
                        );
                    }
                    else {
                        if ($attacker instanceof Profile) {
                            $attackerMessage = sprintf(
                                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You hit [%s] for [%s] damage in the [%s]</pre>'),
                                $defenderName,
                                $damage,
                                $hitLocation
                            );
                        }
                        $defender->setCurrentEeg($newHealth);
                        $nodeMessage = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] hits [%s] in the [%s]</pre>'),
                            $attackerName,
                            $defenderName,
                            $hitLocation
                        );
                    }
                }
            }
        }
        else {
            /* missed */
            // attacker is profile
            if ($attacker instanceof Profile) {
                $this->learnFromFailure($attacker, ['skills' => ['blades']], -50);
                $attackerMessage = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">You miss [%s]</pre>'),
                    $defenderName
                );
            }
            // defender is profile
            if ($defender instanceof Profile) {
                $defenderMessage = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] misses you</pre>'),
                    $attackerName
                );
            }
            // message for other players
            $nodeMessage = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] misses [%s]</pre>'),
                $attackerName,
                $defenderName
            );
        }
        return [$attackerMessage, $defenderMessage, $flyToDefender, $nodeMessage];
    }

    /**
     * @return string
     */
    private function determineHitLocation()
    {
        $roll = mt_rand(1, 10);
        switch ($roll) {
            default:
                return 'torso';
            case 1:
                return 'head';
            case 2:
                return 'shoulders';
            case 3:
                return 'upper arms';
            case 4:
                return 'lower arms';
            case 5:
                return 'hands';
            case 6:
                return 'legs';
            case 7:
                return 'feet';
        }
    }

    /**
     * @param Profile $defender
     * @param string $hitLocation
     * @param int $damage
     * @return int
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function processArmorOnHit(Profile $defender, $hitLocation, $damage)
    {
        switch ($hitLocation) {
            default:
                $armor = $defender->getTorsoArmor();
                break;
            case 'head':
                $armor = $defender->getHeadArmor();
                break;
            case 'shoulders':
                $armor = $defender->getShoulderArmor();
                break;
            case 'upper arms':
                $armor = $defender->getUpperArmArmor();
                break;
            case 'lower arms':
                $armor = $defender->getLowerArmArmor();
                break;
            case 'hands':
                $armor = $defender->getHandArmor();
                break;
            case 'legs':
                $armor = $defender->getLegArmor();
                break;
            case 'feet':
                $armor = $defender->getShoesArmor();
                break;
        }
        if ($armor) {
            $armorLevel = $armor->getLevel();
            $armorIntegrity = $armor->getIntegrity();
            $mitigatedDamage = ceil(round(($damage / 100) * $armorLevel));
            $mitigatedDamage = $this->checkValueMinMax($mitigatedDamage, 1, $armorIntegrity);
            $damage = $damage - $mitigatedDamage;
            $this->lowerIntegrityOfFile($armor, 100, $mitigatedDamage);
        }
        return $damage;
    }

}
