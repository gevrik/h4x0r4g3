<?php

/**
 * SkillRating Custom Repository.
 * SkillRating Custom Repository.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Repository;

use Doctrine\ORM\EntityRepository;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;

class SkillRatingRepository extends EntityRepository
{

    /**
     * Find the skill rating for the given profile and skill.
     * @param Profile $profile
     * @param Skill $skill
     * @return null|object
     */
    public function findByProfileAndSkill(Profile $profile, Skill $skill)
    {
        return $this->findOneBy([
            'profile' => $profile,
            'skill' => $skill
        ]);
    }

}
