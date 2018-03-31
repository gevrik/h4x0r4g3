<?php

/**
 * Controller for Entity Passage.
 * Controller for Entity Passage.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Entity\Choice;
use TwistyPassages\Service\ChoiceService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;

class ChoiceController extends TwistyPassagesAbstractEntityController
{

    /**
     * @var ChoiceService
     */
    protected $service;


    /**
     * ChoiceController constructor.
     * @param ChoiceService $service
     */
    public function __construct(
        ChoiceService $service
    )
    {
        $this->service = $service;
    }

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    protected function getService()
    {
        return $this->service;
    }

    /**
     * @param array $entities
     * @return array
     */
    protected function populateXhrData($entities): array
    {
        $data = [];
        foreach ($entities as $entity) {
            /** @var Choice $entity */
            $data[] = [
                'actions' => $this->service->getActionButtonsDefinitions($entity->getId()),
                'title' => $entity->getTitle(),
                'description' => $entity->getDescription(),
                'status' => ChoiceService::$status[$entity->getStatus()],
                'added' => $entity->getAdded()->format('Y/m/d H:i:s'),
            ];
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getSectionname()
    {
        return self::SECTION_CHOICES;
    }

}
