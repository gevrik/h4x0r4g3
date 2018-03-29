<?php

/**
 * Choice Form.
 * Choice Form.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Form;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Zend\Form\Form;

class ChoiceForm extends Form
{

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct('choice-form');
        $this->setHydrator(new DoctrineHydrator($entityManager));
        $fieldset = new ChoiceFieldset($entityManager);
        $fieldset->setUseAsBaseFieldset(true);
        $this->add($fieldset);

        $this->add([
            'type' => 'submit',
            'name' => 'submit',
        ]);

    }

}
