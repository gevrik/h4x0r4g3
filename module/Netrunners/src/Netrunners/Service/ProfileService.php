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
use Netrunners\Entity\Feedback;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Invitation;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileModInstanceRepository;
use Netrunners\Repository\FilePartInstanceRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\InvitationRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SkillRepository;
use Zend\Crypt\Password\Bcrypt;
use Zend\Mvc\I18n\Translator;
use Zend\Validator\EmailAddress;
use Zend\View\Model\ViewModel;
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

    const SCORE_BANK_BALANCE_STRING = 'balance';

    const SCORE_SNIPPETS_STRING = 'snippets';

    const SCORE_SECRATING_STRING = 'secrating';

    const SCORE_STEALTHING_STRING = 'stealthing';

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
     * @var FileModInstanceRepository
     */
    protected $fileModInstanceRepo;

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
        $this->fileModInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\FileModInstance');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
    }

    /**
     * @param $resourceId
     * @return bool|\Netrunners\Model\GameClientResponse
     */
    public function showScore($resourceId)
    {
        // init service
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        /** @var Profile $profile */
        $returnMessage = array();
        $returnMessage[] = sprintf(
            '%-12s: %s',
            $this->translate(self::SCORE_CREDITS_STRING),
            $profile->getCredits()
        );
        $returnMessage[] = sprintf(
            '%-12s: %s',
            $this->translate(self::SCORE_BANK_BALANCE_STRING),
            ($profile->getBankBalance()) ? $profile->getBankBalance() : 0
        );
        $returnMessage[] = sprintf(
            '%-12s: %s',
            $this->translate(self::SCORE_SNIPPETS_STRING),
            $profile->getSnippets()
        );
        $returnMessage[] = sprintf(
            '%-12s: %s',
            $this->translate(self::SCORE_SECRATING_STRING),
            ($profile->getSecurityRating()) ? $profile->getSecurityRating() : 0
        );
        $returnMessage[] = sprintf(
            '%-12s: %s',
            $this->translate(self::SCORE_STEALTHING_STRING),
            ($profile->getStealthing()) ? $this->translate('<span class="text-warning">on</span>') : $this->translate('<span class="text-muted">off</span>')
        );
        $this->gameClientResponse->addMessages($returnMessage);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showSkills($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $headerMessage = sprintf(
            '%-20s: %s',
            $this->translate('SKILLPOINTS'),
            $profile->getSkillPoints()
        );
        $this->gameClientResponse->addMessages($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $returnMessage = [];
        $skills = $this->skillRepo->findAll();
        foreach ($skills as $skill) {
            /** @var Skill $skill */
            $skillRatingObject = $this->skillRatingRepo->findByProfileAndSkill($profile, $skill);
            $skillRating = $skillRatingObject->getRating();
            $returnMessage[] = sprintf(
                '%-20s: %-7s',
                $skill->getName(),
                $skillRating
            );
        }
        $this->gameClientResponse->addMessages($returnMessage);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showEquipment($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
            $this->translate('You are currently using these equipment module files:')
        );
        $this->gameClientResponse->addMessages($message, GameClientResponse::CLASS_SYSMSG);
        $messages = [];
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('blade'),
            ($profile->getBlade()) ? $profile->getBlade()->getName() : $this->translate('---'),
            $this->translate('level'),
            ($profile->getBlade()) ? $profile->getBlade()->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($profile->getBlade()) ? $profile->getBlade()->getIntegrity() : $this->translate('---'),
            ($profile->getBlade()) ? $profile->getBlade()->getMaxIntegrity() : $this->translate('---')
        );
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('blaster'),
            ($profile->getBlaster()) ? $profile->getBlaster()->getName() : $this->translate('---'),
            $this->translate('level'),
            ($profile->getBlaster()) ? $profile->getBlaster()->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($profile->getBlaster()) ? $profile->getBlaster()->getIntegrity() : $this->translate('---'),
            ($profile->getBlaster()) ? $profile->getBlaster()->getMaxIntegrity() : $this->translate('---')
        );
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('shield'),
            ($profile->getShield()) ? $profile->getShield()->getName() : $this->translate('---'),
            $this->translate('level'),
            ($profile->getShield()) ? $profile->getShield()->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($profile->getShield()) ? $profile->getShield()->getIntegrity() : $this->translate('---'),
            ($profile->getShield()) ? $profile->getShield()->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getHeadArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('head'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getShoulderArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('shoulders'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getUpperArmArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('upper-arms'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getLowerArmArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('lower-arms'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getHandArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('hands'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getTorsoArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('torso'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getLegArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('legs'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $armor = $profile->getShoesArmor();
        $messages[] = sprintf(
            '[%-10s] : [%-32s] [%-10s: %-3s] [%-10s: %-3s/%-3s]',
            $this->translate('shoes'),
            ($armor) ? $armor->getName() : $this->translate('---'),
            $this->translate('level'),
            ($armor) ? $armor->getLevel() : $this->translate('---'),
            $this->translate('integrity'),
            ($armor) ? $armor->getIntegrity() : $this->translate('---'),
            ($armor) ? $armor->getMaxIntegrity() : $this->translate('---')
        );
        $this->gameClientResponse->addMessages($messages);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is checking out their equipment'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $jobs
     * @return bool|GameClientResponse
     */
    public function showJobs($resourceId, $jobs)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $userJobs = [];
        foreach ($jobs as $jobId => $jobData) {
            if ($jobData['socketId'] == $this->clientData->socketId) {
                $userJobs[] = $jobData;
            }
        }
        $returnMessage = [];
        if (empty($userJobs)) {
            $this->gameClientResponse->addMessage($this->translate('No running jobs'));
        }
        else {
            $headerMessage = sprintf(
                '%-4s|%-10s|%-32|%-20s|%s',
                $this->translate('ID'),
                $this->translate('TYPE'),
                $this->translate('NAME'),
                $this->translate('TIME'),
                $this->translate('DIFFICULTY')
            );
            $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
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
                    '%-4s|%-10s|%-32|%-20s|%s',
                    $jobId,
                    $type,
                    $newCode->getName(),
                    $completionDate->format('y/m/d H:i:s'),
                    $difficulty
                );
            }
            $this->gameClientResponse->addMessages($returnMessage);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showFileModInstances($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        /** @var Profile $profile */
        $returnMessage = [];
        $fileModInstances = $this->fileModInstanceRepo->findForPartsCommand($profile);
        if (empty($fileModInstances)) {
            $this->gameClientResponse->addMessage($this->translate('You have no unused file mods'));
        }
        else {
            foreach ($fileModInstances as $data) {
                // prepare message
                $returnMessage[] = sprintf(
                    '%-27s: %-10s level-range: %s-%s',
                    $data['fmname'],
                    $data['fmicount'],
                    $data['minlevel'],
                    $data['maxlevel']
                );
            }
            $this->gameClientResponse->addMessages($returnMessage);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showFilePartInstances($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        /** @var Profile $profile */
        $returnMessage = [];
        $filePartInstances = $this->filePartInstanceRepo->findForPartsCommand($profile);
        if (empty($filePartInstances)) {
            $this->gameClientResponse->addMessage($this->translate('You have no file parts'));
        }
        else {
            foreach ($filePartInstances as $data) {
                // prepare message
                $returnMessage[] = sprintf(
                    '%-27s: %-10s level-range: %s-%s',
                    $data['fpname'],
                    $data['fpicount'],
                    $data['minlevel'],
                    $data['maxlevel']
                );
            }
            $this->gameClientResponse->addMessages($returnMessage);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function startStealthing($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        if ($profile->getStealthing()) {
            $message = $this->translate('You are already stealthing...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $profile->setStealthing(true);
        $this->entityManager->flush($profile);
        $message = $this->translate('You start stealthing...');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $xmessage = sprintf(
            $this->translate('[%s] starts stealthing'),
            $profile->getUser()->getDisplayName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $xmessage, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function stopStealthing($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        if (!$profile->getStealthing()) {
            $message = $this->translate('You are not stealthing...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $profile->setStealthing(false);
        $this->entityManager->flush($profile);
        $this->gameClientResponse->addMessage($this->translate('You stop stealthing...'), GameClientResponse::CLASS_SUCCESS);
        $xmessage = sprintf(
            $this->translate('[%s] stops stealthing'),
            $profile->getUser()->getDisplayName()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $xmessage, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showInventory($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $returnMessage = [];
        $files = $this->fileRepo->findByProfile($profile);
        $headerMessage = sprintf(
            '%-6s|%-32s|%-33s|<span data-toggle="tooltip" data-placement="top" data-original-title="%s">%-3s</span>|%-3s|%-3s|<span data-toggle="tooltip" data-placement="top" data-original-title="%s">%s</span>|<span data-toggle="tooltip" data-placement="top" data-original-title="%s">%s</span>|%-12s|%-32s|%-32s',
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
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($files as $file) {
            /** @var File $file */
            $subtypeString = $this->translate('---');
            $fileData = json_decode($file->getData());
            if ($fileData && isset($fileData->subtype)) {
                switch ($file->getFileType()->getId()) {
                    default:
                        break;
                    case FileType::ID_CODEARMOR:
                        $subtypeString = FileType::$armorSubtypeLookup[$fileData->subtype];
                        break;
                }
            }
            $returnMessage[] = sprintf(
                '%-6s|%-32s|%-33s|%-3s|%-3s|%-3s|%s|%s|%-12s|%-32s|%-32s',
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
        $this->gameClientResponse->addMessages($returnMessage);
        $addonMessage = sprintf(
            $this->translate('mem: %s/%s sto: %s/%s'),
            $this->getUsedMemory($profile),
            $this->getTotalMemory($profile),
            $this->getUsedStorage($profile),
            $this->getTotalStorage($profile)
        );
        $this->gameClientResponse->addMessage($addonMessage, GameClientResponse::CLASS_ADDON);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showInvitations($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $returnMessage = [];
        $invitationRepo = $this->entityManager->getRepository('Netrunners\Entity\Invitation');
        /** @var InvitationRepository $invitationRepo */
        $invitations = $invitationRepo->findAllByProfile($profile);
        $headerMessage = sprintf(
            '%-19s|%-32s|%-19s|%s',
            $this->translate('GIVEN-DATE'),
            $this->translate('USED-BY'),
            $this->translate('USED-DATE'),
            $this->translate('CODE')
        );
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $totalInvitations = 0;
        $unusedInvitations = 0;
        $usedInvitations = 0;
        foreach ($invitations as $invitation) {
            /** @var Invitation $invitation */
            $totalInvitations++;
            if ($invitation->getUsed()) {
                $usedInvitations++;
                $usedByString = $invitation->getUsedBy()->getUser()->getUsername();
                $usedString = $invitation->getUsed()->format('Y/m/d H:i:s');
            }
            else {
                $unusedInvitations++;
                $usedByString = '---';
                $usedString = '---';
            }
            $returnMessage[] = sprintf(
                '%-19s|%-32s|%-19s|%s',
                $invitation->getGiven()->format('Y/m/d H:i:s'),
                $usedByString,
                $usedString,
                $invitation->getCode()
            );
        }
        $this->gameClientResponse->addMessages($returnMessage);
        $addonMessage = sprintf(
            $this->translate('You have used %s of %s invitations (%s available)'),
            $usedInvitations,
            $totalInvitations,
            $unusedInvitations
        );
        $this->gameClientResponse->addMessage($addonMessage, GameClientResponse::CLASS_ADDON);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function spendSkillPoints($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // get skill input name
        list($contentArray, $skillNameParam) = $this->getNextParameter($contentArray);
        // if none given, show a list of all skill input names
        if (!$skillNameParam) {
            $message = sprintf(
                $this->translate('Please specify the skill that you want to improve (%s skillpoints available) :'),
                $profile->getSkillPoints()
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
            $skillsString = '';
            foreach ($this->skillRepo->findAll() as $skill) {
                /** @var Skill $skill */
                $skillsString .= $this->getInputNameOfSkill($skill) . ' ';
            }
            $skillsString = wordwrap($skillsString, 120);
            $this->gameClientResponse->addMessage($skillsString, GameClientResponse::CLASS_WHITE);
            return $this->gameClientResponse->send();
        }
        // init target skill
        $targetSkill = NULL;
        // now try to get the actual skill
        foreach ($this->skillRepo->findAll() as $skill) {
            /** @var Skill $skill */
            if ($this->getInputNameOfSkill($skill) == $skillNameParam) {
                $targetSkill = $skill;
                break;
            }
        }
        if (!$targetSkill) {
            return $this->gameClientResponse->addMessage($this->translate('Unknown skill'))->send();
        }
        // we got a skill now if there is no response yet - check if the are advanced skills
        if ($targetSkill->getId() == Skill::ID_ADVANCED_CODING || $targetSkill->getId() == Skill::ID_ADVANCED_NETWORKING) {
            $message = $this->translate('Advanced skills can only be improved by practicing them');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // get the amount of skillpoints the player wants to invest
        $skillPointAmount = $this->getNextParameter($contentArray, false, true);
        // check if they want to spend at least 1 sp
        if ($skillPointAmount < 1) {
            $message = $this->translate('Please specify how many skill points you want to invest');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now check if they want to spend more than they have
        if ($skillPointAmount > $profile->getSkillPoints()) {
            $message = sprintf(
                $this->translate('You can only spend up to %s skillpoints'),
                $profile->getSkillPoints()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now check if the total skill rating would exceed 100
        $skillRatingObject = NULL;
        $skillRatingObject = $this->skillRatingRepo->findByProfileAndSkill($profile, $targetSkill);
        /** @var SkillRating $skillRatingObject */
        $skillRating = ($skillRatingObject) ? $skillRatingObject->getRating() : 0;
        if ($skillRating + $skillPointAmount > 100) {
            $possible = 100 - $skillRating;
            $message = sprintf(
                $this->translate('You can only spend up to %s skillpoints on that skill'),
                $possible
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all checks passed, we can now spend the skillpoints */
        $skillPointAmount = $this->checkValueMinMax($skillPointAmount, 1, NULL);
        $profile->setSkillPoints($profile->getSkillPoints() - $skillPointAmount);
        $skillRatingObject->setRating($skillRating + $skillPointAmount);
        $this->entityManager->flush();
        $message = [
            'command' => 'showmessage',
            'message' => sprintf(
                $this->translate('You have raised [%s] to %s by spending %s skillpoints'),
                $targetSkill->getName(),
                $skillRatingObject->getRating(),
                $skillPointAmount
            )
        ];
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showFactionRatings($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $factions = $this->entityManager->getRepository('Netrunners\Entity\Faction')->findBy([
            'joinable' => true,
            'playerRun' => false
        ]);
        $returnMessage = [];
        $headerMessage = sprintf(
            '%-32s|%-11s',
            $this->translate('FACTION'),
            $this->translate('RATING')
        );
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($factions as $faction) {
            /** @var Faction $faction */
            $returnMessage[] = sprintf(
                '%-32s|%-11s',
                $faction->getName(),
                $this->getProfileFactionRating($profile, $faction)
            );
        }
        $this->gameClientResponse->addMessages($returnMessage);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function setEmail($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $emailParameter = $this->getNextParameter($contentArray, false);
        // if no parameter was give, show their current settings
        if (!$emailParameter) {
            $message = sprintf(
                $this->translate('your current e-mail address on record: <span class="text-%s">%s</span>'),
                ($profile->getEmail()) ? 'info' : 'sysmsg',
                ($profile->getEmail()) ? $profile->getEmail() : $this->translate('no e-mail address set')
            );
            return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE)->send();
        }
        else {
            // player is trying to set email address
            $validator = new EmailAddress();
            if (!$validator->isValid($emailParameter)) {
                return $this->gameClientResponse->addMessage($this->translate('Invalid e-mail address'))->send();
            }
            $profile->setEmail($emailParameter);
            $this->entityManager->flush($profile);
            $this->gameClientResponse->addMessages($this->translate('E-mail address set'), GameClientResponse::CLASS_SUCCESS);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function changePassword($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $newPassword = $this->getNextParameter($contentArray, false);
        // ask them to supply a new password
        if (!$newPassword) {
            $message = $this->translate('Please specify a new password (8-char-min, 30-char-max, alpha-numeric only)');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        else {
            $checkResult = $this->stringChecker($newPassword, 30, 8);
            if ($checkResult) {
                return $this->gameClientResponse->addMessage($checkResult)->send();
            }
            $bcrypt = new Bcrypt();
            $bcrypt->setCost(10);
            $pass = $bcrypt->create($newPassword);
            $this->user->setPassword($pass);
            $this->entityManager->flush($this->user);
            $message = sprintf(
                $this->translate('Password set to: %s'),
                $newPassword
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function showBankBalance($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $message = [];
        $message[] = sprintf(
            $this->translate('Your current credits: %s'),
            $profile->getCredits()
        );
        $message[] = sprintf(
            $this->translate('Your current bank balance in credits: %s'),
            $profile->getBankBalance()
        );
        $this->gameClientResponse->addMessages($message, GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function depositCredits($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // check if they are in a banking node
        if ($currentNode->getNodeType()->getId() != NodeType::ID_BANK) {
            $message = $this->translate('You need to be in a banking node to deposit credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        // check if an amount was given
        if (!$amount) {
            $message = $this->translate('Please specify how much you want to deposit');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they have that much
        if ($profile->getCredits() < $amount) {
            $message = $this->translate('You do not have that many credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if valid amount
        if ($amount <= 0) {
            $message = $this->translate('Nice try');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all seems good, deposit */
        // check for skimmer
        $skimmerFiles = $this->fileRepo->findRunningInNodeByType($currentNode, FileType::ID_SKIMMER);
        $remainingAmount = $amount;
        $triggerData = ['value' => $remainingAmount];
        foreach ($skimmerFiles as $skimmerFile) {
            /** @var File $skimmerFile */
            $skimAmount = $this->checkFileTriggers($skimmerFile, $triggerData);
            if ($skimAmount === false) continue;
            $remainingAmount -= $skimAmount;
            $triggerData['value'] = $remainingAmount;
        }
        // now add/substract
        $profile->setCredits($profile->getCredits() - $amount);
        $profile->setBankBalance($profile->getBankBalance() + $remainingAmount);
        $this->entityManager->flush($profile);
        $message = sprintf(
            $this->translate('You have deposited %s credits into your bank account'),
            $amount
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has deposited some credits'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param bool $messageSocket
     * @param bool $asActiveCommand
     * @return array|bool
     */
    public function cancelCurrentAction($resourceId, $messageSocket = false, $asActiveCommand = false)
    {
        return $this->cancelAction($resourceId, $messageSocket, $asActiveCommand);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function changeBackgroundOpacity($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $newOpacity = $this->getNextParameter($contentArray, false, false, false, true);
        if ($newOpacity === NULL) {
            $newOpacity = 0.6;
        }
        if ($newOpacity < 0) {
            $newOpacity = 0;
        }
        if ($newOpacity > 1) {
            $newOpacity = 1;
        }
        $profile->setBgopacity($newOpacity);
        $this->entityManager->flush($profile);
        $message = $this->translate('Background opacity set to: %s');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $clientResponse = new GameClientResponse($resourceId);
        $clientResponse
            ->setSilent(true)
            ->setCommand(GameClientResponse::COMMAND_SETOPACITY)
            ->addOption(GameClientResponse::OPT_CONTENT, $newOpacity)
            ->send();
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function withdrawCredits($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        // check if they are in a banking node
        if ($currentNode->getNodeType()->getId() != NodeType::ID_BANK) {
            $message = $this->translate('You need to be in a banking node to withdraw credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $amount = $this->getNextParameter($contentArray, false, true);
        // check if an amount was given
        if (!$amount) {
            $message = $this->translate('Please specify how much you want to withdraw');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they have that much
        if ($profile->getBankBalance() < $amount) {
            $message = $this->translate('You do not have that many credits in your bank account');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if valid amount
        if ($amount <= 0) {
            $message = $this->translate('Nice try...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /* all seems good, withdraw */
        $profile->setCredits($profile->getCredits() + $amount);
        $profile->setBankBalance($profile->getBankBalance() - $amount);
        $this->entityManager->flush($profile);
        $message = sprintf(
            $this->translate('You have withdrawn %s credits from your bank account'),
            $amount
        );
        $this->gameClientResponse->addMessage($message);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has withdrawn some credits'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     */
    public function setProfileLocale($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $localeParameter = $this->getNextParameter($contentArray, false);
        // if no parameter was give, show their current settings
        if (!$localeParameter) {
            $message = sprintf(
                $this->translate('your current locale on record: <span class="text-info">%s</span>'),
                $profile->getLocale()
            );
            return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE)->send();
        }
        else {
            // player is trying to set locale
            if (!in_array($localeParameter, self::$availableLocales)) {
                $message = sprintf(
                    $this->translate('Invalid locale, available locales: <span class="text-muted">%s</span>'),
                    implode(' ', self::$availableLocales)
                );
                return $this->gameClientResponse->addMessage($message)->send();
            }
            $profile->setLocale($localeParameter);
            $this->entityManager->flush($profile);
            $this->gameClientResponse->addMessage($this->translate('Locale set'), GameClientResponse::CLASS_SUCCESS);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param int $type
     * @return bool|GameClientResponse
     */
    public function openSubmitFeedbackPanel($resourceId, $type = Feedback::TYPE_TYPO_ID)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/feedback/feedback-form.phtml');
        $view->setVariable('typeid', $type);
        $view->setVariable('typestring', Feedback::$lookup[$type]);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     */
    public function logoutCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $message = $this->translate('Disconnecting from NeoCortex Network - have a nice day and see you soon');
        $this->gameClientResponse->addOption(GameClientResponse::OPT_DISCONNECTX, true);
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_INFO);
        return $this->gameClientResponse->send();
    }

}
