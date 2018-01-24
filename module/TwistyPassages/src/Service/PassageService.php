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

class PassageService extends TwistyPassagesAbstractEntityService
{

    const STATUS_INVALID = 0;
    const STATUS_CREATED = 1;
    const STATUS_APPROVED = 100;

    const STRING_INVALID = 'invalid';
    const STRING_CREATED = 'created';
    const STRING_APPROVED = 'approved';

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
     * @return PassageForm|\Zend\Form\Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param int $id
     * @return null|object
     */
    public function find(int $id)
    {
        return $this->repository->find($id);
    }

}
