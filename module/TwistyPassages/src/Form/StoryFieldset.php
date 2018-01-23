<?php

namespace TwistyPassages\Form;

use Doctrine\Common\Persistence\ObjectManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use TwistyPassages\Entity\Story;
use TwistyPassages\Service\StoryService;
use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class StoryFieldset extends Fieldset implements InputFilterProviderInterface
{

    public function __construct(ObjectManager $objectManager)
    {
        parent::__construct('story');

        $this->setHydrator(new DoctrineHydrator($objectManager))->setObject(new Story());

        $this->add(array(
            'type' => 'hidden',
            'name' => 'id'
        ));

        $this->add(array(
            'type' => 'Zend\Form\Element\Select',
            'name' => 'status',
            'options' => array(
                'label' => _('Status'),
                'value_options' => array(
                    StoryService::STATUS_INVALID => StoryService::STRING_INVALID,
                    StoryService::STATUS_CREATED => StoryService::STRING_CREATED,
                    StoryService::STATUS_SUBMITTED => StoryService::STRING_SUBMITTED,
                    StoryService::STATUS_REVIEW => StoryService::STRING_REVIEW,
                    StoryService::STATUS_CHANGED => StoryService::STRING_CHANGED,
                    StoryService::STATUS_APPROVED => StoryService::STRING_APPROVED
                ),
            ),
            'attributes' => array(
                'value' => StoryService::STATUS_CREATED,
            )
        ));

        $this->add(array(
            'type' => 'Zend\Form\Element\Text',
            'name' => 'title',
            'options' => array(
                'label' => _('Title'),
            ),
        ));

        $this->add(array(
            'type' => 'Zend\Form\Element\Textarea',
            'name' => 'description',
            'options' => array(
                'label' => _('Description'),
            ),
        ));

        $this->add(array(
            'type' => 'hidden',
            'name' => 'added'
        ));

        $this->add(array(
            'type' => 'hidden',
            'name' => 'author'
        ));

    }

    public function getInputFilterSpecification()
    {
        return array(
            'id' => array(
                'required' => false
            ),
            'title' => array(
                'required' => true,
                'filters'  => array(
                    array('name' => 'Zend\Filter\StringTrim'),
                ),
                'validators' => array(
                ),
            ),
            'description' => array(
                'required' => true,
                'filters'  => array(
                    array('name' => 'Zend\Filter\StringTrim'),
                ),
                'validators' => array(
                ),
            ),
            'added' => array(
                'required' => false
            ),
            'status' => array(
                'required' => false
            ),
            'author' => array(
                'required' => false
            ),
        );
    }

}
