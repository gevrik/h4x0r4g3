<?php

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

        $this->add(array(
            'type' => 'submit',
            'name' => 'submit',
            'attributes' => array(
                'value' => _('Create story'),
            ),
        ));

    }

}
