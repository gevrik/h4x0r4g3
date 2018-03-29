<?php

/**
 * Welcome Page Service.
 * Welcome Page Service.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Service;

use Doctrine\ORM\EntityManager;
use TwistyPassages\Entity\Story;

class WelcomeService extends TwistyPassagesAbstractService
{

    const WELCOME_STORY_AMOUNT = 9;


    /**
     * WelcomeService constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(
        EntityManager $entityManager
    )
    {
        parent::__construct($entityManager);
    }

    /**
     * @return array
     */
    public function getForTopList(): array
    {
        $qb = $this->entityManager->getRepository('TwistyPassages\Entity\Story')->createQueryBuilder('s');
        $qb->select('s.id, s.title, s.description, s.added, a.id as user_id, a.username as author');
        $qb->leftJoin('s.author', 'a');
        $qb->where($qb->expr()->gte('s.status', StoryService::STATUS_APPROVED));
        $qb->orderBy('s.id', 'ASC');
        $qb->setMaxResults(self::WELCOME_STORY_AMOUNT);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param int $id
     * @return null|object
     */
    public function findStory(int $id)
    {
        return $this->entityManager->getRepository('TwistyPassages\Entity\Story')->find($id);
    }

}
