<?php

/**
 * FilePartSkill Entity.
 * This keeps track of which skills are needed for each file-part.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace Netrunners\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity(repositoryClass="Netrunners\Repository\FilePartSkillRepository") */
class FilePartSkill
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
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\FilePart")
     */
    protected $filePart;

    /**
     * @ORM\ManyToOne(targetEntity="Netrunners\Entity\Skill")
     */
    protected $skill;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return FilePartSkill
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFilePart()
    {
        return $this->filePart;
    }

    /**
     * @param mixed $filePart
     * @return FilePartSkill
     */
    public function setFilePart($filePart)
    {
        $this->filePart = $filePart;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSkill()
    {
        return $this->skill;
    }

    /**
     * @param mixed $skill
     * @return FilePartSkill
     */
    public function setSkill($skill)
    {
        $this->skill = $skill;
        return $this;
    }

}
