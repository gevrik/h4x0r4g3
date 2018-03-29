<?php

/**
 * PassageChoice Entity.
 * Bridge Entity to simulate the many-to-many relation of passages and choices.
 * @version 1.0
 * @author Gevrik gevrik@protonmail.com
 * @copyright TMO
 */

namespace TwistyPassages\Entity;

use Doctrine\ORM\Mapping as ORM;

/** @ORM\Entity */
class PassageChoice
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $added;

    // ORM

    /**
     * @ORM\ManyToOne(targetEntity="TwistyPassages\Entity\Passage")
     */
    protected $passage;

    /**
     * @ORM\ManyToOne(targetEntity="TwistyPassages\Entity\Choice")
     */
    protected $choice;


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return PassageChoice
     */
    public function setId(int $id): PassageChoice
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getAdded(): \DateTime
    {
        return $this->added;
    }

    /**
     * @param \DateTime $added
     * @return PassageChoice
     */
    public function setAdded(\DateTime $added): PassageChoice
    {
        $this->added = $added;
        return $this;
    }

    // ORM

    /**
     * @return null|Passage
     */
    public function getPassage()
    {
        return $this->passage;
    }

    /**
     * @param Passage $passage
     * @return PassageChoice
     */
    public function setPassage($passage)
    {
        $this->passage = $passage;
        return $this;
    }

    /**
     * @return null|Choice
     */
    public function getChoice()
    {
        return $this->choice;
    }

    /**
     * @param Choice $choice
     * @return PassageChoice
     */
    public function setChoice($choice)
    {
        $this->choice = $choice;
        return $this;
    }

}
