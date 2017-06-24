<?php

namespace Netrunners\View\Helper;

use Zend\View\Helper\AbstractHelper;

class ImageUrlHelper extends AbstractHelper
{
    const URL_DEFAULT = 'https://www.h4x0r4g3.com';

    /**
     * @var string URL
     */
    protected $url;

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     * @return ImageUrlHelper
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param array $config
     */
    public function __construct($config)
    {
        if(!array_key_exists('assetsconfig', $config))
        {
            $url = self::URL_DEFAULT;
        } else {
            $url = $config['assetsconfig']['protocol'] . '://' . $config['assetsconfig']['host'];
        }

        $this->setUrl($url);
    }

    /**
     * @return string URL
     */
    public function __invoke()
    {
        return $this->getUrl();
    }
}
