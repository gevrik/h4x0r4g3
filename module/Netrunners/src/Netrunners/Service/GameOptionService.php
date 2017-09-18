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
use Netrunners\Repository\GameOptionInstanceRepository;
use Netrunners\Repository\GameOptionRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class GameOptionService extends BaseService
{

    /**
     * @var GameOptionRepository|null
     */
    protected $gameOptionRepo = NULL;

    /**
     * GameOptionService constructor.
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
        $this->gameOptionRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOption');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function optionsCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $returnMessage = [];
        list($contentArray, $optionName) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$optionName) {
            $returnMessage = $this->addCurrentOptionsOutput($profile, $returnMessage);
        }
        else {
            $gameOption = $this->gameOptionRepo->findOneBy([
                'name' => $optionName
            ]);
            if (!$gameOption) {
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unknown game option')
                );
            }
            else {
                /** @var GameOption $gameOption */
                // if this is an option that requires another parameter as its value
                if ($gameOption->getDefaultValue()) {
                    $goiRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOptionInstance');
                    /** @var GameOptionInstanceRepository $goiRepo */
                    $gameOptionValue = $this->getNextParameter($contentArray, false, false, true, true);
                    if (!$gameOptionValue) {
                        $returnMessage[] = sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Please specify a value')
                        );
                    }
                    if (empty($returnMessage)) {
                        $now = new \DateTime();
                        switch ($gameOption->getId()) {
                            default:
                                break;
                            case GameOption::ID_BGOPACITY:
                                $gameOptionValue = floatval($gameOptionValue);
                                $gameOptionInstance = $goiRepo->findOneBy([
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
                                break;
                        }
                    }
                }
                else {
                    // just toggle this option
                    $this->toggleProfileGameOption($profile, $gameOption->getId());
                    $newStatus = $this->getProfileGameOption($profile, $gameOption->getId());
                    $returnMessage[] = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-white">Option [%s] changed to [<span class="text-%s">%s</span>]</pre>',
                        $gameOption->getName(),
                        ($newStatus) ? 'success' : 'sysmsg',
                        ($newStatus) ? $this->translate('on') : $this->translate('off')
                    );
                }
            }
        }
        $this->response = [
            'command' => 'showoutput',
            'message' => $returnMessage
        ];
        return $this->response;
    }

    /**
     * @param Profile $profile
     * @param $returnMessage
     * @return array
     */
    private function addCurrentOptionsOutput(Profile $profile, $returnMessage)
    {
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
            $this->translate('Please specify which option to toggle:')
        );
        foreach ($this->gameOptionRepo->findAll() as $gameOption) {
            /** @var GameOption $gameOption */
            if ($gameOption->getDefaultValue()) {
                $profileGameOptionValue = $this->getProfileGameOptionValue($profile, $gameOption->getId());
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-13s : %s</pre>',
                    $gameOption->getName(),
                    $profileGameOptionValue
                );
            }
            else {
                $profileGameOptionValue = $this->getProfileGameOption($profile, $gameOption->getId());
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-13s : %s</pre>',
                    $gameOption->getName(),
                    ($profileGameOptionValue) ? $this->translate('on') : $this->translate('off')
                );
            }
        }
        return $returnMessage;
    }

}
