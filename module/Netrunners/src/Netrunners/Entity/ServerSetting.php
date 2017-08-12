<?php

/**
 * ServerSetting Entity.
 * This keeps track of important server settings.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\ServerSettingRepository") */
class ServerSetting
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $wildernessSystemId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $wildernessHubNodeId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $chatsuboSystemId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    protected $chatsuboNodeId;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @var string
     */
    protected $motd;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return ServerSetting
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getWildernessSystemId()
    {
        return $this->wildernessSystemId;
    }

    /**
     * @param int $wildernessSystemId
     * @return ServerSetting
     */
    public function setWildernessSystemId($wildernessSystemId)
    {
        $this->wildernessSystemId = $wildernessSystemId;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatsuboSystemId()
    {
        return $this->chatsuboSystemId;
    }

    /**
     * @param int $chatsuboSystemId
     * @return ServerSetting
     */
    public function setChatsuboSystemId($chatsuboSystemId)
    {
        $this->chatsuboSystemId = $chatsuboSystemId;
        return $this;
    }

    /**
     * @return int
     */
    public function getWildernessHubNodeId()
    {
        return $this->wildernessHubNodeId;
    }

    /**
     * @param int $wildernessHubNodeId
     * @return ServerSetting
     */
    public function setWildernessHubNodeId($wildernessHubNodeId)
    {
        $this->wildernessHubNodeId = $wildernessHubNodeId;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatsuboNodeId()
    {
        return $this->chatsuboNodeId;
    }

    /**
     * @param int $chatsuboNodeId
     * @return ServerSetting
     */
    public function setChatsuboNodeId($chatsuboNodeId)
    {
        $this->chatsuboNodeId = $chatsuboNodeId;
        return $this;
    }

    /**
     * @return string
     */
    public function getMotd()
    {
        return $this->motd;
    }

    /**
     * @param string $motd
     * @return ServerSetting
     */
    public function setMotd($motd)
    {
        $this->motd = $motd;
        return $this;
    }

}
