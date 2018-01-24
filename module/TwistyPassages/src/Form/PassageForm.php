<?php

/**
 * Passage Form.
 * Passage Form.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Form;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Zend\Form\Form;

class PassageForm extends Form
{

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct('passage-form');
        $this->setHydrator(new DoctrineHydrator($entityManager));
        $fieldset = new PassageFieldset($entityManager);
        $fieldset->setUseAsBaseFieldset(true);
        $this->add($fieldset);

        $this->add(array(
            'type' => 'submit',
            'name' => 'submit',
        ));

    }

}
