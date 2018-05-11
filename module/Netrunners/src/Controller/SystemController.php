<?php

/**
 * Controller for Entity System.
 * Controller for Entity System.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Controller;

use Zend\View\Model\ViewModel;

final class SystemController extends NetrunnersAbstractController
{

    /**
     * SystemController constructor.
     * @param $entityManager
     * @param $entityService
     */
    public function __construct($entityManager, $entityService)
    {
        parent::__construct($entityManager, $entityService);
    }

    /**
     * @return string
     */
    function getEntityName()
    {
        return 'Netrunners\Entity\System';
    }

    /**
     * @param array $entities
     * @return array
     */
    function populateXhrData($entities)
    {
        return [];
    }

    /**
     * @return string
     */
    function getSectionName()
    {
        return 'systems';
    }

    /**
     * @return ViewModel
     */
    public function profileIndexAction()
    {
        $this->layout(self::LAYOUT_NAME);
        return new ViewModel(array(
            'section' => $this->getSectionName()
        ));
    }

}
