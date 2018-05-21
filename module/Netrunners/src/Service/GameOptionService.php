<?php

/**
 * GameOptionService.
 * This service resolves logic around the game options.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\GameOption;
use Netrunners\Entity\GameOptionInstance;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\GameOptionInstanceRepository;
use Netrunners\Repository\GameOptionRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class GameOptionService extends BaseService
{

    /**
     * @var GameOptionRepository
     */
    protected $gameOptionRepo;

    /**
     * @var GameOptionInstanceRepository
     */
    protected $goiRepo;


    /**
     * GameOptionService constructor.
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
        $this->gameOptionRepo = $this->entityManager->getRepository(GameOption::class);
        $this->goiRepo = $this->entityManager->getRepository(GameOptionInstance::class);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function optionsCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $returnMessage = [];
        list($contentArray, $optionName) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$optionName) {
            return $this->addCurrentOptionsOutput($profile, $returnMessage);
        }
        else {
            $gameOption = $this->gameOptionRepo->findOneBy([
                'name' => $optionName
            ]);
            if (!$gameOption) {
                $returnMessage = $this->translate('Unknown game option');
                return $this->gameClientResponse->addMessage($returnMessage)->send();
            }
            /** @var GameOption $gameOption */
            // if this is an option that requires another parameter as its value
            if ($gameOption->getDefaultValue()) {
                $gameOptionValue = $this->getNextParameter($contentArray, false, false, true, true);
                if (!$gameOptionValue) {
                    $returnMessage = $this->translate('Please specify a value');
                    return $this->gameClientResponse->addMessage($returnMessage)->send();
                }
                $now = new \DateTime();
                switch ($gameOption->getId()) {
                    default:
                        $returnMessage = $this->translate('Unable to process input');
                        return $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_DANGER)->send();
                    case GameOption::ID_BGOPACITY:
                        $gameOptionValue = floatval($gameOptionValue);
                        $gameOptionInstance = $this->goiRepo->findOneBy([
                            'gameOption' => $gameOption,
                            'profile' => $profile
                        ]);
                        if ($gameOptionInstance) {
                            /** @var GameOptionInstance $gameOptionInstance */
                            $gameOptionInstance->setValue((string)$gameOptionValue);
                            $gameOptionInstance->setChanged($now);
                        }
                        else {
                            $gameOptionInstance = new GameOptionInstance();
                            $gameOptionInstance->setValue((string)$gameOptionValue);
                            $gameOptionInstance->setProfile($profile);
                            $gameOptionInstance->setGameOption($gameOption);
                            $gameOptionInstance->setChanged($now);
                            $this->entityManager->persist($gameOptionInstance);
                        }
                        $this->entityManager->flush($gameOptionInstance);
                        $returnMessage = sprintf($this->translate('Opacity set to %s'), (string)$gameOptionValue);
                        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SUCCESS);
                        break;
                }
            }
            else {
                // just toggle this option
                $this->toggleProfileGameOption($profile, $gameOption->getId());
                $newStatus = $this->getProfileGameOption($profile, $gameOption->getId());
                $returnMessage = sprintf(
                    'Option [%s] changed to [<span class="text-%s">%s</span>]',
                    $gameOption->getName(),
                    ($newStatus) ? 'success' : 'sysmsg',
                    ($newStatus) ? $this->translate('on') : $this->translate('off')
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
            }
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param Profile $profile
     * @param $returnMessage
     * @return GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function addCurrentOptionsOutput(Profile $profile, $returnMessage)
    {
        $returnMessage = ($returnMessage) ? $returnMessage : $this->translate('Please specify which option to toggle:');
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($this->gameOptionRepo->findAll() as $gameOption) {
            /** @var GameOption $gameOption */
            if ($gameOption->getDefaultValue()) {
                $profileGameOptionValue = $this->getProfileGameOptionValue($profile, $gameOption->getId());
                $returnMessage = sprintf(
                    '%-13s : %s',
                    $gameOption->getName(),
                    $profileGameOptionValue
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
            }
            else {
                $profileGameOptionValue = $this->getProfileGameOption($profile, $gameOption->getId());
                $returnMessage = sprintf(
                    '%-13s : %s',
                    $gameOption->getName(),
                    ($profileGameOptionValue) ? $this->translate('on') : $this->translate('off')
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
            }
        }
        return $this->gameClientResponse->send();
    }

}
