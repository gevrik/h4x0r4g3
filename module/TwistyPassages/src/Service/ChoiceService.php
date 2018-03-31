<?php

/**
 * Choice Service.
 * Choice Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use TwistyPassages\Entity\Choice;
use TwistyPassages\Form\ChoiceForm;
use Zend\Form\Form;
use Zend\Http\Response;

class ChoiceService extends TwistyPassagesAbstractEntityService
{

    const ROUTE = 'choice';

    const STATUS_INVALID = 0;
    const STATUS_CREATED = 1;
    const STATUS_APPROVED = 100;

    const STRING_INVALID = 'invalid';
    const STRING_CREATED = 'created';
    const STRING_APPROVED = 'approved';

    /**
     * @var array
     */
    static $status = [
        self::STATUS_CREATED => self::STRING_CREATED,
        self::STATUS_APPROVED => self::STRING_APPROVED,
    ];

    /**
     * @var Choice
     */
    protected $entity;


    /**
     * ChoiceService constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        parent::__construct($entityManager);
        $this->entity = new Choice();
        $this->form = new ChoiceForm($entityManager);
    }

    /**
     * @return Choice
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return Choice::class;
    }

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    public function initQueryBuilder()
    {
        $this->queryBuilder->leftJoin('e.story', 's');
        return $this;
    }

    /**
     * @param $columnName
     * @param $dir
     * @return TwistyPassagesEntityServiceInterface
     */
    public function addOrderBy($columnName, $dir)
    {
        switch ($columnName) {
            default:
                $this->queryBuilder->addOrderBy('e.' . $columnName, $dir);
                break;
        }
        return $this;
    }

    /**
     * @return ChoiceForm|\Zend\Form\Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param string $searchValue
     * @return TwistyPassagesEntityServiceInterface
     */
    public function getSearchWhere($searchValue)
    {
        $this->queryBuilder->andWhere($this->queryBuilder->expr()->like('e.title', $this->queryBuilder->expr()->literal($searchValue . '%')));
        return $this;
    }

    /**
     * @param int $entityId
     * @return array
     */
    public function getActionButtonsDefinitions(int $entityId)
    {
        $buttonDefinitions = $this->getDefaultActionButtons($entityId);
        return $buttonDefinitions;
    }

    /**
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function delete($entity)
    {
        if (!$entity instanceof Choice) {
            throw new \Exception("request to delete choice but no valid choice given", Response::STATUS_CODE_400);
        }
        $this->entityManager->remove($entity);
        $this->flush();
    }

}
