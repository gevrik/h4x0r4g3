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
use Netrunners\Entity\GameOption;
use Netrunners\Entity\MilkrunAivatarInstance;
use Netrunners\Entity\Profile;
use Netrunners\Repository\GameOptionRepository;
use Netrunners\Repository\MilkrunAivatarInstanceRepository;
use Netrunners\Repository\MilkrunAivatarRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class MilkrunAivatarService extends BaseService
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
     * @return array|bool|false
     */
    public function showMilkrunAivatars($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // init response
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $aivatars = $this->mrAivatarInstanceRepo->findByProfile($profile);
            $returnMessage = [];
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%-20s|%-7s|%-7s|%-7s|%-4s|%-4s|%-19s|%s</pre>',
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
            foreach ($aivatars as $aivatar) {
                /** @var MilkrunAivatarInstance $aivatar */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-%s">%-11s|%-20s|%-20s|%-7s|%-7s|%-7s|%-4s|%-4s|%-19s|%s</pre>',
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
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function setDefaultMrai($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        $targetMraId = $this->getNextParameter($contentArray, false, true);
        // check if they have given an id
        if (!$this->response) {
            if (!$targetMraId) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Please specify a valid id (use "showmra" to get a list)')
                    )
                ];
            }
        }
        // see if that id is a valid object
        $targetMra = false;
        if (!$this->response) {
            $targetMra = $this->entityManager->find('Netrunners\Entity\MilkrunAivatarInstance', $targetMraId);
        }
        if (!$this->response && !$targetMra) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a valid id (use "showmra" to get a list)')
                )
            ];
        }
        // check if it belongs to them
        if (!$this->response && $targetMra && $targetMra->getProfile() != $profile) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a valid id (use "showmra" to get a list)')
                )
            ];
        }
        // all good, they can now set the mra as their new default
        if (!$this->response && $targetMra) {
            $profile->setDefaultMilkrunAivatar($targetMra);
            $this->entityManager->flush($profile);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">[%s] is now your default Milkrun Aivatar</pre>',
                    $targetMra->getName()
                )
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function repairMrai($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        $defaultMra = $profile->getDefaultMilkrunAivatar();
        if (!$this->response && $defaultMra->getCurrentEeg() >= $defaultMra->getMaxEeg()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Your default Milkrun Aivatar does not need repairs')
                )
            ];
        }
        if (!$this->response && $profile->getSnippets() < 1) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You do not have enough snippets to repair the Milkrun Aivatar')
                )
            ];
        }
        if (!$this->response) {
            $repairsNeeded = $defaultMra->getMaxEeg() - $defaultMra->getCurrentEeg();
            if ($profile->getSnippets() < $repairsNeeded) $repairsNeeded = $profile->getSnippets();
            $defaultMra->setCurrentEeg($defaultMra->getCurrentEeg() + $repairsNeeded);
            $this->entityManager->flush($defaultMra);
            $profile->setSnippets($profile->getSnippets() - $repairsNeeded);
            $this->entityManager->flush($profile);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">[%s] repaired with %s snippets</pre>',
                    $defaultMra->getName(),
                    $repairsNeeded
                )
            );
        }
        return $this->response;
    }

    public function upgradeMra($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        $aivatar = $profile->getDefaultMilkrunAivatar();
        // for every five completed milkruns the aivatar can receive one upgrade
        if (!$this->response && $aivatar->getUpgrades() >= floor(round($aivatar->getCompleted()/5))) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">[%s] has already received all currently possible upgrades - complete more milkruns with it</pre>',
                    $aivatar->getName()
                )
            );
        }
        if (!$this->response) {
            $propertyString = $this->getNextParameter($contentArray, false, false, true, true);
            $command = 'showmessage';
            $message = false;
            switch ($propertyString) {
                default:
                    $cost = false;
                    $method = false;
                    $newValue = false;
                    $message = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Please specify which property to upgrade ("eeg", "attack" or "armor")')
                    );
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
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">[%s] needs %s points to upgrade %s</pre>'),
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
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] has received an upgrade to %s</pre>'),
                            $aivatar->getName(),
                            $propertyString
                        );
                        $this->entityManager->flush($aivatar);
                    }
                }
            }
            $this->response = [
                'command' => $command,
                'message' => $message
            ];
        }
        return $this->response;
    }

}
