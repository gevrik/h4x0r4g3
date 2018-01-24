<?php

/**
 * TwistyPassages Abstract Controller.
 * TwistyPassages Abstract Controller.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Controller;

use TwistyPassages\Service\TwistyPassagesEntityServiceInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;

abstract class TwistyPassagesAbstractController extends AbstractActionController
{

    /**
     * @return TwistyPassagesEntityServiceInterface
     */
    abstract protected function getService();

    /**
     * Override the parent's onDispatch() method.
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        $response = parent::onDispatch($e);
        $this->layout()->setTemplate('layout/tp');
        return $response;
    }

    /**
     * @return mixed
     */
    protected function getUserIdentity()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->zfcUserAuthentication()->getIdentity();
    }

}
