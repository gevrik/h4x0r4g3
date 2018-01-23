<?php

/**
 * Story Service.
 * Story Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use TwistyPassages\Entity\Story;
use TwistyPassages\Form\StoryForm;
use TwistyPassages\Repository\StoryRepository;

class StoryService extends TwistyPassagesAbstractService
{

    const STATUS_INVALID = 0;
    const STATUS_CREATED = 1;
    const STATUS_SUBMITTED = 2;
    const STATUS_REVIEW = 3;
    const STATUS_CHANGED = 4;
    const STATUS_APPROVED = 100;

    const STRING_INVALID = 'invalid';
    const STRING_CREATED = 'created';
    const STRING_SUBMITTED = 'submitted';
    const STRING_REVIEW = 'review';
    const STRING_CHANGED = 'changed';
    const STRING_APPROVED = 'approved';

    /**
     * @var StoryRepository
     */
    protected $repository;

    /**
     * StoryService constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        parent::__construct($entityManager);
        $this->repository = $this->entityManager->getRepository(Story::class);
    }

    /**
     * @return StoryForm
     */
    public function getForm()
    {
        return new StoryForm($this->entityManager);
    }

    /**
     * @return array
     */
    public function getForTopList()
    {
        return $this->repository->findForTopList();
    }

}
