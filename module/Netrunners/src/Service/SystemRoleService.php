<?php

/**
 * SystemRole Service.
 * The service supplies methods that resolve logic around system roles.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class SystemRoleService extends BaseService
{

    /**
     * SystemService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator, $entityGenerator);
    }

    public function grantSystemRoleCommand($resourceId, $contentArray)
    {

    }

    public function removeSystemRoleCommand($resourceId, $contentArray)
    {

    }

    public function changeExpiryDateOfInstance($resourceId, $contentArray)
    {

    }

    public function showSystemRolesCommand($resourceId, $contentArray)
    {

    }

    public function showProfileSystemRolesCommand($resourceId, $contentArray)
    {

    }

}