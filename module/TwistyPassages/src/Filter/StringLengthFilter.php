<?php

namespace TwistyPassages\Filter;

use Zend\Filter\AbstractFilter;

class StringLengthFilter extends AbstractFilter
{

    /**
     * @var int
     */
    protected $length = 50;

    /**
     * @var bool
     */
    protected $ellipses = true;


    /**
     * StringLengthFilter constructor.
     * @param null|array|\Traversable $options
     */
    public function __construct($options = NULL)
    {
        if ($options) {
            $this->setOptions($options);
        }
    }

    /**
     * @param int $length
     * @return StringLengthFilter
     */
    public function setLength(int $length)
    {
        $this->length = $length;
        return $this;
    }

    /**
     * @param bool $ellipses
     * @return StringLengthFilter
     */
    public function setEllipses(bool $ellipses)
    {
        $this->ellipses = $ellipses;
        return $this;
    }

    /**
     * @param mixed $value
     * @return bool|mixed|string
     */
    public function filter($value)
    {
        // already under max length
        if (strlen($value) <= $this->length) {
            return $value;
        }
        // find last space
        $lastSpace = strrpos(substr($value, 0, $this->length), ' ');
        $value = substr($value, 0, $lastSpace);
        // add ellipses (...)
        if ($this->ellipses) {
            $value .= '...';
        }
        return $value;
    }

}
