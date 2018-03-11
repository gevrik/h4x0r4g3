<?php

/**
 * Abstract Mapper.
 * This mapper supplies base methods for child mapper classes.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Mapper;

use Application\Service\WebsocketService;

abstract class AbstractMapper
{

    /**
     * @var array
     */
    protected $data = [];


    /**
     * AbstractMapper constructor.
     */
    public function __construct()
    {
        $methodName = 'get' . $this->getPoolName();
        $this->data = $this->getWebsocketServer()->$methodName;
    }

    /**
     * @return string
     */
    abstract public function getPoolName();

    /**
     * @return WebsocketService
     */
    protected function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function findById(int $id)
    {
        return (array_key_exists($id, $this->data)) ? $this->data[$id] : NULL;
    }

}
