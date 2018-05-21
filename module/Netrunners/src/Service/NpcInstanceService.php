<?php

/**
 * NpcInstance Service.
 * The service supplies methods that resolve logic around NpcInstance objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\NpcRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class NpcInstanceService extends BaseService
{

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;

    /**
     * @var NpcRepository
     */
    protected $npcRepo;


    /**
     * NpcInstanceService constructor.
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
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        $this->npcRepo = $this->entityManager->getRepository('Netrunners\Entity\Npc');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function considerNpc($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false);
        $npc = $this->findNpcByNameOrNumberInCurrentNode($parameter);
        if (!$npc) {
            return $this->gameClientResponse->addMessage($this->translate('No such entity'))->send();
        }
        $message = sprintf(
            $this->translate('Consideration info for [%s]'),
            $npc->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('description'),
            wordwrap($npc->getDescription(), 120)
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_MUTED);
        $message = sprintf(
            $this->translate('%-12s : %s/%s'),
            $this->translate('eeg'),
            $npc->getCurrentEeg(),
            $npc->getMaxEeg()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('snippets'),
            $npc->getSnippets()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('credits'),
            $npc->getCredits()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('level'),
            $npc->getLevel()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('aggressive'),
            ($npc->getAggressive()) ? $this->translate('<span class="text-danger">yes</span>') : $this->translate('no')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('roaming'),
            ($npc->getRoaming()) ? $this->translate('yes') : $this->translate('no')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('codegates'),
            ($npc->getBypassCodegates()) ? $this->translate('<span class="text-danger">yes</span>') : $this->translate('no')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('stealthing'),
            ($npc->getStealthing()) ? $this->translate('<span class="text-danger">yes</span>') : $this->translate('no')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('type'),
            $npc->getNpc()->getName()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('owner'),
            ($npc->getProfile()) ? $npc->getProfile()->getUser()->getUsername() : '---'
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('faction'),
            ($npc->getFaction()) ? $npc->getFaction()->getName() : $this->translate('---')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('%-12s : %s'),
            $this->translate('group'),
            ($npc->getGroup()) ? $npc->getGroup()->getName() : $this->translate('---')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function changeNpcName($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* npc param can be given as name or number, so we need to handle both */
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true);
        // check if they have specified the npc instance to change
        if (!$parameter) {
            $message = $this->translate('Please specify the name or number of the entity that you want to rename');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now check if we can find that npc instance
        $npc = $this->findNpcByNameOrNumberInCurrentNode($parameter);
        if (!$npc) {
            $message = $this->translate('No such entity');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they can change the name
        if ($profile !== $npc->getProfile()) {
            $message = $this->translate('Permission denied');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$newName) {
            $message = $this->translate('Please specify a name for the entity (32-chars max, alpha-numeric only)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $checkResult = $this->stringChecker($newName);
        if ($checkResult) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        // turn spaces in name to underscores
        $name = str_replace(' ', '_', $newName);
        $npc->setName($name);
        $this->entityManager->flush($npc);
        $message = sprintf(
            $this->translate('Entity name changed to [%s]'),
            $name
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has edited [%s]'),
            $this->user->getUsername(),
            $name
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function esetCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        /* npc param can be given as name or number, so we need to handle both */
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true);
        // check if they have specified the npc instance to change
        if (!$parameter) {
            $message = $this->translate('Please specify the name or number of the entity that you want to modify');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now check if we can find that npc instance
        $npc = $this->findNpcByNameOrNumberInCurrentNode($parameter);
        if (!$npc) {
            $message = $this->translate('No such entity');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they can change the entity
        if ($profile !== $npc->getProfile()) {
            $message = $this->translate('Permission denied');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // get which property they want to change
        list($contentArray, $npcPropertyString) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$npcPropertyString) {
            $message = $this->translate('Please specify the property that you want to set (roaming, aggressive, codegates)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // get which value the property should be set to (if not given, default is off)
        $propertyValueString = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$propertyValueString) $propertyValueString = 'off';
        switch ($propertyValueString) {
            default:
                $propertyValue = 0;
                $propertyValueString = 'off';
                break;
            case 'on':
                $propertyValue = 1;
                break;
            case 'off':
                $propertyValue = 0;
                break;
        }
        switch ($npcPropertyString) {
            default:
                $message = $this->translate('Please specify the property that you want to set (roaming, aggressive)');
                return $this->gameClientResponse->addMessage($message)->send();
            case 'roaming':
                $npc->setRoaming($propertyValue);
                $this->entityManager->flush($npc);
                $message = sprintf(
                    $this->translate('[%s] [%s] set to [%s]'),
                    $npc->getName(),
                    $npcPropertyString,
                    $propertyValueString
                );
            break;
            case 'aggressive':
                $npc->setAggressive($propertyValue);
                $this->entityManager->flush($npc);
                $message = sprintf(
                    $this->translate('[%s] [%s] set to [%s]'),
                    $npc->getName(),
                    $npcPropertyString,
                    $propertyValueString
                );
            break;
            case 'codegates':
                $npc->setBypassCodegates($propertyValue);
                $this->entityManager->flush($npc);
                $message = sprintf(
                    $this->translate('[%s] [%s] set to [%s]'),
                    $npc->getName(),
                    $npcPropertyString,
                    $propertyValueString
                );
                break;
        }
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

}
