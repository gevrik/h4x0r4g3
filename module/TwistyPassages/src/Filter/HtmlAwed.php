<?php

namespace TwistyPassages\Filter;

use Zend\Filter\AbstractFilter;

class HtmlAwed extends AbstractFilter
{

    /**
     * @var array|null
     */
    protected $options = ['safe' => 1, 'elements' => 'strong'];


    /**
     * HtmlAwed constructor.
     * @param null|array|\Traversable $options
     */
    public function __construct($options = NULL)
    {
        if ($options) {
            $this->options = $options;
        }
    }

    /**
     * @param mixed $value
     * @return mixed|null|string|string[]
     */
    public function filter($value)
    {
        $value = htmLawed($value, $this->options);
        return $value;
    }

}
