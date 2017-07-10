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
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;

class SkillRatingRepository extends EntityRepository
{

    /**
     * Find the skill rating for the given profile and skill.
     * @param Profile|NpcInstance $profile
     * @param Skill $skill
     * @return null|object
     */
    public function findByProfileAndSkill($profile, Skill $skill)
    {
        if ($profile instanceof Profile) {
            $result = $this->findOneBy([
                'profile' => $profile,
                'skill' => $skill
            ]);
        }
        else {
            $result = $this->findOneBy([
                'npc' => $profile,
                'skill' => $skill
            ]);
        }
        return $result;
    }

}
