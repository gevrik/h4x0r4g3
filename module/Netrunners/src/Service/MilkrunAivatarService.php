<?php

/**
 * MilkrunAivatarService.
 * This service resolves logic around the milkrun aivatars.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\MilkrunAivatarInstance;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\MilkrunAivatarInstanceRepository;
use Netrunners\Repository\MilkrunAivatarRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class MilkrunAivatarService extends BaseService
{

    /**
     * @var MilkrunAivatarRepository|null
     */
    protected $mrAivatarRepo = NULL;

    /**
     * @var MilkrunAivatarInstanceRepository|null
     */
    protected $mrAivatarInstanceRepo = NULL;


    /**
     * MilkrunAivatarService constructor.
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
        $this->mrAivatarRepo = $this->entityManager->getRepository('Netrunners\Entity\MilkrunAivatar');
        $this->mrAivatarInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\MilkrunAivatarInstance');
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showMilkrunAivatars($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // init response
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $aivatars = $this->mrAivatarInstanceRepo->findByProfile($profile);
        $returnMessage = sprintf(
            '%-11s|%-20s|%-20s|%-7s|%-7s|%-7s|%-4s|%-4s|%-19s|%s',
            $this->translate('ID'),
            $this->translate('NAME'),
            $this->translate('TYPE'),
            $this->translate('EEG'),
            $this->translate('ATTACK'),
            $this->translate('ARMOR'),
            $this->translate('CMPL'),
            $this->translate('PNTS'),
            $this->translate('CREATED'),
            $this->translate('SPECIALS')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($aivatars as $aivatar) {
            /** @var MilkrunAivatarInstance $aivatar */
            $returnMessage = sprintf(
                '<span class="text-%s">%-11s|%-20s|%-20s|%-7s|%-7s|%-7s|%-4s|%-4s|%-19s|%s</span>',
                ($aivatar === $profile->getDefaultMilkrunAivatar()) ? 'newbie' : 'white',
                $aivatar->getId(),
                $aivatar->getName(),
                $aivatar->getMilkrunAivatar()->getName(),
                $aivatar->getCurrentEeg() . '/' . $aivatar->getMaxEeg(),
                $aivatar->getCurrentAttack() . '/' . $aivatar->getMaxAttack(),
                $aivatar->getCurrentArmor() . '/' . $aivatar->getMaxArmor(),
                $aivatar->getCompleted(),
                $aivatar->getPointsearned() - $aivatar->getPointsused(),
                $aivatar->getCreated()->format('Y/m/d H:is:'),
                ($aivatar->getSpecials()) ? $aivatar->getSpecials() : '---'
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
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
    public function setDefaultMrai($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $targetMraId = $this->getNextParameter($contentArray, false, true);
        // check if they have given an id
        if (!$targetMraId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a valid id (use "showmra" to get a list)'))->send();
        }
        // see if that id is a valid object
        $targetMra = $this->entityManager->find('Netrunners\Entity\MilkrunAivatarInstance', $targetMraId);
        if (!$targetMra) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a valid id (use "showmra" to get a list)'))->send();
        }
        // check if it belongs to them
        if ($targetMra->getProfile() != $profile) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a valid id (use "showmra" to get a list)'))->send();
        }
        // all good, they can now set the mra as their new default
        $profile->setDefaultMilkrunAivatar($targetMra);
        $this->entityManager->flush($profile);
        $message = sprintf(
            '[%s] is now your default Milkrun Aivatar',
            $targetMra->getName()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function repairMrai($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $defaultMra = $profile->getDefaultMilkrunAivatar();
        if ($defaultMra->getCurrentEeg() >= $defaultMra->getMaxEeg()) {
            $message = $this->translate('Your default Milkrun Aivatar does not need repairs');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($profile->getSnippets() < 1) {
            $message = $this->translate('You do not have enough snippets to repair the Milkrun Aivatar');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $repairsNeeded = $defaultMra->getMaxEeg() - $defaultMra->getCurrentEeg();
        if ($profile->getSnippets() < $repairsNeeded) $repairsNeeded = $profile->getSnippets();
        $defaultMra->setCurrentEeg($defaultMra->getCurrentEeg() + $repairsNeeded);
        $this->entityManager->flush($defaultMra);
        $profile->setSnippets($profile->getSnippets() - $repairsNeeded);
        $this->entityManager->flush($profile);
        $message = sprintf(
            '[%s] repaired with %s snippets',
            $defaultMra->getName(),
            $repairsNeeded
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function upgradeMra($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $aivatar = $profile->getDefaultMilkrunAivatar();
        // for every five completed milkruns the aivatar can receive one upgrade
        if ($aivatar->getUpgrades() >= floor(round($aivatar->getCompleted()/5))) {
            $message = sprintf(
                '[%s] has already received all currently possible upgrades - complete more milkruns with it',
                $aivatar->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $propertyString = $this->getNextParameter($contentArray, false, false, true, true);
        $message = false;
        switch ($propertyString) {
            default:
                $cost = false;
                $method = false;
                $newValue = false;
                $message = $this->translate('<span class="text-warning">Please specify which property to upgrade ("eeg", "attack" or "armor")</span>');
                break;
            case 'eeg':
                $cost = 8;
                $method = 'setMaxEeg';
                $newValue = $aivatar->getMaxEeg() + 2;
                break;
            case 'attack':
                $cost = 32;
                $method = 'setMaxAttack';
                $newValue = $aivatar->getMaxAttack() + 1;
                break;
            case 'armor':
                $cost = 16;
                $method = 'setMaxArmor';
                $newValue = $aivatar->getMaxArmor() + 2;
                break;
        }
        if (!$message) {
            $availablePoints = $aivatar->getPointsearned() - $aivatar->getPointsused();
            if ($availablePoints < $cost) {
                $message = sprintf(
                    $this->translate('<span class="text-warning">[%s] needs %s points to upgrade %s</span>'),
                    $aivatar->getName(),
                    $cost,
                    $propertyString
                );
            }
            else {
                if ($method && $newValue) {
                    // upgrade property
                    $aivatar->$method($newValue);
                    $aivatar->setPointsused($aivatar->getPointsused()+$cost);
                    $aivatar->setUpgrades($aivatar->getUpgrades()+1);
                    $aivatar->setModified(new \DateTime());
                    $message = sprintf(
                        $this->translate('<span class="text-success">[%s] has received an upgrade to %s</span>'),
                        $aivatar->getName(),
                        $propertyString
                    );
                    $this->entityManager->flush($aivatar);
                }
            }
        }
        return $this->gameClientResponse->addMessage($message)->send();
    }

}
