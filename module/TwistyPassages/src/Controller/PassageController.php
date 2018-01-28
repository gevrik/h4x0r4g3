<?php

/**
 * Controller for Entity Passage.
 * Controller for Entity Passage.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Entity\Passage;
use TwistyPassages\Filter\StringLengthFilter;
use TwistyPassages\Service\PassageService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;

class PassageController extends TwistyPassagesAbstractEntityController
{

    /**
     * @var PassageService
     */
    protected $service;


    /**
     * PassageController constructor.
     * @param PassageService $service
     */
    public function __construct(
        PassageService $service
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
            /** @var Passage $entity */
            $description = $entity->getDescription();
            $filter = new StringLengthFilter();
            $description = $filter->filter($description);
            $data[] = [
                'actions' => $this->service->getActionButtonsDefinitions($entity->getId()),
                'title' => $entity->getTitle(),
                'description' => $description,
                'status' => PassageService::$status[$entity->getStatus()],
                'added' => $entity->getAdded()->format('Y/m/d H:i:s'),
                'sub' => ($entity->getAllowChoiceSubmissions()) ? "yes" : "no",
            ];
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getSectionname(): string
    {
        return self::SECTION_PASSAGES;
    }

}
