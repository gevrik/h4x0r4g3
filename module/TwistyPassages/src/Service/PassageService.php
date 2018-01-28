<?php

/**
 * Passage Service.
 * Passage Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use TwistyPassages\Entity\Passage;
use TwistyPassages\Form\PassageForm;
use Zend\Form\Form;
use Zend\Http\Response;

class PassageService extends TwistyPassagesAbstractEntityService
{

    const ROUTE = 'passage';

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
        self::STATUS_INVALID => self::STRING_INVALID,
        self::STATUS_CREATED => self::STRING_CREATED,
        self::STATUS_APPROVED => self::STRING_APPROVED,
    ];

    /**
     * @var Passage
     */
    protected $entity;


    /**
     * PassageService constructor.
     * @param $entityManager
     */
    public function __construct($entityManager)
    {
        parent::__construct($entityManager);
        $this->entity = new Passage();
        $this->form = new PassageForm($entityManager);
    }

    /**
     * @return Passage
     */
    public function getEntity(): Passage
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return Passage::class;
    }

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    public function initQueryBuilder(): TwistyPassagesEntityServiceInterface
    {
        $this->queryBuilder->leftJoin('e.story', 's');
        return $this;
    }

    /**
     * @param $columnName
     * @param $dir
     * @return TwistyPassagesEntityServiceInterface
     */
    public function addOrderBy($columnName, $dir): TwistyPassagesEntityServiceInterface
    {
        switch ($columnName) {
            default:
                $this->queryBuilder->addOrderBy('e.' . $columnName, $dir);
                break;
            case 'storytitle':
                $this->queryBuilder->addOrderBy('s.title', $dir);
                break;
        }
        return $this;
    }

    /**
     * @return PassageForm|\Zend\Form\Form
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    /**
     * @param string $searchValue
     * @return TwistyPassagesEntityServiceInterface
     */
    public function getSearchWhere($searchValue): TwistyPassagesEntityServiceInterface
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
        if (!$entity instanceof Passage) {
            throw new \Exception("request to delete passage but no valid passage given", Response::STATUS_CODE_400);
        }
        $this->entityManager->remove($entity);
        $this->flush();
    }

}
