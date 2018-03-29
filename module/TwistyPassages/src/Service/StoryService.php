<?php

/**
 * Story Service.
 * Story Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use TwistyPassages\Entity\Passage;
use TwistyPassages\Entity\Story;
use TwistyPassages\Form\StoryForm;
use Zend\Form\Form;

class StoryService extends TwistyPassagesAbstractEntityService
{

    const ROUTE = 'story';

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
     * @var Story
     */
    protected $entity;

    /**
     * @var array
     */
    static $status = [
        self::STATUS_CREATED => self::STRING_CREATED,
        self::STATUS_SUBMITTED => self::STRING_SUBMITTED,
        self::STATUS_REVIEW => self::STRING_REVIEW,
        self::STATUS_CHANGED => self::STRING_CHANGED,
        self::STATUS_APPROVED => self::STRING_APPROVED,
    ];

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
     * @return Story
     */
    public function getEntity(): Story
    {
        return $this->entity;
    }

    /**
     * @return StoryForm|Form
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    public function initQueryBuilder(): TwistyPassagesEntityServiceInterface
    {
        $this->queryBuilder->leftJoin('e.author', 'a');
        return $this;
    }

    /**
     * @param $columnName
     * @param $dir
     * @return $this|TwistyPassagesEntityServiceInterface
     */
    public function addOrderBy($columnName, $dir): TwistyPassagesEntityServiceInterface
    {
        switch ($columnName) {
            default:
                $this->queryBuilder->addOrderBy('e.' . $columnName, $dir);
                break;
        }
        return $this;
    }

    /**
     * @param string $searchValue
     * @return TwistyPassagesEntityServiceInterface
     */
    public function getSearchWhere($searchValue): TwistyPassagesEntityServiceInterface
    {
        $this->queryBuilder->andWhere($this->queryBuilder->expr()->like('u.username', $this->queryBuilder->expr()->literal($searchValue . '%')));
        return $this;
    }

    /**
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function delete($entity)
    {
        $passages = $this->entityManager->getRepository($this->getClassName())->findBy([
            'story' => $entity
        ]);
        foreach ($passages as $passage) {
            /** @var Passage $passage */
            $this->entityManager->remove($passage);
        }
        $this->entityManager->remove($entity);
        $this->flush();
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return Story::class;
    }

}
