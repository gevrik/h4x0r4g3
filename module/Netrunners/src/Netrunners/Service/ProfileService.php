<?php

/**
 * Profile Service.
 * The service supplies methods that resolve logic around Profile objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Profile;
use TmoAuth\Entity\User;

class ProfileService extends BaseService
{

    const SKILL_CODING_STRING = 'coding';

    const SKILL_BLACKHAT_STRING = 'blackhat';

    const SKILL_WHITEHAT_STRING = 'whitehat';

    const SKILL_NETWORKING_STRING = 'networking';

    const SKILL_COMPUTING_STRING = 'computing';

    const SKILL_DATABASES_STRING = 'databases';

    const SKILL_ELECTRONICS_STRING = 'electronics';

    const SKILL_FORENSICS_STRING = 'forensics';

    const SKILL_SOCIAL_ENGINEERING_STRING = 'social engineering';

    const SKILL_CRYPTOGRAPHY_STRING = 'cryptography';

    const SKILL_REVERSE_ENGINEERING_STRING = 'reverse engineering';

    const SKILL_ADVANCED_NETWORKING_STRING = 'advanced networking';

    const SKILL_ADVANCED_CODING_STRING = 'advanced coding';

    const SCORE_CREDITS_STRING = 'credits';

    const SCORE_SNIPPETS_STRING = 'snippets';

    const DEFAULT_STARTING_CREDITS = 1000;

    const DEFAULT_STARTING_SNIPPETS = 100;

    const DEFAULT_SKILL_POINTS = 20;


    public function showScore($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::SCORE_CREDITS_STRING, $profile->getCredits());
        $returnMessage[] = sprintf('<pre>%-12s: %s</pre>', self::SCORE_SNIPPETS_STRING, $profile->getSnippets());
        $response = array(
            'command' => 'score',
            'message' => $returnMessage
        );
        return $response;
    }

    public function showSkills($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $returnMessage[] = sprintf('<pre>%-20s: %-7s%-20s: %s</pre>', self::SKILL_CODING_STRING, $profile->getSkillCoding(), self::SKILL_COMPUTING_STRING, $profile->getSkillComputing());
        $returnMessage[] = sprintf('<pre>%-20s: %-7s%-20s: %s</pre>', self::SKILL_BLACKHAT_STRING, $profile->getSkillBlackhat(), self::SKILL_WHITEHAT_STRING, $profile->getSkillWhitehat());
        $returnMessage[] = sprintf('<pre>%-20s: %-7s%-20s: %s</pre>', self::SKILL_CRYPTOGRAPHY_STRING, $profile->getSkillCryptography(), self::SKILL_DATABASES_STRING, $profile->getSkillDatabases());
        $returnMessage[] = sprintf('<pre>%-20s: %-7s%-20s: %s</pre>', self::SKILL_ELECTRONICS_STRING, $profile->getSkillElectronics(), self::SKILL_FORENSICS_STRING, $profile->getSkillForensics());
        $returnMessage[] = sprintf('<pre>%-20s: %-7s%-20s: %s</pre>', self::SKILL_REVERSE_ENGINEERING_STRING, $profile->getSkillReverseEngineering(), self::SKILL_SOCIAL_ENGINEERING_STRING, $profile->getSkillSocialEngineering());
        $returnMessage[] = sprintf('<pre>%-20s: %-7s%-20s: %s</pre>', self::SKILL_ADVANCED_CODING_STRING, $profile->getSkillAdvancedCoding(), self::SKILL_ADVANCED_NETWORKING_STRING, $profile->getSkillAdvancedNetworking());
        $returnMessage[] = sprintf('<pre>%-20s: %-7s</pre>', self::SKILL_NETWORKING_STRING, $profile->getSkillNetworking());
        $response = array(
            'command' => 'skills',
            'message' => $returnMessage
        );
        return $response;
    }

}
