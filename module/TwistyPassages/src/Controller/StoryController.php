<?php

/**
 * Controller for Entity Story.
 * Controller for Entity Story.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Entity\Story;
use TwistyPassages\Service\StoryService;
use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;

class StoryController extends TwistyPassagesAbstractEntityController
{

    /**
     * @var StoryService
     */
    protected $service;


    /**
     * StoryController constructor.
     * @param StoryService $service
     */
    public function __construct(
        StoryService $service
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
            /** @var Story $entity */
            $data[] = [
                'id' => $entity->getId(),
                'title' => $entity->getTitle()
            ];
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getSectionname(): string
    {
        return self::SECTION_STORIES;
    }

}
