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
use Netrunners\Entity\Profile;
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
        $optionValue = $this->getNextParameter($contentArray, false);
        if (!$optionValue) {
            $returnMessage = $this->addCurrentOptionsOutput($profile, $returnMessage);
        }
        else {
            $gameOption = $this->gameOptionRepo->findOneBy([
                'name' => $optionValue
            ]);
            if (!$gameOption) {
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unknown game option')
                );
            }
            else {
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
            $profileGameOptionValue = $this->getProfileGameOption($profile, $gameOption->getId());
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-13s : %s</pre>',
                $gameOption->getName(),
                ($profileGameOptionValue) ? $this->translate('on') : $this->translate('off')
            );
        }
        return $returnMessage;
    }

}
