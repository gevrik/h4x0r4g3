<?php

/**
 * Form element for hidden doctrine objects.
 * To circumvent proxy issues.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Form\Element;

use DoctrineModule\Form\Element\ObjectSelect;
use DoctrineModule\Form\Element\Proxy;
use Zend\Form\Element\Hidden as HiddenElement;

class ObjectHidden extends HiddenElement
{
    /**
     * @var Proxy
     */
    protected $proxy;

    /**
     * @return Proxy
     */
    public function getProxy()
    {
        if (null === $this->proxy) {
            $this->proxy = new Proxy();
        }
        return $this->proxy;
    }

    /**
     * @param  array|\Traversable $options
     * @return ObjectSelect
     */
    public function setOptions($options)
    {
        $this->getProxy()->setOptions($options);
        return parent::setOptions($options);
    }

    /**
     * {@inheritDoc}
     */
    public function setValue($value)
    {
        return parent::setValue($this->getProxy()->getValue($value));
    }

    /**
     * {@inheritDoc}
     */
    public function getValueOptions()
    {
        if (empty($this->valueOptions)) {
            $this->setValueOptions($this->getProxy()->getValueOptions());
        }
        return $this->valueOptions;
    }

}
