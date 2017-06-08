<?php

/**
 * Form Profile edit.
 * Form Profile edit.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Form;

use Doctrine\ORM\EntityManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Zend\Form\Form;

class ProfileEditForm extends Form
{

    public function __construct(EntityManager $entityManager)
    {
        parent::__construct('profile-edit-form');
        // The form will hydrate an object of type "Profile"
        $this->setHydrator(new DoctrineHydrator($entityManager));
        // Add the fieldset, and set it as the base fieldset
        $fieldset = new ProfileFieldset($entityManager);
        $fieldset->setUseAsBaseFieldset(true);
        $this->add($fieldset);
        // Add submit button
        $this->add(array(
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => array(
                'value' => _('Save profile'),
                'id' => 'submitbutton',
                'class' => 'btn btn-primary',
            ),
        ));
    }
}
