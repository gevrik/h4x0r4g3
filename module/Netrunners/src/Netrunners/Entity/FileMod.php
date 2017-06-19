<?php

/**
 * FileMod Entity.
 * Programs can be enhanced by using file-mods. Programs have a property called "slots" which determines, how many
 * mods can be used on a program. This is the lookup table for all available mods. Mods can be coded, but it is very
 * hard to do so. It involves high levels of advanced skills.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FileModRepository") */
class FileMod
{

    const ID_BACKSLASH = 1;

    const STRING_BACKSLASH = 'backslash';

    static $revLookup = [
        self::STRING_BACKSLASH => self::ID_BACKSLASH,
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $name;

    /**
     * @ORM\Column(type="text")
     * @var string
     */
    protected $description;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FileMod
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return FileMod
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return FileMod
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

}
