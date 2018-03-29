<?php

/**
 * Controller for Entity Profile.
 * Controller for Entity Profile.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Controller;

use Netrunners\Entity\Profile;
use Zend\View\Model\ViewModel;

class ProfileController extends NetrunnersAbstractController
{

    /**
     * ProfileController constructor.
     * @param $entityManager
     * @param $entityService
     */
    public function __construct($entityManager, $entityService)
    {
        parent::__construct($entityManager, $entityService);
    }

    /**
     * @param array $entities
     * @return array
     */
    protected function populateXhrData($entities)
    {
        $this->init();
        $data = [];
        $locale = ($this->profile) ? $this->profile->getLocale() : 'en-US';
        foreach ($entities as $entity) {
            /** @var Profile $entity */
            $data[] = [
                'id' => $entity->getId(),
                'username' => $entity->getUser()->getUsername(),
                'credits' => $this->entityService->getNumberFormat($locale, $entity->getCredits()),
                'snippets' => $this->entityService->getNumberFormat($locale, $entity->getSnippets()),
                'resourceid' => ($entity->getCurrentResourceId()) ? $entity->getCurrentResourceId() : '---',
            ];
        }
        return $data;
    }

    /**
     * @return ViewModel
     */
    public function profileAction()
    {
        $this->init();
        $this->layout(self::LAYOUT_NAME);
        return new ViewModel(array(
            'section' => $this->entityService->getSectionName(),
            'profile' => $this->profile
        ));
    }

}
