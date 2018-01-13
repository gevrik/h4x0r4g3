<?php

/**
 * FileExecution Service.
 * The service supplies methods that resolve execution logic around File objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Effect;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class FileExecutionService extends BaseService
{

    /**
     * @var CodebreakerService
     */
    protected $codebreakerService;

    /**
     * @var MissionService
     */
    protected $missionService;

    /**
     * @var HangmanService
     */
    protected $hangmanService;

    /**
     * @var FileRepository
     */
    protected $fileRepo;


    /**
     * FileExecutionService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param CodebreakerService $codebreakerService
     * @param MissionService $missionService
     * @param HangmanService $hangmanService
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        CodebreakerService $codebreakerService,
        MissionService $missionService,
        HangmanService $hangmanService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->codebreakerService = $codebreakerService;
        $this->missionService = $missionService;
        $this->hangmanService = $hangmanService;
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function executeFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        list($contentArray, $parameter) = $this->getNextParameter($contentArray);
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName(
            $profile->getCurrentNode(),
            $profile,
            $parameter
        );
        $file = NULL;
        if (count($targetFiles) >= 1) {
            $file = array_shift($targetFiles);
        }
        if (!$file) {
            return $this->gameClientResponse->addMessage($this->translate('No such file'))->send();
        }
        /** @var File $file */
        $isBlocked = $this->isActionBlockedNew($resourceId, false, $file);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if file belongs to user TODO should be able to bypass this via bh program
        if ($file->getProfile() != $profile) {
            $message = sprintf(
                $this->translate('You are not allowed to execute %s'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if already running
        if ($file->getRunning()) {
            $message = sprintf(
                $this->translate('%s is already running'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if file has enough integrity
        if ($file->getIntegrity() < 1) {
            $message = sprintf(
                $this->translate('%s has no integrity'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if there is enough memory to execute this
        if (!$this->canExecuteFile($profile, $file)) {
            $message = sprintf(
                $this->translate('You do not have enough memory to execute %s - build more memory nodes'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // determine what to do depending on file type
        switch ($file->getFileType()->getId()) {
            default:
                $message = sprintf(
                    $this->translate('%s is not executable, yet'),
                    $file->getName()
                );
                return $this->gameClientResponse->addMessage($message)->send();
            case FileType::ID_CHATCLIENT:
                return $this->executeChatClient($file);
            case FileType::ID_KICKER:
            case FileType::ID_BREAKOUT:
            case FileType::ID_SMOKESCREEN:
            case FileType::ID_VENOM:
            case FileType::ID_ANTIDOTE:
            case FileType::ID_STIMULANT:
                return $this->executeCombatProgram($file);
            case FileType::ID_DATAMINER:
                return $this->executeDataminer($file, $profile->getCurrentNode());
            case FileType::ID_COINMINER:
                return $this->executeCoinminer($file, $profile->getCurrentNode());
            case FileType::ID_GUARD_SPAWNER:
                return $this->executeGuardSpawner($file, $profile->getCurrentNode());
            case FileType::ID_ICMP_BLOCKER:
                return $this->executeIcmpBlocker($file, $profile->getCurrentNode());
            case FileType::ID_BEARTRAP:
                return $this->executeBeartrap($file, $profile->getCurrentNode());
            case FileType::ID_PORTSCANNER:
            case FileType::ID_JACKHAMMER:
            case FileType::ID_SIPHON:
            case FileType::ID_MEDKIT:
            case FileType::ID_PROXIFIER:
                return $this->queueProgramExecution($resourceId, $file, $profile->getCurrentNode(), $contentArray);
                break;
            case FileType::ID_CODEBREAKER:
                return $this->codebreakerService->startCodebreaker($resourceId, $file, $contentArray);
            case FileType::ID_CUSTOM_IDE:
                return $this->executeCustomIde($file, $profile->getCurrentNode());
            case FileType::ID_SKIMMER:
                return $this->executeSkimmer($file, $profile->getCurrentNode());
            case FileType::ID_BLOCKCHAINER:
                return $this->executeBlockchainer($file, $profile->getCurrentNode());
            case FileType::ID_IO_TRACER:
                return $this->executeIoTracer($file, $profile->getCurrentNode());
            case FileType::ID_OBFUSCATOR:
                return $this->executeObfuscator($file);
            case FileType::ID_CLOAK:
                return $this->executeCloak($file);
            case FileType::ID_LOG_ENCRYPTOR:
                return $this->executeLogEncryptor($file, $profile->getCurrentNode());
            case FileType::ID_LOG_DECRYPTOR:
                return $this->executeLogDecryptor($file, $profile->getCurrentNode());
            case FileType::ID_PHISHER:
                return $this->executePhisher($file, $profile->getCurrentNode());
            case FileType::ID_WILDERSPACE_HUB_PORTAL:
                return $this->executeWilderspaceHubPortal($file, $profile->getCurrentNode());
            case FileType::ID_RESEARCHER:
                return $this->executeResearcher($file, $profile->getCurrentNode());
            case FileType::ID_CODEBLADE:
            case FileType::ID_CODEBLASTER:
            case FileType::ID_CODESHIELD:
            case FileType::ID_CODEARMOR:
                return $this->equipFile($file);
            case FileType::ID_TEXT:
                return $this->executeMissionFile($file);
            case FileType::ID_WORMER:
                return $this->hangmanService->startHangmanGame($resourceId, $file, $contentArray);
        }
    }

    /**
     * @param File $file
     * @return GameClientResponse
     */
    protected function executeChatClient(File $file)
    {
        $file->setRunning(true);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }


    protected function executeCombatProgram(File $file)
    {
        $profile = $this->user->getProfile();
        if (!$this->isInCombat($profile)) { // TODO some of these should be executable outside of combat - like breakout
            $message = $this->translate('You are not in combat');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $now = new \DateTime();
        if ($this->clientData->combatFileCooldown > $now) {
            $message = $this->translate('You have to wait before executing another combat program');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        switch ($file->getFileType()->getId()) {
            default:
                break;
            case FileType::ID_KICKER:
                $this->executeKicker($file);
                break;
            case FileType::ID_BREAKOUT:
                $this->executeBreakout($file);
                break;
            case FileType::ID_SMOKESCREEN:
                $this->executeSmokeScreen($file);
                break;
            case FileType::ID_VENOM:
                $this->executeVenom($file);
                break;
            case FileType::ID_ANTIDOTE:
                $this->executeAntidote($file);
                break;
            case FileType::ID_PUNCHER:
                $message = $this->translate('You try to punch your opponent');
                $this->gameClientResponse->addMessage($message);
                break;
            case FileType::ID_STIMULANT:
                $message = $this->translate('You try to heal yourself');
                $this->gameClientResponse->addMessage($message);
                break;
        }
        // set global combat cooldown
        $now->add(new \DateInterval('PT2S'));
        $this->getWebsocketServer()->setClientCombatFileCooldown($profile->getCurrentResourceId(), $now);
        return $this->gameClientResponse->send();
    }

    /**
     * @param File $file
     */
    public function executeSmokeScreen(File $file)
    {
        $profile = $file->getProfile();
        $rating = $this->getBonusForFileLevel($file);
        if (mt_rand(1, 100) <= $rating) {
            $this->getWebsocketServer()->removeCombatant($profile);
            $profile->setStealthing(true);
            $this->entityManager->flush($profile);
            // TODO add a temporary stealth boost effect
            $message = sprintf(
                $this->translate('You execute [%s] and disengage from combat'),
                $file->getName()
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            $responseOther = sprintf(
                $this->translate('[%s] executes [%s] and disengages from combat'),
                $file->getName()
            );
            $this->messageEveryoneInNodeNew($file->getNode(), $responseOther, GameClientResponse::CLASS_WARNING, $profile, $profile->getId(), true);
        }
        else {
            $message = sprintf(
                $this->translate('You execute [%s] but fail to cause any effect'),
                $file->getName()
            );
            $this->gameClientResponse->addMessage($message);
            $responseOther = sprintf(
                $this->translate('[%s] executes [%s] but fails to cause any effect'),
                $file->getName()
            );
            $this->messageEveryoneInNodeNew($file->getNode(), $responseOther, GameClientResponse::CLASS_WARNING, NULL, $profile->getId());
        }
        $this->lowerIntegrityOfFile($file, 100, 1, true);
    }

    /**
     * @param File $file
     * @return bool|GameClientResponse
     */
    public function executeBreakout(File $file)
    {
        $now = new \DateTime();
        $profile = $file->getProfile();
        $rating = $this->getBonusForFileLevel($file);
        if (!$this->isUnderEffect($profile, Effect::ID_STUNNED)) {
            return $this->gameClientResponse->addMessage($this->translate('You are not stunned'));
        }
        $effectInstance = $this->getEffectInstance($profile, Effect::ID_STUNNED);
        if ($effectInstance->getExpires() < $now) {
            return $this->gameClientResponse->addMessage($this->translate('You are not stunned'));
        }
        if ($effectInstance) {
            $this->lowerIntegrityOfFile($file, 100, 1, true);
            if (mt_rand(1, 100) <= $rating) {
                $effectInstance->setExpires($now);
                $this->entityManager->flush($effectInstance);
                $message = sprintf(
                    $this->translate('You execute [%s] and shake-off the stun'),
                    $file->getName()
                );
                $responseOther = sprintf(
                    $this->translate('[%s] executes [%s] and shakes-off the stun'),
                    $file->getName()
                );
                $this->messageEveryoneInNodeNew($file->getNode(), $responseOther, GameClientResponse::CLASS_MUTED, NULL, $profile->getId());
                return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            }
            else {
                $message = sprintf(
                    $this->translate('You execute [%s] but fail to break the stun'),
                    $file->getName()
                );
                $responseOther = sprintf(
                    $this->translate('[%s] executes [%s] but fails to break the stun'),
                    $file->getName()
                );
                $this->messageEveryoneInNodeNew($file->getNode(), $responseOther, GameClientResponse::CLASS_MUTED, NULL, $profile->getId());
                return $this->gameClientResponse->addMessage($message);
            }
        }
        return true;
    }

    /**
     * @param File $file
     * @return bool|GameClientResponse
     */
    public function executeAntidote(File $file)
    {
        $now = new \DateTime();
        $profile = $file->getProfile();
        $rating = $this->getBonusForFileLevel($file);
        if (!$this->isUnderEffect($profile, Effect::ID_DAMAGE_OVER_TIME)) {
            $message = $this->translate('You are not affected by any damage-over-time effects');
            return $this->gameClientResponse->addMessage($message);
        }
        $effectInstance = NULL;
        $effectInstance = $this->getEffectInstance($profile, Effect::ID_STUNNED);
        if ($effectInstance->getExpires() < $now) {
            $message = $this->translate('You are not affected by any damage-over-time effects');
            return $this->gameClientResponse->addMessage($message);
        }
        if ($effectInstance) {
            $this->lowerIntegrityOfFile($file, 100, 1, true);
            if (mt_rand(1, 100) <= $rating) {
                $effectInstance->setExpires($now);
                $this->entityManager->flush($effectInstance);
                $message = sprintf(
                    $this->translate('You execute [%s] and shake-off the damage-over-time effect'),
                    $file->getName()
                );
                $responseOther = sprintf(
                    $this->translate('[%s] executes [%s] and shakes-off the damage-over-time effect'),
                    $file->getName()
                );
                $this->messageEveryoneInNodeNew($file->getNode(), $responseOther, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
                return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            }
            else {
                $message = sprintf(
                    $this->translate('You fail to execute [%s]'),
                    $file->getName()
                );
                $responseOther = sprintf(
                    $this->translate('[%s] fails to execute [%s]'),
                    $file->getName()
                );
                $this->messageEveryoneInNodeNew($file->getNode(), $responseOther, GameClientResponse::CLASS_MUTED, NULL, $profile->getId());
                return $this->gameClientResponse->addMessage($message);
            }
        }
        return true;
    }

    /**
     * @param File $file
     */
    protected function executeKicker(File $file)
    {
        $profile = $file->getProfile();
        $ws = $this->getWebsocketServer();
        $combatData = $ws->getProfileCombatData($profile->getId());
        if (!$combatData) {
            $message = sprintf(
                $this->translate('You are no longer in combat - unable to execute [%s]'),
                $file->getName()
            );
            $this->gameClientResponse->addMessage($message);
        }
        else {
            $target = (isset($combatData->profileTarget)) ? $this->entityManager->find('Netrunners\Entity\Profile', $combatData->profileTarget) : $this->entityManager->find('Netrunners\Entity\NpcInstance', $combatData->npcTarget);
            if (!$target) {
                $message = sprintf(
                    $this->translate('Your target is no longer valid - unable to execute [%s]'),
                    $file->getName()
                );
                $this->gameClientResponse->addMessage($message);
            }
            else {
                if (mt_rand(1, 100) <= $this->getBonusForFileLevel($file)) {
                    if ($target instanceof Profile) {
                        list($actorMessage, $targetMessage) = $this->addEffect($target, NULL, $profile, Effect::ID_STUNNED);
                    }
                    else {
                        list($actorMessage, $targetMessage) = $this->addEffect(NULL, $target, $profile, Effect::ID_STUNNED);
                    }
                    $this->gameClientResponse->addMessage($actorMessage);
                    if ($targetMessage) $this->messageProfileNew($target, $targetMessage);
                }
                else {
                    $message = sprintf(
                        $this->translate('[%s] has no effect'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message);
                }
                $this->lowerIntegrityOfFile($file, 100, 1, true);
            }
        }
    }

    /**
     * @param File $file
     */
    protected function executeVenom(File $file)
    {
        $profile = $file->getProfile();
        $ws = $this->getWebsocketServer();
        $combatData = $ws->getProfileCombatData($profile->getId());
        if (!$combatData) {
            $message = sprintf(
                $this->translate('You are no longer in combat - unable to execute [%s]'),
                $file->getName()
            );
            $this->gameClientResponse->addMessage($message);
        }
        else {
            $target = (isset($combatData->profileTarget)) ? $this->entityManager->find('Netrunners\Entity\Profile', $combatData->profileTarget) : $this->entityManager->find('Netrunners\Entity\NpcInstance', $combatData->npcTarget);
            if (!$target) {
                $message = sprintf(
                    $this->translate('Your target is no longer valid - unable to execute [%s]'),
                    $file->getName()
                );
                $this->gameClientResponse->addMessage($message);

            }
            else {
                if (mt_rand(1, 100) <= $this->getBonusForFileLevel($file)) {
                    if ($target instanceof Profile) {
                        list($actorMessage, $targetMessage) = $this->addEffect($target, NULL, $profile, Effect::ID_DAMAGE_OVER_TIME);
                    }
                    else {
                        list($actorMessage, $targetMessage) = $this->addEffect(NULL, $target, $profile, Effect::ID_DAMAGE_OVER_TIME);
                    }
                    $this->gameClientResponse->addMessage($actorMessage);
                    if ($targetMessage) $this->messageProfileNew($target, $targetMessage);
                }
                else {
                    $message = sprintf(
                        $this->translate('[%s] has no effect'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message);
                }
            }
        }
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeDataminer(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a database node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeCoinminer(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a terminal node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeGuardSpawner(File $file, Node $node)
    {
        $profile = $this->user->getProfile();
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can not be used in this type of node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($profile !== $profile->getCurrentNode()->getSystem()->getProfile()) {
            $message = sprintf(
                $this->translate('Permission denied'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $addonText = '';
        if (ceil(round($file->getLevel()/10)) > $node->getLevel()) {
            $addonText = $this->translate('<span class="text-attention">[NOTICE: SPAWNED ENTITY-LEVEL RESTRICTED BY NODE-LEVEL]</span>');
        }
        $message = sprintf(
            $this->translate('%s has been started as process %s %s'),
            $file->getName(),
            $file->getId(),
            $addonText
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeIcmpBlocker(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in I/O nodes'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeBeartrap(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a firewall node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param Node $node
     * @param $contentArray
     * @return array|bool|mixed
     */
    private function queueProgramExecution($resourceId, File $file, Node $node, $contentArray)
    {
        $executeWarning = false;
        $parameterArray = [];
        $message = '';
        switch ($file->getFileType()->getId()) {
            default:
                break;
            case FileType::ID_PORTSCANNER:
                list($executeWarning, $systemId) = $this->executeWarningPortscanner($file, $node, $contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'systemId' => $systemId,
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('You start portscanning with [%s] - please wait'),
                    $file->getName()
                );
                break;
            case FileType::ID_JACKHAMMER:
                list($executeWarning, $systemId, $nodeId) = $this->executeWarningJackhammer($file, $node, $contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'systemId' => $systemId,
                    'nodeId' => $nodeId,
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('You start breaking into the system with [%s] - please wait'),
                    $file->getName()
                );
                break;
            case FileType::ID_SIPHON:
                list($executeWarning, $minerId) = $this->executeWarningSiphon($contentArray);
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'minerId' => $minerId,
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('You start siphoning into the miner program with [%s] - please wait'),
                    $file->getName()
                );
                break;
            case FileType::ID_MEDKIT:
                $executeWarning = $this->executeWarningMedkit();
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('You start using [%s] on yourself'),
                    $file->getName()
                );
                break;
            case FileType::ID_PROXIFIER:
                $executeWarning = $this->executeWarningProxifier();
                $parameterArray = [
                    'fileId' => $file->getId(),
                    'contentArray' => $contentArray
                ];
                $message = sprintf(
                    $this->translate('You start using [%s] - please wait'),
                    $file->getName()
                );
                break;
        }
        if ($executeWarning) {
            $this->gameClientResponse->addMessage($executeWarning);
        }
        else {
            $fileType = $file->getFileType();
            $completionDate = new \DateTime();
            $completionDate->add(new \DateInterval('PT' . $fileType->getExecutionTime() . 'S'));
            $actionData = [
                'command' => 'executeprogram',
                'completion' => $completionDate,
                'blocking' => $fileType->getBlocking(),
                'fullblock' => $fileType->getFullblock(),
                'parameter' => $parameterArray
            ];
            $this->getWebsocketServer()->setClientActionData($resourceId, $actionData);
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            $this->gameClientResponse->addOption(GameClientResponse::OPT_TIMER, $fileType->getExecutionTime());
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @param $contentArray
     * @return array
     */
    public function executeWarningPortscanner(File $file, Node $node, $contentArray)
    {
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = sprintf(
                $this->translate('[%s] can only be used in an I/O node'),
                $file->getName()
            );
        }
        $addy = $this->getNextParameter($contentArray, false);
        if (!$response && !$addy) {
            $response = $this->translate('Please specify a system address to scan');
        }
        $systemId = false;
        $system = false;
        if (!$response) {
            $system = $systemRepo->findOneBy([
                'addy' => $addy
            ]);
            if (!$system) {
                $response = $this->translate('Invalid system address');
            }
            else {
                $systemId = $system->getId();
            }
        }
        /** @var System $system */
        $profile = $file->getProfile();
        /** @var Profile $profile */
        if (!$response && $system->getProfile() === $profile) {
            $response = $this->translate('Invalid system - unable to scan own systems');
        }
        return [$response, $systemId];
    }

    /**
     * @param File $file
     * @param Node $node
     * @param $contentArray
     * @return array
     */
    public function executeWarningJackhammer(File $file, Node $node, $contentArray)
    {
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $response = false;
        if (!$this->canExecuteInNodeType($file, $node)) {
            $response = sprintf(
                $this->translate('%s can only be used in an I/O node'),
                $file->getName()
            );
        }
        list($contentArray, $addy) = $this->getNextParameter($contentArray, true);
        if (!$response && !$addy) {
            $response = $this->translate('Please specify a system address to break in to');
        }
        $systemId = false;
        $system = false;
        if (!$response) {
            $system = $systemRepo->findOneBy([
                'addy' => $addy
            ]);
        }
        if (!$response) {
            if (!$system) {
                $response = $this->translate('Invalid system address');
            }
            else {
                $systemId = $system->getId();
            }
        }
        /** @var System $system */
        $profile = $file->getProfile();
        /** @var Profile $profile */
        if (!$response && $system->getProfile() === $profile) {
            $response = $this->translate('Invalid system - unable to break in to your own systems');
        }
        // now check if a node id was given
        $nodeId = $this->getNextParameter($contentArray, false, true);
        if (!$response && !$nodeId) {
            $response = $this->translate('Please specify a node ID to break in to');
        }
        if (!$response) {
            $node = $this->entityManager->find('Netrunners\Entity\Node', $nodeId);
            /** @var Node $node */
            if (!$this->getNodeAttackDifficulty($node, $file)) {
                $response = $this->translate('Invalid node ID');
            }
        }
        return [$response, $systemId, $nodeId];
    }

    /**
     * @param $contentArray
     * @return array
     */
    public function executeWarningSiphon($contentArray)
    {
        $response = false;
        $profile = $this->user->getProfile();
        $minerString = $this->getNextParameter($contentArray, false);
        if (!$minerString) {
            $response = $this->translate('Please specify the miner that you want to siphon from');
        }
        $minerId = NULL;
        if (!$response) {
            // try to get target file via repo method
            $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $minerString);
            if (!$response && count($targetFiles) < 1) {
                $response = $this->translate('No such file');
            }
            if (!$response) {
                $miner = array_shift($targetFiles);
                /** @var File $miner */
                switch ($miner->getFileType()->getId()) {
                    default:
                        $response = $this->translate('Invalid file type to siphon from');
                        break;
                    case FileType::ID_COINMINER:
                    case FileType::ID_DATAMINER:
                        $minerId = $miner->getId();
                        break;
                }
            }
        }
        return [$response, $minerId];
    }

    /**
     * @return bool|string
     */
    public function executeWarningMedkit()
    {
        $response = false;
        $profile = $this->user->getProfile();
        if ($profile->getEeg() > 99) {
            $response = $this->translate('You are already at maximum EEG');
        }
        return $response;
    }

    /**
     * @return bool|string
     */
    public function executeWarningProxifier()
    {
        $response = false;
        $profile = $this->user->getProfile();
        if ($profile->getSecurityRating() < 1) {
            $response = $this->translate('Your security rating is already at 0');
        }
        return $response;
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeCustomIde(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a coding node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeSkimmer(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a banking node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeBlockchainer(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a banking node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeIoTracer(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in I/O nodes'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @return GameClientResponse
     */
    protected function executeObfuscator(File $file)
    {
        $file->setRunning(true);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @return GameClientResponse
     */
    protected function executeCloak(File $file)
    {
        $file->setRunning(true);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeLogEncryptor(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a monitoring node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeLogDecryptor(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a monitoring node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executePhisher(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in an intrustion node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeWilderspaceHubPortal(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in an intrusion node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @param Node $node
     * @return GameClientResponse
     */
    protected function executeResearcher(File $file, Node $node)
    {
        // check if they can execute it in this node
        if (!$this->canExecuteInNodeType($file, $node)) {
            $message = sprintf(
                $this->translate('%s can only be used in a memory node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $file->setRunning(true);
        $file->setSystem($node->getSystem());
        $file->setNode($node);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('%s has been started as process %s'),
            $file->getName(),
            $file->getId()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param File $file
     * @return GameClientResponse
     */
    private function equipFile(File $file)
    {
        $profile = $file->getProfile();
        switch ($file->getFileType()->getId()) {
            default:
                break;
            case FileType::ID_CODEBLADE:
                $currentBlade = $profile->getBlade();
                if ($currentBlade) {
                    $message = sprintf(
                        $this->translate('You remove the [%s]'),
                        $currentBlade->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                    $currentBlade->setRunning(false);
                    $profile->setBlade(NULL);
                    $this->entityManager->flush($currentBlade);
                }
                if (!$this->canExecuteFile($profile, $file)) {
                    $message = sprintf(
                        $this->translate('You do not have enough memory to execute [%s]'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message);
                }
                else {
                    $profile->setBlade($file);
                    $file->setRunning(true);
                    $this->entityManager->flush($file);
                    $message = sprintf(
                        $this->translate('You now use [%s] as your blade module'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                }
                $this->entityManager->flush($profile);
                break;
            case FileType::ID_CODEBLASTER:
                $currentBlaster = $profile->getBlaster();
                if ($currentBlaster) {
                    $message = sprintf(
                        $this->translate('You remove the [%s]'),
                        $currentBlaster->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                    $currentBlaster->setRunning(false);
                    $profile->setBlaster(NULL);
                    $this->entityManager->flush($currentBlaster);
                }
                if (!$this->canExecuteFile($profile, $file)) {
                    $message = sprintf(
                        $this->translate('You do not have enough memory to execute [%s]'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message);
                }
                else {
                    $profile->setBlaster($file);
                    $file->setRunning(true);
                    $this->entityManager->flush($file);
                    $message = sprintf(
                        $this->translate('You now use [%s] as your blaster module'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                }
                $this->entityManager->flush($profile);
                break;
            case FileType::ID_CODESHIELD:
                $currentShield = $profile->getBlaster();
                if ($currentShield) {
                    $message = sprintf(
                        $this->translate('You remove the [%s]'),
                        $currentShield->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                    $currentShield->setRunning(false);
                    $profile->setShield(NULL);
                    $this->entityManager->flush($currentShield);
                }
                if (!$this->canExecuteFile($profile, $file)) {
                    $message = sprintf(
                        $this->translate('You do not have enough memory to execute [%s]'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message);
                }
                else {
                    $profile->setShield($file);
                    $file->setRunning(true);
                    $this->entityManager->flush($file);
                    $message = sprintf(
                        $this->translate('You now use [%s] as your shield module'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                }
                $this->entityManager->flush($profile);
                break;
            case FileType::ID_CODEARMOR:
                $fileData = json_decode($file->getData());
                if (!$fileData) {
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] has not been initialized yet</pre>'),
                        $file->getName()
                    );
                    $this->gameClientResponse->addMessage($message);
                }
                else {
                    switch ($fileData->subtype) {
                        default:
                            break;
                        case FileType::SUBTYPE_ARMOR_HEAD:
                            $currentArmor = $profile->getHeadArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setHeadArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setHeadArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your head armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_SHOULDERS:
                            $currentArmor = $profile->getShoulderArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setShoulderArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setShoulderArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your shoulder armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_UPPER_ARM:
                            $currentArmor = $profile->getUpperArmArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setUpperArmArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setUpperArmArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your upper-arm armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_LOWER_ARM:
                            $currentArmor = $profile->getLowerArmArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setLowerArmArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setLowerArmArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your lower-arm armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_HANDS:
                            $currentArmor = $profile->getHandArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setHandArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setHandArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your hands armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_TORSO:
                            $currentArmor = $profile->getTorsoArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setTorsoArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setTorsoArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your torso armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_LEGS:
                            $currentArmor = $profile->getLegArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setLegArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setLegArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your leg armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                        case FileType::SUBTYPE_ARMOR_SHOES:
                            $currentArmor = $profile->getShoesArmor();
                            if ($currentArmor) {
                                $message = sprintf(
                                    $this->translate('You remove the [%s]'),
                                    $currentArmor->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                                $currentArmor->setRunning(false);
                                $profile->setShoesArmor(NULL);
                                $this->entityManager->flush($currentArmor);
                            }
                            if (!$this->canExecuteFile($profile, $file)) {
                                $message = sprintf(
                                    $this->translate('You do not have enough memory to execute [%s]'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message);
                            }
                            else {
                                $profile->setShoesArmor($file);
                                $file->setRunning(true);
                                $this->entityManager->flush($file);
                                $message = sprintf(
                                    $this->translate('You now use [%s] as your shoes armor module'),
                                    $file->getName()
                                );
                                $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                            }
                            $this->entityManager->flush($profile);
                            break;
                    }
                }
                break;
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param File $file
     * @param System $system
     * @return GameClientResponse
     */
    public function executePortscanner(File $file, System $system)
    {
        $response = new GameClientResponse($file->getProfile()->getCurrentResourceId());
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $fileLevel = $file->getLevel();
        $fileIntegrity = $file->getIntegrity();
        $skillRating = $this->getSkillRating($file->getProfile(), Skill::ID_COMPUTING);
        $baseChance = ($fileLevel + $fileIntegrity + $skillRating) / 2;
        $nodes = $nodeRepo->findBySystem($system);
        $messages = [];
        foreach ($nodes as $node) {
            /** @var Node $node */
            $difficulty = $this->getNodeAttackDifficulty($node, $file);
            if ($difficulty) {
                $roll = mt_rand(1, 100);
                if ($roll <= $baseChance - $difficulty) {
                    $messages[] = sprintf(
                        '%-45s|%-11s|%-20s|%s',
                        $system->getAddy(),
                        $node->getId(),
                        $node->getNodeType()->getName(),
                        $node->getName()
                    );
                }
            }
        }
        $headerMessage = sprintf(
            $this->translate('PORTSCANNER RESULTS FOR : %s'),
            $system->getAddy()
        );
        $response->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        if (empty($messages)) {
            $messages[] = $this->translate('No vulnerable nodes detected');
        }
        else {
            array_unshift($messages, sprintf(
                '<span class="text-sysmsg">%-45s|%-11s|%-20s|%s</span>',
                $this->translate('SYSTEM-ADDRESS'),
                $this->translate('NODE-ID'),
                $this->translate('NODE-TYPE'),
                $this->translate('NODE-NAME')
            ));
        }
        $response->addMessages($messages);
        $this->lowerIntegrityOfFile($file);
        return $response->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param System $system
     * @param Node $node
     * @return GameClientResponse
     */
    public function executeJackhammer($resourceId, File $file, System $system, Node $node)
    {
        $response = new GameClientResponse($resourceId);
        $fileLevel = $file->getLevel();
        $fileIntegrity = $file->getIntegrity();
        $skillRating = $this->getSkillRating($file->getProfile(), Skill::ID_COMPUTING);
        $baseChance = ($fileLevel + $fileIntegrity + $skillRating) / 2;
        $difficulty = $this->getNodeAttackDifficulty($node, $file);
        if (!$difficulty) $difficulty = 0;
        $roll = mt_rand(1, 100);
        if ($roll <= $baseChance - $difficulty) {
            $message = sprintf(
                $this->translate('JACKHAMMER RESULTS FOR %s:%s'),
                $system->getAddy(),
                $node->getId()
            );
            $response->addMessage($message, GameClientResponse::CLASS_SYSMSG);
            $message = sprintf(
                $this->translate('You break in to the target system\'s node')
            );
            $response->addMessage($message, GameClientResponse::CLASS_SUCCESS);
            $profile = $file->getProfile();
            /** @var Profile $profile */
            $this->movePlayerToTargetNodeNew($resourceId, $profile, NULL, $file->getProfile()->getCurrentNode(), $node);
            $this->updateMap($resourceId, $profile);
            $flytoResponse = new GameClientResponse($resourceId);
            $flytoResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
            $flytoResponse->addOption(GameClientResponse::OPT_CONTENT, explode(',', $system->getGeocoords()));
            $flytoResponse->send();
            $nodeInfoResponse = $this->showNodeInfoNew($resourceId, NULL, false);
            if ($nodeInfoResponse instanceof GameClientResponse) return $nodeInfoResponse;
        }
        else {
            $message = sprintf(
                $this->translate('JACKHAMMER RESULTS FOR %s:%s'),
                $system->getAddy(),
                $node->getId()
            );
            $response->addMessage($message, GameClientResponse::CLASS_SYSMSG);
            $message = $this->translate('You fail to break in to the target system');
            $response->addMessage($message);
        }
        $this->lowerIntegrityOfFile($file);
        return $response->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
    }

    /**
     * @param File $file
     * @param File $miner
     * @return GameClientResponse
     */
    public function executeSiphon(File $file, File $miner)
    {
        $response = new GameClientResponse($file->getProfile()->getCurrentResourceId());
        $response->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $minerData = json_decode($miner->getData());
        if (!isset($minerData->value)) {
            return $response->addMessage($this->translate('No resources to siphon in that miner'));
        }
        if ($minerData->value < 1) {
            return $response->addMessage($this->translate('No resources to siphon in that miner'));
        }
        $fileLevel = $file->getLevel();
        $availableResources = $minerData->value;
        $amountSiphoned = ($availableResources < $fileLevel) ? $availableResources : $fileLevel;
        $minerData->value = $minerData->value - $amountSiphoned;
        $profile = $file->getProfile();
        switch ($miner->getFileType()->getId()) {
            default:
                $message = NULL;
                break;
            case FileType::ID_DATAMINER:
                $profile->setSnippets($profile->getSnippets() + $amountSiphoned);
                $message = sprintf(
                    $this->translate('You siphon [%s] snippets from [%s]'),
                    $amountSiphoned,
                    $miner->getName()
                );
                $response->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                break;
            case FileType::ID_COINMINER:
                $profile->setCredits($profile->getCredits() + $amountSiphoned);
                $message = sprintf(
                    $this->translate('You siphon [%s] credits from [%s]'),
                    $amountSiphoned,
                    $miner->getName()
                );
                $response->addMessage($message, GameClientResponse::CLASS_SUCCESS);
                break;
        }
        if (!$message) {
            return $response->addMessage($this->translate('Unable to siphon at this moment'), GameClientResponse::CLASS_DANGER);
        }
        $miner->setData(json_encode($minerData));
        $this->entityManager->flush($profile);
        $this->entityManager->flush($miner);
        $this->lowerIntegrityOfFile($file, 100, 1, true);
        return $response;
    }

    /**
     * @param File $file
     * @return GameClientResponse
     */
    public function executeMedkit(File $file)
    {
        $response = new GameClientResponse($file->getProfile()->getCurrentResourceId());
        $response->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $profile = $file->getProfile();
        if ($profile->getEeg() > 99) {
            return $response->addMessage($this->translate('You are already at maximum EEG'));
        }
        if ($this->isInCombat($profile)) {
            return $response->addMessage($this->translate('You are busy fighting'));
        }
        $amountHealed = $file->getLevel();
        $newEeg = $profile->getEeg() + $amountHealed;
        if ($newEeg > 99) $newEeg = 100;
        $profile->setEeg($newEeg);
        $this->entityManager->flush($profile);
        $this->lowerIntegrityOfFile($file, 100, 1, true);
        $message = sprintf(
            $this->translate('You have used the medkit - new eeg: %s'),
            $newEeg
        );
        return $response->addMessage($message, GameClientResponse::CLASS_SUCCESS);
    }

    /**
     * @param File $file
     * @return GameClientResponse
     */
    public function executeProxifier(File $file)
    {
        $response = new GameClientResponse($file->getProfile()->getCurrentResourceId());
        $response->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
        $checkResult = $this->executeWarningProxifier();
        if ($checkResult) {
            return $response->addMessage($checkResult);
        }
        $profile = $file->getProfile();
        if ($this->isInCombat($profile)) {
            return $response->addMessage($this->translate('You are busy fighting'));
        }
        $amountRecovered = ceil(round($file->getLevel()/10));
        $newSecRating = $profile->getSecurityRating() - $amountRecovered;
        if ($newSecRating < 0) $newSecRating = 0;
        $profile->setSecurityRating($newSecRating);
        $this->entityManager->flush($profile);
        $this->lowerIntegrityOfFile($file, 100, $newSecRating, true);
        $message = sprintf(
            $this->translate('You have used [%s] - sec-rating lowered by %s to %s'),
            $file->getName(),
            $amountRecovered,
            $profile->getSecurityRating()
        );
        return $response->addMessage($message, GameClientResponse::CLASS_SUCCESS);
    }

}