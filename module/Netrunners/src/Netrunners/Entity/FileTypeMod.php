<?php

/**
 * FileTypeMod Entity.
 * This keeps track of which mods are available for each file-type.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FileTypeModRepository") */
class FileTypeMod
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FileType")
     */
    protected $fileType;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FileMod")
     */
    protected $fileMod;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FileTypeSkill
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFileType()
    {
        return $this->fileType;
    }

    /**
     * @param mixed $fileType
     * @return FileTypeSkill
     */
    public function setFileType($fileType)
    {
        $this->fileType = $fileType;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFileMod()
    {
        return $this->fileMod;
    }

    /**
     * @param mixed $fileMod
     * @return FileTypeSkill
     */
    public function setFileMod($fileMod)
    {
        $this->fileMod = $fileMod;
        return $this;
    }

}
