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

class StoryService extends TwistyPassagesAbstractEntityService
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

    const WELCOME_STORY_AMOUNT = 9;

    /**
     * @var Story
     */
    protected $entity;

    /**
     * @var StoryForm
     */
    protected $form;


    /**
     * StoryService constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        parent::__construct($entityManager);
        $this->entity = new Story();
        $this->form = new StoryForm($entityManager);
    }

    /**
     * @return array
     */
    public function getForTopList(): array
    {
        $qb = $this->repository->createQueryBuilder('s');
        $qb->select('s.id, s.title, s.description, s.added, a.id as user_id, a.username as author');
        $qb->leftJoin('s.author', 'a');
        $qb->where($qb->expr()->gte('s.status', self::STATUS_APPROVED));
        $qb->orderBy('s.id', 'ASC');
        $qb->setMaxResults(self::WELCOME_STORY_AMOUNT);
        return $qb->getQuery()->getResult();
    }

    /**
     * @return Story
     */
    public function getEntity(): Story
    {
        return $this->entity;
    }

    /**
     * @return StoryForm
     */
    public function getForm(): StoryForm
    {
        return $this->form;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return Story::class;
    }

    /**
     * @param Story $entity
     */
    public function persist(Story $entity)
    {
        $this->entityManager->persist($entity);
    }

    /**
     * @param Story $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function flush(Story $entity)
    {
        $this->entityManager->flush($entity);
    }

}
