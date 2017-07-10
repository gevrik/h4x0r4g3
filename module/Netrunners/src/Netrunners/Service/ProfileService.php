<?php

/**
 * Profile Service.
 * The service supplies methods that resolve logic around Profile objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SkillRepository;
use Zend\Mvc\I18n\Translator;
use Zend\Validator\EmailAddress;
use Zend\View\Renderer\PhpRenderer;

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

    const SKILL_BLADES_STRING = 'blades';

    const SKILL_CODE_BLADES_STRING = 'bladecoding';

    const SKILL_BLASTERS_STRING = 'blasters';

    const SKILL_CODE_BLASTERS_STRING = 'blastercoding';

    const SKILL_SHIELDS_STRING = 'shields';

    const SKILL_CODE_SHIELDS_STRING = 'shieldcoding';

    const SCORE_CREDITS_STRING = 'credits';

    const SCORE_SNIPPETS_STRING = 'snippets';

    const DEFAULT_STARTING_CREDITS = 750;

    const DEFAULT_STARTING_SNIPPETS = 250;

    const DEFAULT_SKILL_POINTS = 20;

    static $availableLocales = [
        "en_US", "de_DE"
    ];

    /**
     * @var SkillRepository
     */
    protected $skillRepo;

    /**
     * @var SkillRatingRepository
     */
    protected $skillRatingRepo;

    /**
     * @var FilePartInstanceRepository
     */
    protected $filePartInstanceRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * ProfileService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->skillRepo = $this->entityManager->getRepository('Netrunners\Entity\Skill');
        $this->skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        $this->filePartInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartInstance');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showScore($resourceId)
    {
        // init service
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            /** @var Profile $profile */
            $returnMessage = array();
            $returnMessage[] = sprintf(
                '<pre>%-12s: %s</pre>',
                $this->translate(self::SCORE_CREDITS_STRING),
                $profile->getCredits()
            );
            $returnMessage[] = sprintf(
                '<pre>%-12s: %s</pre>',
                $this->translate(self::SCORE_SNIPPETS_STRING),
                $profile->getSnippets()
            );
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showSkills($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $returnMessage = [];
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-20s: %s</pre>',
                $this->translate('skillpoints'),
                $profile->getSkillPoints()
            );
            $skills = $this->skillRepo->findAll();
            foreach ($skills as $skill) {
                /** @var Skill $skill */
                $skillRatingObject = $this->skillRatingRepo->findByProfileAndSkill($profile, $skill);
                $skillRating = $skillRatingObject->getRating();
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-20s: %-7s</pre>',
                    $skill->getName(),
                    $skillRating
                );
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showEquipment($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $messages = [];
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                $this->translate('You are currently using these equipment module files:')
            );
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]</pre>',
                $this->translate('blade'),
                ($profile->getBlade()) ? $profile->getBlade()->getName() : $this->translate('---'),
                $this->translate('level'),
                ($profile->getBlade()) ? $profile->getBlade()->getLevel() : $this->translate('---'),
                $this->translate('integrity'),
                ($profile->getBlade()) ? $profile->getBlade()->getIntegrity() : $this->translate('---'),
                ($profile->getBlade()) ? $profile->getBlade()->getMaxIntegrity() : $this->translate('---')
            );
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]</pre>',
                $this->translate('blaster'),
                ($profile->getBlaster()) ? $profile->getBlaster()->getName() : $this->translate('---'),
                $this->translate('level'),
                ($profile->getBlaster()) ? $profile->getBlaster()->getLevel() : $this->translate('---'),
                $this->translate('integrity'),
                ($profile->getBlaster()) ? $profile->getBlaster()->getIntegrity() : $this->translate('---'),
                ($profile->getBlaster()) ? $profile->getBlaster()->getMaxIntegrity() : $this->translate('---')
            );
            $messages[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]</pre>',
                $this->translate('shield'),
                ($profile->getShield()) ? $profile->getShield()->getName() : $this->translate('---'),
                $this->translate('level'),
                ($profile->getShield()) ? $profile->getShield()->getLevel() : $this->translate('---'),
                $this->translate('integrity'),
                ($profile->getShield()) ? $profile->getShield()->getIntegrity() : $this->translate('---'),
                ($profile->getShield()) ? $profile->getShield()->getMaxIntegrity() : $this->translate('---')
            );
            $this->response = [
                'command' => 'showoutput',
                'message' => $messages
            ];
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $jobs
     * @return array|bool
     */
    public function showJobs($resourceId, $jobs)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $userJobs = [];
        foreach ($jobs as $jobId => $jobData) {
            if ($jobData['socketId'] == $this->clientData->socketId) {
                $userJobs[] = $jobData;
            }
        }
        $returnMessage = array();
        if (empty($userJobs)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('No running jobs')
                )
            );
        }
        else {
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-4s|%-10s|%-20s|%-20s|%s</pre>',
                $this->translate('ID'),
                $this->translate('TYPE'),
                $this->translate('NAME'),
                $this->translate('TIME'),
                $this->translate('DIFFICULTY')
            );
            foreach ($userJobs as $jobId => $jobData) {
                $type = $jobData['type'];
                $typeId = $jobData['typeId'];
                $completionDate = $jobData['completionDate'];
                /** @var \DateTime $completionDate */
                $difficulty = $jobData['difficulty'];
                if ($type == 'program') {
                    $newCode = $this->entityManager->find('Netrunners\Entity\FileType', $typeId);
                }
                else {
                    $newCode = $this->entityManager->find('Netrunners\Entity\FilePart', $typeId);
                }
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-4s|%-10s|%-20s|%-20s|%s</pre>',
                    $jobId,
                    $type,
                    $newCode->getName(),
                    $completionDate->format('y/m/d H:i:s'),
                    $difficulty
                );
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showFilePartInstances($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            /** @var Profile $profile */
            $returnMessage = array();
            $filePartInstances = $this->filePartInstanceRepo->findForPartsCommand($profile);
            if (empty($filePartInstances)) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('You have no file parts')
                    )
                );
            }
            else {
                foreach ($filePartInstances as $data) {
                    // prepare message
                    $returnMessage[] = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-white">%-27s: %-10s level-range: %s-%s</pre>',
                        $data['fpname'],
                        $data['fpicount'],
                        $data['minlevel'],
                        $data['maxlevel']
                    );
                }
                $this->response = array(
                    'command' => 'showoutput',
                    'message' => $returnMessage
                );
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function startStealthing($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            if ($profile->getStealthing()) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('You are already stealthing...')
                    )
                ];
            }
            if (!$this->response) {
                $profile->setStealthing(true);
                $this->entityManager->flush($profile);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('You start stealthing...')
                    )
                ];
            }
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showInventory($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $returnMessage = array();
        $files = $this->fileRepo->findByProfile($profile);
        $returnMessage[] = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-6s|%-20s|%-33s|<span data-toggle="tooltip" data-placement="top" data-original-title="%s">%-3s</span>|%-3s|%-3s|<span data-toggle="tooltip" data-placement="top" data-original-title="%s">%s</span>|<span data-toggle="tooltip" data-placement="top" data-original-title="%s">%s</span>|%-12s|%-32s|%-32s</pre>',
            $this->translate('ID'),
            $this->translate('TYPE'),
            $this->translate('NAME'),
            $this->translate('integrity'),
            $this->translate('INT'),
            $this->translate('LVL'),
            $this->translate('SZE'),
            $this->translate('running'),
            $this->translate('R'),
            $this->translate('slots'),
            $this->translate('S'),
            $this->translate('SUBTYPE'),
            $this->translate('SYSTEM'),
            $this->translate('NODE')
        );
        foreach ($files as $file) {
            /** @var File $file */
            $subtypeString = $this->translate('---');
            $fileData = json_decode($file->getData());
            if ($fileData && $fileData->subtype) {
                switch ($file->getFileType()->getId()) {
                    default:
                        break;
                    case FileType::ID_CODEARMOR:
                        $subtypeString = FileType::$armorSubtypeLookup[$fileData->subtype];
                        break;
                }
            }
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%-6s|%-20s|%-33s|%-3s|%-3s|%-3s|%s|%s|%-12s|%-32s|%-32s</pre>',
                $file->getId(),
                $file->getFileType()->getName(),
                $file->getName(),
                $file->getIntegrity(),
                $file->getLevel(),
                $file->getSize(),
                ($file->getRunning()) ? '<span class="text-success">*</span>' : ' ',
                $file->getSlots(),
                $subtypeString,
                ($file->getSystem()) ? $file->getSystem()->getName() : '',
                ($file->getNode()) ? $file->getNode()->getName() : ''
            );
        }
        $returnMessage[] = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-addon">mem: %s/%s sto: %s/%s</pre>'),
            $this->getUsedMemory($profile),
            $this->getTotalMemory($profile),
            $this->getUsedStorage($profile),
            $this->getTotalStorage($profile)
        );
        $this->response = array(
            'command' => 'showoutput',
            'message' => $returnMessage
        );
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
     */
    public function spendSkillPoints($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId, true);
        $message = [];
        // get skill input name
        list($contentArray, $skillNameParam) = $this->getNextParameter($contentArray);
        // if none given, show a list of all skill input names
        if (!$this->response && !$skillNameParam) {
            $message[] = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">Please specify the skill that you want to improve (%s skillpoints available) :</pre>'),
                $profile->getSkillPoints()
            );
            $skillsString = '';
            foreach ($this->skillRepo->findAll() as $skill) {
                /** @var Skill $skill */
                $skillsString .= $this->getInputNameOfSkill($skill) . ' ';
            }
            $skillsString = wordwrap($skillsString, 120);
            $message[] = sprintf('<pre style="white-space: pre-wrap;" class="text-white">%s</pre>', $skillsString);
            $this->response = [
                'command' => 'showoutput',
                'message' => $message
            ];
        }
        // init target skill
        $targetSkill = NULL;
        // now try to get the actual skill
        if (!$this->response) {
            foreach ($this->skillRepo->findAll() as $skill) {
                /** @var Skill $skill */
                if ($this->getInputNameOfSkill($skill) == $skillNameParam) {
                    $targetSkill = $skill;
                    break;
                }
            }
        }
        if (!$this->response && !$targetSkill) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unknown skill')
                )
            ];
        }
        // get the amount of skillpoints the player wants to invest
        $skillPointAmount = $this->getNextParameter($contentArray, false, true);
        // check if they want to spend at least 1 sp
        if (!$this->response && $skillPointAmount < 1) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify how many skill points you want to invest')
                )
            ];
        }
        // now check if they want to spend more than they have
        if (!$this->response && $skillPointAmount > $profile->getSkillPoints()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You can only spend up to %s skillpoints</pre>'),
                    $profile->getSkillPoints()
                )
            ];
        }
        // now check if the total skill rating would exceed 100
        $skillRatingObject = NULL;
        $skillRating = 0;
        if (!$this->response) {
            $skillRatingObject = $this->skillRatingRepo->findByProfileAndSkill($profile, $targetSkill);
            /** @var SkillRating $skillRatingObject */
            $skillRating = ($skillRatingObject) ? $skillRatingObject->getRating() : 0;
            if ($skillRating + $skillPointAmount > 100) {
                $possible = 100 - $skillRating;
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You can only spend up to %s skillpoints on that skill</pre>'),
                        $possible
                    )
                ];
            }
        }
        /* all checks passed, we can now spend the skillpoints */
        if (!$this->response) {
            $profile->setSkillPoints($profile->getSkillPoints() - $skillPointAmount);
            $skillRatingObject->setRating($skillRating + $skillPointAmount);
            $this->entityManager->flush();
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have raised [%s] to %s by spending %s skillpoints</pre>'),
                    $targetSkill->getName(),
                    $skillRatingObject->getRating(),
                    $skillPointAmount
                )
            ];
        }
        return $this->response;
    }

    /**
     * Shows the profile's faction ratings.
     * @param $resourceId
     * @return array|bool
     */
    public function showFactionRatings($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $factions = $this->entityManager->getRepository('Netrunners\Entity\Faction')->findBy([
                'joinable' => true,
                'playerRun' => false
            ]);
            $returnMessage = array();
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%-11s</pre>',
                $this->translate('FACTION'),
                $this->translate('RATING')
            );
            foreach ($factions as $faction) {
                /** @var Faction $faction */
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-32s|%-11s</pre>',
                    $faction->getName(),
                    $this->getProfileFactionRating($profile, $faction)
                );
            }
            $this->response = array(
                'command' => 'showoutput',
                'message' => $returnMessage
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function setEmail($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $emailParameter = $this->getNextParameter($contentArray, false);
        // if no parameter was give, show their current settings
        if (!$emailParameter) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">your current e-mail address on record: <span class="text-%s">%s</span></pre>'),
                    ($profile->getEmail()) ? 'info' : 'sysmsg',
                    ($profile->getEmail()) ? $profile->getEmail() : $this->translate('no e-mail address set')
                )
            ];
        }
        else {
            // player is trying to set email address
            $validator = new EmailAddress();
            if (!$validator->isValid($emailParameter)) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid e-mail address')
                    )
                ];
            }
            if (!$this->response) {
                $profile->setEmail($emailParameter);
                $this->entityManager->flush($profile);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('E-mail address set')
                    )
                ];
            }
        }
        return $this->response;
    }

    public function setProfileLocale($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $localeParameter = $this->getNextParameter($contentArray, false);
        // if no parameter was give, show their current settings
        if (!$localeParameter) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">your current locale on record: <span class="text-info">%s</span></pre>'),
                    $profile->getLocale()
                )
            ];
        }
        else {
            // player is trying to set locale
            if (!in_array($localeParameter, self::$availableLocales)) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Invalid locale, available locales: <span class="text-muted">%s</span></pre>'),
                        implode(' ', self::$availableLocales)
                    )
                ];
            }
            if (!$this->response) {
                $profile->setLocale($localeParameter);
                $this->entityManager->flush($profile);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('Locale set')
                    )
                ];
            }
        }
        return $this->response;
    }

}
