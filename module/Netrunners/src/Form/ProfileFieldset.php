<?php

/**
 * Fieldset for entity Profile.
 * Fieldset for entity Profile.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Form;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Netrunners\Entity\Profile;
use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class ProfileFieldset extends Fieldset implements InputFilterProviderInterface
{

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct('profile');

        $this->setHydrator(new DoctrineHydrator($entityManager))
            ->setObject(new Profile());

        $this->add(array(
            'type' => 'hidden',
            'name' => 'id'
        ));
    }

    public function getInputFilterSpecification()
    {
        return array(
            'id' => array(
                'required' => false
            ),
        );
    }
}
