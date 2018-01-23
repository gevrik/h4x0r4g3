<?php

/**
 * Story Custom Repository.
 * Story Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace TwistyPassages\Repository;

use Doctrine\ORM\EntityRepository;
use TwistyPassages\Service\StoryService;

class StoryRepository extends EntityRepository
{

    const TOP_STORY_AMOUNT = 9;

    /**
     * @return array
     */
    public function findForTopList()
    {
        $qb = $this->createQueryBuilder('s');
        $qb->select('s.id, s.title, s.description, s.added, a.id as user_id, a.username as author');
        $qb->leftJoin('s.author', 'a');
        $qb->where($qb->expr()->gte('s.status', StoryService::STATUS_APPROVED));
        $qb->orderBy('s.id', 'ASC');
        $qb->setMaxResults(self::TOP_STORY_AMOUNT);
        return $qb->getQuery()->getResult();
    }

}
