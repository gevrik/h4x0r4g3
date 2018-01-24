<?php

/**
 * Story Form.
 * Story Form.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Form;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Zend\Form\Form;

class StoryForm extends Form
{

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct('story-form');
        $this->setHydrator(new DoctrineHydrator($entityManager));
        $fieldset = new StoryFieldset($entityManager);
        $fieldset->setUseAsBaseFieldset(true);
        $this->add($fieldset);

        $this->add([
            'type' => 'submit',
            'name' => 'submit',
        ]);

    }

}
