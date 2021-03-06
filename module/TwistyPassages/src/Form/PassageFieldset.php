<?php

/**
 * Passage Fieldset.
 * Passage Fieldset.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Form;

use Doctrine\Common\Persistence\ObjectManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use TwistyPassages\Entity\Story;
use TwistyPassages\Filter\HtmlAwed;
use TwistyPassages\Form\Element\ObjectHidden;
use Zend\Filter\StringTrim;
use Zend\Filter\ToInt;
use Zend\Form\Element\Checkbox;
use Zend\Form\Element\DateTime;
use Zend\Form\Element\Hidden;
use Zend\Form\Element\Select;
use Zend\Form\Element\Text;
use Zend\Form\Element\Textarea;
use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Date;
use Zend\Validator\Digits;

class PassageFieldset extends Fieldset implements InputFilterProviderInterface
{

    /**
     * PassageFieldset constructor.
     * @param ObjectManager $objectManager
     */
    public function __construct(ObjectManager $objectManager)
    {
        parent::__construct('passage');

        $this->setHydrator(new DoctrineHydrator($objectManager))->setObject(new Story());

        $this->add([
            'type' => Hidden::class,
            'name' => 'id',
        ]);

        $this->add([
            'type' => Select::class,
            'name' => 'status',
        ]);

        $this->add([
            'type' => Text::class,
            'name' => 'title',
        ]);

        $this->add([
            'type' => Textarea::class,
            'name' => 'description',
        ]);

        $this->add([
            'type' => DateTime::class,
            'name' => 'added',
        ]);

        $this->add([
            'type' => Checkbox::class,
            'name' => 'allowChoiceSubmissions',
        ]);

        $this->add([
            'type' => ObjectHidden::class,
            'name' => 'story',
            'options' => [
                'object_manager' => $objectManager,
                'target_class' => Story::class,
            ],
        ]);

    }

    /**
     * @return array
     */
    public function getInputFilterSpecification()
    {
        return [
            'id' => [
                'required' => false
            ],
            'title' => [
                'required' => true,
                'filters'  => [
                    ['name' => StringTrim::class],
                    [
                        'name' => HtmlAwed::class,
                        'options' => [
                            'safe' => 1,
                        ],
                    ],
                ],
                'validators' => [
                ],
            ],
            'description' => [
                'required' => true,
                'filters'  => [
                    [
                        'name' => HtmlAwed::class,
                        'options' => [
                            'safe' => 1,
                            'elements' => 'strong, em, i, h2, h3, p, br, hr'
                        ],
                    ],
                ],
                'validators' => [
                ],
            ],
            'added' => [
                'required' => true,
                'validators' => [
                    [
                        'name' => Date::class,
                        'options' => [
                            'format' => 'Y-m-d H:i:s'
                        ],
                    ]
                ],
            ],
            'status' => [
                'required' => true,
                'filters'  => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    ['name' => Digits::class]
                ],
            ],
            'allowChoiceSubmissions' => [
                'required' => true,
                'filters'  => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    ['name' => Digits::class]
                ],
            ],
            'story' => [
                'required' => true,
                'filters'  => [
                    ['name' => ToInt::class],
                ],
                'validators' => [
                    ['name' => Digits::class]
                ],
            ],
        ];
    }

}
