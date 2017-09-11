<?php

/**
 * Base Service.
 * The service supplies a base for all complex services.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Application\Service\WebsocketService;
use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FilePart;
use Netrunners\Entity\FilePartSkill;
use Netrunners\Entity\FileType;
use Netrunners\Entity\FileTypeSkill;
use Netrunners\Entity\GameOptionInstance;
use Netrunners\Entity\Group;
use Netrunners\Entity\Invitation;
use Netrunners\Entity\KnownNode;
use Netrunners\Entity\MilkrunInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Npc;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Entity\ProfileFactionRating;
use Netrunners\Entity\ServerSetting;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Entity\SystemLog;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\FilePartSkillRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeSkillRepository;
use Netrunners\Repository\KnownNodeRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\ProfileFactionRatingRepository;
use Netrunners\Repository\ProfileRepository;
use Netrunners\Repository\SkillRatingRepository;
use Netrunners\Repository\SystemRepository;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\I18n\Validator\Alnum;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class BaseService
{

    const SETTING_MOTD = 'motd';
    const SETTING_CHATSUBO_SYSTEM_ID = 'csid';
    const SETTING_CHATSUBO_NODE_ID = 'cnid';
    const SETTING_WILDERNESS_SYSTEM_ID = 'wsid';
    const SETTING_WILDERNESS_NODE_ID = 'wnid';
    const VALUE_TYPE_CODINGNODELEVELS = 'codingnodelevels';
    const VALUE_TYPE_MEMORYLEVELS = 'memorylevels';
    const VALUE_TYPE_STORAGELEVELS = 'storagelevels';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var PhpRenderer
     */
    protected $viewRenderer;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var string
     */
    protected $profileLocale = 'en_US';

    /**
     * @var object
     */
    protected $clientData;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var array|false
     */
    protected $response = false;


    /**
     * BaseService constructor.
     * @param EntityManager $entityManager
     * @param $viewRenderer
     * @param $translator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator
    )
    {
        $this->entityManager = $entityManager;
        $this->viewRenderer = $viewRenderer;
        $this->translator = $translator;
    }

    /**
     * @param $string
     * @return string
     */
    protected function translate($string)
    {
        $this->translator->getTranslator()->setLocale($this->profileLocale);
        return $this->translator->translate($string);
    }

    /**
     * @return WebsocketService
     */
    protected function getWebsocketServer()
    {
        return WebsocketService::getInstance();
    }

    /**
     * @param $resourceId
     */
    protected function initService($resourceId)
    {
        $this->setClientData($resourceId);
        $this->setUser();
        $this->setProfileLocale();
        $this->setResponse(false);
    }

    /**
     * @param $resourceId
     */
    private function setClientData($resourceId)
    {
        $this->clientData = $this->getWebsocketServer()->getClientData($resourceId);
    }

    /**
     * Sets the user from the client data.
     * @param User|NULL $user
     */
    protected function setUser(User $user = NULL)
    {
        $this->user = ($user) ? $user : $this->entityManager->find('TmoAuth\Entity\User', $this->clientData->userId);
    }

    private function setProfileLocale()
    {
        if ($this->user && $this->user->getProfile()) $this->profileLocale = $this->user->getProfile()->getLocale();
    }

    /**
     * @param $response
     */
    protected function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * Checks if the given profile can execute the given file.
     * Returns true if the file can be executed.
     * @param Profile $profile
     * @param File $file
     * @return bool
     */
    protected function canExecuteFile(Profile $profile, File $file)
    {
        $result = false;
        if ($file->getSize() + $this->getUsedMemory($profile) <= $this->getTotalMemory($profile)) {
            $result = true;
        }
        return $result;
    }

    /**
     * Checks if the given profile can store the given file.
     * Returns true if the file can be stored.
     * @param Profile $profile
     * @param File $file
     * @return bool
     */
    protected function canStoreFile(Profile $profile, File $file)
    {
        return ($file->getSize() + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? true : false;
    }

    /**
     * Checks if the given profile can store the given file size.
     * Returns true if the file can be stored.
     * @param Profile $profile
     * @param int $size
     * @return bool
     */
    protected function canStoreFileOfSize(Profile $profile, $size = 0)
    {
        return ($size + $this->getUsedStorage($profile) <= $this->getTotalStorage($profile)) ? true : false;
    }

    /**
     * Get the given system's memory value.
     * @param System $system
     * @return int
     */
    protected function getSystemMemory(System $system)
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_MEMORY);
        $total = 0;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $total += $node->getLevel() * SystemService::BASE_MEMORY_VALUE;
        }
        return $total;
    }

    protected function updateDivHtml(Profile $profile, $element, $content, $adds = [], $sendNow = false)
    {
        $response = [
            'command' => 'updatedivhtml',
            'content' => (string)$content,
            'element' => $element
        ];
        if (!empty($adds)) {
            foreach ($adds as $key => $value) {
                $response[$key] = $value;
            }
        }
        if ($sendNow) {
            $wsClient = $this->getWsClientByProfile($profile);
            $wsClient->send(json_encode($response));
        }
        else {
            $this->response = $response;
        }
    }

    /**
     * Get the given system's storage value.
     * @param System $system
     * @return int
     */
    protected function getSystemStorage(System $system)
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_STORAGE);
        $total = 0;
        foreach ($nodes as $node) {
            /** @var Node $node */
            $total += $node->getLevel() * SystemService::BASE_STORAGE_VALUE;
        }
        return $total;
    }

    /**
     * Get the given profile's total memory.
     * This is calculated from all systems that the profile owns.
     * @param Profile $profile
     * @return int
     */
    protected function getTotalMemory(Profile $profile)
    {
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systems = $systemRepo->findByProfile($profile);
        $total = 0;
        foreach ($systems as $system) {
            /** @var System $system */
            $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_MEMORY);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $total += $node->getLevel() * SystemService::BASE_MEMORY_VALUE;
            }
        }
        return $total;
    }

    /**
     * Get the given profile's total storage.
     * This is calculated from all systems that the profile owns.
     * @param Profile $profile
     * @return int
     */
    protected function getTotalStorage(Profile $profile)
    {
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $systems = $systemRepo->findByProfile($profile);
        $total = 0;
        foreach ($systems as $system) {
            /** @var System $system */
            $nodes = $nodeRepo->findBySystemAndType($system, NodeType::ID_STORAGE);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $total += $node->getLevel() * SystemService::BASE_STORAGE_VALUE;
            }
        }
        return $total;
    }

    /**
     * @param Profile|NpcInstance|File $detector
     * @param Profile|NpcInstance|File $stealther
     * @return bool
     */
    protected function canSee($detector, $stealther)
    {
        // init vars
        $canSee = true;
        $detectorSkillRating = 0;
        $stealtherSkillRating = 0;
        $stealthing = false;
        $stealtherName = '';
        $detectorName = '';
        $currentNode = NULL;
        // get values depending on instance
        if ($stealther instanceof Profile) {
            $stealthing = $stealther->getStealthing();
            if (!$stealthing) return $canSee;
            $currentNode = $stealther->getCurrentNode();
            $stealtherName = $stealther->getUser()->getUsername();
            $stealtherSkillRating = $this->getSkillRating($stealther, SKill::ID_STEALTH);
            $detectorSkillRating = $this->getSkillRating($detector, SKill::ID_DETECTION);
            // TODO add programs that modify ratings (cloak)
        }
        if ($stealther instanceof NpcInstance) {
            $stealthing = $stealther->getStealthing();
            if (!$stealthing) return $canSee;
            $currentNode = $stealther->getNode();
            $stealtherName = $stealther->getName();
            $stealtherSkillRating = $this->getSkillRating($stealther, SKill::ID_STEALTH);
            $detectorSkillRating = $this->getSkillRating($detector, SKill::ID_DETECTION);
            // if detector is owner then they can always see their instances
            if ($detector instanceof Profile) {
                if ($detector == $stealther->getProfile()) $stealthing = false;
            }
            // TODO add programs that modify ratings
        }
        if ($stealther instanceof File) {
            $stealthing = $stealther->getFileType()->getStealthing();
            if (!$stealthing) return $canSee;
            $currentNode = $stealther->getNode();
            $stealtherName = $stealther->getName();
            $skillRating = ceil(($stealther->getIntegrity() + $stealther->getLevel()) / 2);
            $stealtherSkillRating = $skillRating;
            $detectorSkillRating = $skillRating;
            // if detector is owner then they can always see their files
            if ($detector instanceof Profile) {
                if ($detector == $stealther->getProfile()) $stealthing = false;
            }
            // TODO add mods that modify ratings
        }
        if ($detector instanceof Profile) {
            $detectorName = ($detector->getStealthing()) ? 'something' : $detector->getUser()->getUsername();
        }
        if ($detector instanceof NpcInstance) {
            $detectorName = ($detector->getStealthing()) ? 'something' : $detector->getName();
        }
        if ($detector instanceof File) {
            $detectorName = ($detector->getFileType()->getStealthing()) ? 'something' : $detector->getName();
        }
        // only check if they are actively stealthing
        if ($stealthing) {
            $chance = 50 + $detectorSkillRating - $stealtherSkillRating;
            if (mt_rand(1, 100) > $chance) $canSee = false;
            // check for skill gain
            if ($canSee) {
                if ($detector instanceof Profile) $this->learnFromSuccess($detector, ['skills' => ['detection']], -50);
                if ($stealther instanceof Profile) {
                    $this->learnFromFailure($stealther, ['skills' => ['stealth']], -50);
                    $stealther->setStealthing(false);
                    $this->entityManager->flush($stealther);
                }
                // message everyone in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-attention">[%s] has detected [%s]</pre>'),
                    $detectorName,
                    $stealtherName
                );
                $this->messageEveryoneInNode($currentNode, $message);
            }
            else {
                if ($detector instanceof Profile) $this->learnFromFailure($detector, ['skills' => ['detection']], -50);
                if ($stealther instanceof Profile) $this->learnFromSuccess($stealther, ['skills' => ['stealth']], -50);
            }
        }
        // return result
        return $canSee;
    }

    /**
     * Get the amount of used memory for the given profile.
     * @param Profile $profile
     * @return int
     */
    protected function getUsedMemory(Profile $profile)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $amount = 0;
        $files = $fileRepo->findByProfile($profile);
        foreach ($files as $file) {
            /** @var File $file */
            if ($file->getRunning()) $amount += $file->getSize();
        }
        return $amount;
    }

    /**
     * Get the amount of used storage for the given profile.
     * @param Profile $profile
     * @return int
     */
    protected function getUsedStorage(Profile $profile)
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $amount = 0;
        $files = $fileRepo->findByProfile($profile);
        foreach ($files as $file) {
            /** @var File $file */
            $amount += $file->getSize();
        }
        return $amount;
    }

    /**
     * @param Profile $profile
     * @param $jobData
     * @param int $modifier
     * @return bool
     */
    protected function learnFromSuccess(Profile $profile, $jobData, $modifier = 0)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $this->reverseSkillNameModification($skillName)
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill->getId());
            // return true if already at max
            if ($skillRating > 99) return true;
            // calculate chance
            $chance = 100 - $skillRating + $modifier;
            // return true if chance smaller than one
            if ($chance < 1) return true;
            // roll
            if (mt_rand(1, 100) <= $chance) {
                // calc new skill-rating, set and message
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-attention">You have gained a level in %s</pre>'),
                    $skill->getName()
                );
                $this->messageProfile($profile, $message);
                // check if the should receive skillpoints for reaching a milestone
                if ($newSkillRating%10 == 0) {
                    // skillrating divisible by 10, gain skillpoints
                    $this->gainSkillpoints($profile, floor(round($newSkillRating / 10)));
                    // check if they just mastered the skill and give them an invitation as a reward
                    if ($newSkillRating >= 100) {
                        $this->gainInvitation($profile);
                    }
                }
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param null|int $special
     */
    protected function gainInvitation(Profile $profile, $special = NULL)
    {
        $given = new \DateTime();
        $code = md5($given->format('Y/m/d-H:i:s') . '-' . $profile->getId());
        $invitation = new Invitation();
        $invitation->setCode($code);
        $invitation->setGiven($given);
        $invitation->setUsed(NULL);
        $invitation->setGivenTo($profile);
        $invitation->setUsedBy(NULL);
        $invitation->setSpecial($special);
        $this->entityManager->persist($invitation);
        $this->entityManager->flush($invitation);
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-attention">%s</pre>',
            ($special) ?
                $this->translate('You have gained a special invitation (see "invitations" for a list)') :
                $this->translate('You have gained an invitation (see "invitations" for a list)')
        );
        $this->messageProfile($profile, $message);
    }

    /**
     * Players can learn from failure, but not a lot.
     * @param Profile $profile
     * @param $jobData
     * @param int $modifier
     * @return bool
     */
    protected function learnFromFailure(Profile $profile, $jobData, $modifier = 0)
    {
        foreach ($jobData['skills'] as $skillName) {
            $skill = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findOneBy([
                'name' => $this->reverseSkillNameModification($skillName)
            ]);
            /** @var Skill $skill */
            $skillRating = $this->getSkillRating($profile, $skill->getId());
            if ($skillRating >= SkillRating::MAX_SKILL_RATING_FAIL_LEARN) continue;
            $chance = 100 - $skillRating + $modifier;
            if ($chance < 1) return true;
            if (mt_rand(1, 100) <= $chance) {
                $newSkillRating = $skillRating + 1;
                $this->setSkillRating($profile, $skill, $newSkillRating);
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-addon">You have gained a level in %s</pre>'),
                    $skill->getName()
                );
                $this->messageProfile($profile, $message);
            }
        }
        $this->entityManager->flush($profile);
        return true;
    }

    /**
     * @param Profile $profile
     * @param $amount
     * @param bool $flush
     */
    protected function gainSkillpoints(Profile $profile, $amount, $flush = false)
    {
        $profile->setSkillPoints($profile->getSkillPoints() + $amount);
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-addon">You have received %s skillpoints</pre>'),
            $amount
        );
        $this->messageProfile($profile, $message);
        if ($flush) $this->entityManager->flush($profile);
    }

    /**
     * @param Profile $profile
     * @param $message
     */
    protected function messageProfile(Profile $profile, $message = NULL)
    {
        $wsClient = $this->getWsClientByProfile($profile);
        if ($wsClient) {
            if (!$message) {
                $message = '<pre style="white-space: pre-wrap;" class="text-danger">INVALID SYSTEM-MESSAGE RECEIVED</pre>';
            }
            if (is_array($message)) {
                $command = 'showoutputprepend';
            }
            else {
                $command = 'showmessageprepend';
            }
            $response = [
                'command' => $command,
                'message' => $message
            ];
            $wsClient->send(json_encode($response));
        }
    }

    /**
     * @param Profile $profile
     * @return null|object
     */
    protected function getWsClientByProfile(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        foreach ($ws->getClients() as $wsClientId => $wsClient) {
            $wsClientData = $ws->getClientData($wsClient->resourceId);
            if ($wsClientData->profileId == $profile->getId()) {
                return $wsClient;
            }
        }
        return NULL;
    }

    /**
     * @param $parameter
     * @param Node $currentNode
     * @return bool|Connection
     */
    protected function findConnectionByNameOrNumber($parameter, Node $currentNode)
    {
        $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        /** @var ConnectionRepository $connectionRepo */
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $connections = $connectionRepo->findBySourceNode($currentNode);
        $connection = false;
        if ($searchByNumber) {
            if (isset($connections[$parameter - 1])) {
                $connection = $connections[$parameter - 1];
            }
        } else {
            foreach ($connections as $pconnection) {
                /** @var Connection $pconnection */
                if ($pconnection->getTargetNode()->getName() == $parameter) {
                    $connection = $pconnection;
                    break;
                }
            }
        }
        return $connection;
    }

    /**
     * @param int|string $parameter
     * @return NpcInstance|null
     */
    protected function findNpcByNameOrNumberInCurrentNode($parameter)
    {
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $searchByNumber = false;
        if (is_numeric($parameter)) {
            $searchByNumber = true;
        }
        $npcs = $npcInstanceRepo->findBy([
            'node' => $this->user->getProfile()->getCurrentNode()
        ]);
        $npc = NULL;
        if ($searchByNumber) {
            if (isset($npcs[$parameter - 1])) {
                $npc = $npcs[$parameter - 1];
            }
        }
        else {
            foreach ($npcs as $xnpc) {
                /** @var NpcInstance $xnpc */
                if (mb_strrpos($xnpc->getName(), $parameter) !== false) {
                    $npc = $xnpc;
                    break;
                }
            }
        }
        return $npc;
    }

    /**
     * @param $skillName
     * @return mixed
     */
    protected function reverseSkillNameModification($skillName)
    {
        return str_replace('-', ' ', $skillName);
    }

    /**
     * @param Profile|NpcInstance $profile
     * @param int $skillId
     * @return int
     */
    protected function getSkillRating($profile, $skillId)
    {
        $skill = $this->entityManager->find('Netrunners\Entity\Skill', $skillId);
        /** @var Skill $skill */
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $skillRatingObject = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
        /** @var SkillRating $skillRatingObject */
        return ($skillRatingObject) ? $skillRatingObject->getRating() : 0;
    }

    /**
     * @param Profile $profile
     * @param Skill $skill
     * @param $newSkillRating
     * @return bool
     */
    public function setSkillRating(Profile $profile, Skill $skill, $newSkillRating)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $skillRatingObject = $skillRatingRepo->findByProfileAndSkill($profile, $skill);
        /** @var SkillRating $skillRatingObject */
        $skillRatingObject->setRating($newSkillRating);
        $this->entityManager->flush($skillRatingObject);
        return true;
    }

    /**
     * @param Profile $profile
     * @param Node $node
     */
    protected function addKnownNode(Profile $profile, Node $node)
    {
        $knownNodeRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
        /** @var KnownNodeRepository $knownNodeRepo */
        $row = $knownNodeRepo->findByProfileAndNode($profile, $node);
        if ($row) {
            /** @var KnownNode $row */
            $row->setType($node->getNodeType()->getId());
            $row->setCreated(new \DateTime());
        }
        else {
            $row = new KnownNode();
            $row->setCreated(new \DateTime());
            $row->setProfile($profile);
            $row->setNode($node);
            $row->setType($node->getNodeType()->getId());
            $this->entityManager->persist($row);
        }
        $this->entityManager->flush($row);
    }

    /**
     * @param Profile $profile
     * @param Node $node
     * @return mixed
     */
    protected function getKnownNode(Profile $profile, Node $node)
    {
        $knownNodeRepo = $this->entityManager->getRepository('Netrunners\Entity\KnownNode');
        /** @var KnownNodeRepository $knownNodeRepo */
        return $knownNodeRepo->findByProfileAndNode($profile, $node);
    }

    /**
     * Sends the given message to everyone in the given node, optionally excluding the source of the message.
     * An actor can be given, if it is, the method will check if the current subject can see the actor.
     * If profiles is given, those profiles will be excluded as they will be considered to be the source of the message.
     * @param Node $node
     * @param $message
     * @param Profile|NpcInstance|null $actor
     * @param mixed $ignoredProfileIds
     */
    public function messageEveryoneInNode(Node $node, $message, $actor = NULL, $ignoredProfileIds = [])
    {
        $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
        /** @var ProfileRepository $profileRepo */
        $wsClients = $this->getWebsocketServer()->getClients();
        $wsClientsData = $this->getWebsocketServer()->getClientsData();
        $profiles = $profileRepo->findByCurrentNode($node);
        foreach ($profiles as $xprofile) {
            /** @var Profile $xprofile */
            if (!is_array($ignoredProfileIds)) $ignoredProfileIds = [$ignoredProfileIds];
            if (in_array($xprofile->getId(), $ignoredProfileIds)) continue;
            if ($xprofile !== $actor && !$this->canSee($xprofile, $actor)) continue;
            foreach ($wsClients as $wsClient) {
                if (
                    isset($wsClientsData[$wsClient->resourceId]) &&
                    $wsClientsData[$wsClient->resourceId]['hash'] &&
                    $wsClientsData[$wsClient->resourceId]['profileId'] == $xprofile->getId()
                ) {

                    if (!is_array($message)) {
                        $message = [
                            'command' => 'showmessageprepend',
                            'message' => $message
                        ];
                    }
                    $wsClient->send(json_encode($message));
                }
            }
        }
    }

    /**
     * @param array $contentArray
     * @param bool $returnContent
     * @param bool $castToInt
     * @param bool $implode
     * @param bool $makeSafe
     * @param array $safeOptions
     * @return array|int|mixed|null|string
     */
    protected function getNextParameter(
        $contentArray = [],
        $returnContent = true,
        $castToInt = false,
        $implode = false,
        $makeSafe = false,
        $safeOptions = ['safe'=>1,'elements'=>'strong']
    )
    {
        $parameter = NULL;
        $nextParameter = (!$implode) ? array_shift($contentArray) : implode(' ', $contentArray);
        if ($nextParameter !== NULL) {
            trim($nextParameter);
            if ($makeSafe) $nextParameter = htmLawed($nextParameter, $safeOptions);
            if ($castToInt) $nextParameter = (int)$nextParameter;
            $parameter = $nextParameter;
        }
        return ($returnContent) ? [$contentArray, $parameter] : $parameter;
    }

    /**
     * @param Skill $skill
     * @return string
     */
    protected function getInputNameOfSkill(Skill $skill)
    {
        return str_replace(' ', '', $skill->getName());
    }

    /**
     * @param Profile $profile
     * @param $codeOptions
     * @return int
     */
    protected function calculateCodingSuccessChance(Profile $profile, $codeOptions)
    {
        $difficulty = $codeOptions->fileLevel;
        $skillModifier = 0;
        if ($codeOptions->mode == 'program') {
            $targetType = $this->entityManager->find('Netrunners\Entity\FileType', $codeOptions->fileType);
            /** @var FileType $targetType */
            $skillModifier = $this->getSkillModifierForFileType($targetType, $profile);
        }
        if ($codeOptions->mode == 'resource') {
            $targetType = $this->entityManager->find('Netrunners\Entity\FilePart', $codeOptions->fileType);
            /** @var FilePart $targetType */
            $skillModifier = $this->getSkillModifierForFilePart($targetType, $profile);
        }
        if ($codeOptions->mode == 'mod') {
            $skillModifier = $this->getSkillRating($profile, Skill::ID_ADVANCED_CODING);
        }
        $skillCoding = $this->getSkillRating($profile, Skill::ID_CODING);
        $skillRating = floor(($skillCoding + $skillModifier)/2);
        $chance = $skillRating - $difficulty;
        return (int)$chance;
    }

    /**
     * @param FileType $fileType
     * @param Profile $profile
     * @return int
     */
    protected function getSkillModifierForFileType(FileType $fileType, Profile $profile)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $fileTypeSkillRepo = $this->entityManager->getRepository('Netrunners\Entity\FileTypeSkill');
        /** @var FileTypeSkillRepository $fileTypeSkillRepo */
        $rating = 0;
        $fileTypeSkills = $fileTypeSkillRepo->findBy([
            'fileType' => $fileType
        ]);
        $amount = 0;
        foreach ($fileTypeSkills as $fileTypeSkill) {
            /** @var FileTypeSkill $fileTypeSkill */
            $amount++;
            $skillRating = $skillRatingRepo->findByProfileAndSkill(
                $profile, $fileTypeSkill->getSkill()
            );
            /** @var SkillRating $skillRating */
            $rating += ($skillRating->getRating()) ? $skillRating->getRating() : 0;
        }
        $rating = ceil($rating/$amount);
        return (int)$rating;
    }

    /**
     * @param FilePart $filePart
     * @param Profile $profile
     * @return int
     */
    protected function getSkillModifierForFilePart(FilePart $filePart, Profile $profile)
    {
        $skillRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\SkillRating');
        /** @var SkillRatingRepository $skillRatingRepo */
        $filePartSkillRepo = $this->entityManager->getRepository('Netrunners\Entity\FilePartSkill');
        /** @var FilePartSkillRepository $filePartSkillRepo */
        $rating = 0;
        $filePartSkills = $filePartSkillRepo->findBy([
            'filePart' => $filePart
        ]);
        $amount = 0;
        foreach ($filePartSkills as $filePartSkill) {
            /** @var FilePartSkill $filePartSkill */
            $amount++;
            $skillRating = $skillRatingRepo->findByProfileAndSkill(
                $profile, $filePartSkill->getSkill()
            );
            /** @var SkillRating $skillRating */
            $rating += ($skillRating->getRating()) ? $skillRating->getRating() : 0;
        }
        $rating = ceil($rating/$amount);
        return (int)$rating;
    }

    /**
     * @param string $string
     * @param string $replacer
     * @return mixed
     */
    protected function getNameWithoutSpaces($string = '', $replacer = '-')
    {
        return str_replace(' ', $replacer, $string);
    }

    /**
     * Moves the profile to the node specified by the connection or the target-node.
     * If no connection is given then source- and target-node must be given. This also messages all profiles
     * in the source- and target-node. If no resourceId is given, this will move the profile but not
     * message the moved profile.
     * @param int|NULL $resourceId
     * @param Profile $profile
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return array|bool
     */
    protected function movePlayerToTargetNode(
        $resourceId = NULL,
        Profile $profile,
        Connection $connection = NULL,
        Node $sourceNode = NULL,
        Node $targetNode = NULL
    )
    {
        if ($connection) {
            $sourceNode = $connection->getSourceNode();
            $targetNode = $connection->getTargetNode();
        }
        $toString = ($connection) ? $targetNode->getName() : $this->translate('somewhere unknown');
        // message everyone in source node
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">%s has used the connection to %s</pre>'),
            $profile->getUser()->getUsername(),
            $toString
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($sourceNode, $message, $profile, $profile->getId());
        $profile->setCurrentNode($targetNode);
        $fromString = ($connection) ? $sourceNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">%s has connected to this node from %s</pre>'),
            $profile->getUser()->getUsername(),
            $fromString
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($targetNode, $message, $profile, $profile->getId());
        $this->entityManager->flush($profile);
        $this->checkNpcAggro($profile, $resourceId);
        return ($resourceId) ? $this->getWebsocketServer()->getNodeService()->showNodeInfo($resourceId) : false;
    }

    /**
     * @param NpcInstance $npc
     * @param Connection|NULL $connection
     * @param Node|NULL $sourceNode
     * @param Node|NULL $targetNode
     * @return bool
     */
    protected function moveNpcToTargetNode(
        NpcInstance $npc,
        Connection $connection = NULL,
        Node $sourceNode = NULL,
        Node $targetNode = NULL
    )
    {
        // set source- and target-node if a connection was given
        if ($connection) {
            $sourceNode = $connection->getSourceNode();
            $targetNode = $connection->getTargetNode();
        }
        // message everyone in source node
        $toString = ($connection) ? $targetNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">%s has used the connection to %s</pre>'),
            $npc->getName(),
            $toString
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($sourceNode, $message, $npc);
        $npc->setNode($targetNode);
        $fromString = ($connection) ? $sourceNode->getName() : $this->translate('somewhere unknown');
        $messageText = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">%s has connected to this node from %s</pre>'),
            $npc->getName(),
            $fromString
        );
        $message = array(
            'command' => 'showmessageprepend',
            'message' => $messageText
        );
        $this->messageEveryoneInNode($targetNode, $message, $npc);
        $this->checkNpcAggro($npc);
        $this->checkAggro($npc);
        if (!$this->isInCombat($npc)) $this->checkNpcTriggers($npc);
        $this->entityManager->flush($npc);
        return true;
    }

    /**
     * @param Profile $profile
     * @param System $currentSytem
     * @return bool
     */
    protected function canAccess(Profile $profile, System $currentSytem)
    {
        $systemProfile = $currentSytem->getProfile();
        $systemGroup = $currentSytem->getGroup();
        $systemFaction = $currentSytem->getFaction();
        $canAccess = true;
        if ($systemProfile && $systemProfile !== $profile) $canAccess = false;
        if ($systemFaction && $systemFaction !== $profile->getFaction()) $canAccess = false;
        if ($systemGroup && $systemGroup !== $profile->getGroup()) $canAccess = false;
        return $canAccess;
    }

    /**
     * @param NpcInstance $npc
     */
    private function checkNpcTriggers(NpcInstance $npc)
    {
        if (!$this->isInCombat($npc)) {
            switch ($npc->getNpc()->getId()) {
                default:
                    break;
                case Npc::ID_WORKER_PROGRAM:
                    $this->checkWorkerTriggers($npc);
                    break;
            }
        }
    }

    /**
     * @param NpcInstance $npc
     */
    private function checkWorkerTriggers(NpcInstance $npc)
    {
        $currentNode = $npc->getNode();
        switch ($currentNode->getNodeType()->getId()) {
            default:
                break;
            case NodeType::ID_DATABASE:
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $miners = $fileRepo->findOneForHarvesting($npc, FileType::ID_DATAMINER);
                $highestAmount = 0;
                $miner = NULL;
                $minerData = NULL;
                foreach ($miners as $xMiner) {
                    /** @var File $xMiner */
                    $xMinerData = json_decode($xMiner->getData());
                    if (isset($xMinerData->value)) {
                        if ($xMinerData->value > $highestAmount) {
                            $highestAmount = $xMinerData->value;
                            $miner = $xMiner;
                            $minerData = $xMinerData;
                        }
                    }
                }
                if ($miner && $minerData) {
                    $availableAmount = $minerData->value;
                    $amount = ($npc->getLevel() > $availableAmount) ? $availableAmount : $npc->getLevel();
                    $minerData->value -= $amount;
                    $npc->setSnippets($npc->getSnippets()+$amount);
                    $miner->setData(json_encode($minerData));
                    $this->entityManager->flush($miner);
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has collected some snippets from [%s]</pre>'),
                        $npc->getName(),
                        $miner->getName()
                    );
                    $this->messageEveryoneInNode($currentNode, $message);
                }
                break;
            case NodeType::ID_TERMINAL:
                $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
                /** @var FileRepository $fileRepo */
                $miners = $fileRepo->findOneForHarvesting($npc, FileType::ID_COINMINER);
                $highestAmount = 0;
                $miner = NULL;
                $minerData = NULL;
                foreach ($miners as $xMiner) {
                    /** @var File $xMiner */
                    $xMinerData = json_decode($xMiner->getData());
                    if (isset($xMinerData->value)) {
                        if ($xMinerData->value > $highestAmount) {
                            $highestAmount = $xMinerData->value;
                            $miner = $xMiner;
                            $minerData = $xMinerData;
                        }
                    }
                }
                if ($miner && $minerData) {
                    $availableAmount = $minerData->value;
                    $amount = ($npc->getLevel() > $availableAmount) ? $availableAmount : $npc->getLevel();
                    $minerData->value -= $amount;
                    $npc->setCredits($npc->getCredits()+$amount);
                    $miner->setData(json_encode($minerData));
                    $this->entityManager->flush($miner);
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has collected some credits from [%s]</pre>'),
                        $npc->getName(),
                        $miner->getName()
                    );
                    $this->messageEveryoneInNode($currentNode, $message);
                }
                break;
            case NodeType::ID_BANK:
                $npcProfile = $npc->getProfile();
                if ($npcProfile) {
                    $flush = false;
                    if ($npc->getCredits() >= 1) {
                        $npcProfile->setBankBalance($npcProfile->getBankBalance() + $npc->getCredits());
                        $npc->setCredits(0);
                        $flush = true;
                    }
                    if ($npc->getSnippets() >= 1) {
                        $npcProfile->setSnippets($npcProfile->getSnippets() + $npc->getSnippets());
                        $npc->setSnippets(0);
                        $flush = true;
                    }
                    if ($flush) $this->entityManager->flush($npcProfile);
                }
                break;
        }
    }

    /**
     * Checks if the player is blocked from performing another action.
     * Returns true if the action is blocked, false if it is not blocked.
     * @param $resourceId
     * @param bool $checkForFullBlock
     * @return array|bool
     */
    protected function isActionBlocked($resourceId, $checkForFullBlock = false)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $isBlocked = false;
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
            $this->translate('You are currently busy with something else')
        );
        /* combat block check follows - combat never fully blocks */
        if (!$checkForFullBlock) {
            $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
            $isBlocked = $this->isInCombat($user->getProfile());
            if ($isBlocked) {
                $message = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You are currently busy fighting')
                );
            }
        }
        /* action block check follows */
        if (!empty($clientData->action) && !$isBlocked) {
            $actionData = (object)$clientData->action;
            $isBlocked = false;
            if ($checkForFullBlock) {
                if ($actionData->fullblock) $isBlocked = true;
            }
            if (!$isBlocked) {
                if ($actionData->blocking) $isBlocked = true;
            }
        }
        if ($isBlocked) {
            $isBlocked = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $isBlocked;
    }

    /**
     * @param Node|NULL $node
     * @return bool|int
     */
    protected function getNodeAttackDifficulty(Node $node = NULL)
    {
        $result = false;
        if ($node) {
            switch ($node->getNodeType()->getId()) {
                default:
                    break;
                case NodeType::ID_PUBLICIO:
                case NodeType::ID_IO:
                    $result = $node->getLevel() * FileService::DEFAULT_DIFFICULTY_MOD;
                    break;
            }
        }
        return $result;
    }

    /**
     * @param $resourceId
     * @param $element
     * @param $value
     * @return bool
     */
    protected function updateInterfaceElement($resourceId, $element, $value)
    {
        $wsClient = NULL;
        foreach ($this->getWebsocketServer()->getClients() as $xClientId => $xClient) {
            if ($xClient->resourceId == $resourceId) {
                $wsClient = $xClient;
                break;
            }
        }
        if ($wsClient) {
            $response = [
                'command' => 'updateinterfaceelement',
                'message' => [
                    'element' => $element,
                    'value' => $value
                ]
            ];
            $wsClient->send(json_encode($response));
        }
        return true;
    }

    /**
     * Used to check if a certain file-type can be executed in a node.
     * @param File $file
     * @param Node $node
     * @return bool
     */
    protected function canExecuteInNodeType(File $file, Node $node)
    {
        $result = false;
        $validNodeTypes = [];
        switch ($file->getFileType()->getId()) {
            default:
                $result = true;
                break;
            case FileType::ID_RESEARCHER:
                $validNodeTypes[] = NodeType::ID_MEMORY;
                break;
            case FileType::ID_COINMINER:
                $validNodeTypes[] = NodeType::ID_TERMINAL;
                break;
            case FileType::ID_DATAMINER:
                $validNodeTypes[] = NodeType::ID_DATABASE;
                break;
            case FileType::ID_ICMP_BLOCKER:
                $validNodeTypes[] = NodeType::ID_IO;
                break;
            case FileType::ID_CUSTOM_IDE:
                $validNodeTypes[] = NodeType::ID_CODING;
                break;
            case FileType::ID_SKIMMER:
            case FileType::ID_BLOCKCHAINER:
                $validNodeTypes[] = NodeType::ID_BANK;
                break;
            case FileType::ID_LOG_ENCRYPTOR:
            case FileType::ID_LOG_DECRYPTOR:
                $validNodeTypes[] = NodeType::ID_MONITORING;
                break;
            case FileType::ID_PHISHER:
            case FileType::ID_WILDERSPACE_HUB_PORTAL:
                $validNodeTypes[] = NodeType::ID_INTRUSION;
                break;
            case FileType::ID_BEARTRAP:
                $validNodeTypes[] = NodeType::ID_FIREWALL;
                break;
            case FileType::ID_JACKHAMMER:
            case FileType::ID_PORTSCANNER:
            case FileType::ID_WORMER:
            case FileType::ID_IO_TRACER:
                $validNodeTypes[] = NodeType::ID_IO;
                $validNodeTypes[] = NodeType::ID_PUBLICIO;
                break;
        }
        // if result is false, check if the node type matches an entry of the valid-node-types array
        return (!$result) ? in_array($node->getNodeType()->getId(), $validNodeTypes) : $result;
    }

    /**
     * @param Profile $profile
     * @param string $subject
     * @param string $severity
     * @return bool
     */
    protected function storeNotification(Profile $profile, $subject = 'INVALID', $severity = 'danger')
    {
        $notification = new Notification();
        $notification->setProfile($profile);
        $notification->setSentDateTime(new \DateTime());
        $notification->setSubject($subject);
        $notification->setSeverity($severity);
        $this->entityManager->persist($notification);
        $this->entityManager->flush($notification);
        return true;
    }

    /**
     * @param Profile $profile
     * @param MilkrunInstance|NULL $milkrunInstance
     * @param Profile|NULL $rater
     * @param int $source
     * @param int $sourceRating
     * @param int $targetRating
     * @param null $sourceFaction
     * @param null $targetFaction
     * @return bool
     */
    protected function createProfileFactionRating(
        Profile $profile,
        MilkrunInstance $milkrunInstance = NULL,
        Profile $rater = NULL,
        $source = 0,
        $sourceRating = 0,
        $targetRating = 0,
        $sourceFaction = NULL,
        $targetFaction = NULL
    )
    {
        $existingRating = false;
        // make sure milkrun isnt added twice
        if ($milkrunInstance) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('pfr');
            $qb->from('Netrunners\Entity\ProfileFactionRating', 'pfr');
            $qb->where('pfr.milkrunInstance = :milkrun');
            $qb->setParameter('milkrun', $milkrunInstance);
            $qb->setMaxResults(1);
            $result = $qb->getQuery()->getOneOrNullResult();
            if ($result) $existingRating = true;
        }
        // if no rating exists, create one
        if (!$existingRating) {
            $pfr = new ProfileFactionRating();
            $pfr->setProfile($profile);
            $pfr->setAdded(new \DateTime());
            $pfr->setMilkrunInstance($milkrunInstance);
            $pfr->setRater($rater);
            $pfr->setSource($source);
            $pfr->setSourceRating($sourceRating);
            $pfr->setTargetRating($targetRating);
            $pfr->setSourceFaction($sourceFaction);
            $pfr->setTargetFaction($targetFaction);
            $this->entityManager->persist($pfr);
            $this->entityManager->flush($pfr);
        }
        return true;
    }

    /**
     * Returns the total rating for the given profile and faction.
     * @param Profile $profile
     * @param Faction $faction
     * @return mixed
     */
    protected function getProfileFactionRating(Profile $profile, Faction $faction)
    {
        $profileFactionRatingRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFactionRating');
        /** @var ProfileFactionRatingRepository $profileFactionRatingRepo */
        return $profileFactionRatingRepo->getProfileFactionRating($profile, $faction);
    }

    protected function canStartActionInNodeType()
    {

    }

    /**
     * Generate a random string.
     *
     * @param int $length      How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     */
    public function getRandomString($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     * @param System $system
     * @param int $amount
     */
    public function raiseSystemAlertLevel(System $system, $amount = 0)
    {
        $currentLevel = $system->getAlertLevel();
        $system->setAlertLevel($currentLevel + $amount);
        $this->entityManager->flush($system);
    }

    /**
     * @param Profile $profile
     * @param int $amount
     */
    public function raiseProfileSecurityRating(Profile $profile, $amount = 0)
    {
        $currentRating = $profile->getSecurityRating();
        $profile->setSecurityRating($currentRating + $amount);
        $newRating = $profile->getSecurityRating();
        $currentNode = $profile->getCurrentNode();
        if ($newRating >= Profile::SECURITY_RATING_MAX) {
            $newRating = Profile::SECURITY_RATING_MAX;
            $profile->setSecurityRating(Profile::SECURITY_RATING_MAX);
        }
        if ($newRating >= Profile::SECURITY_RATING_NETWATCH_THRESHOLD) {
            $npcType = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_NETWATCH_INVESTIGATOR);
            if ($profile->getSecurityRating() >= 90) {
                $npcType = $this->entityManager->find('Netrunners\Entity\Npc', Npc::ID_NETWATCH_AGENT);
            }
            /** @var Npc $npcType */
            $npcInstance = $this->spawnNpcInstance(
                $npcType,
                $currentNode,
                NULL,
                NULL,
                NULL,
                NULL,
                ceil(round($newRating/10)),
                true
            );
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has connected to this node from out of nowhere looking for [%s]</pre>'),
                $npcType->getName(),
                $profile->getUser()->getUsername()
            );
            $this->messageEveryoneInNode($currentNode, $message, $profile);
            $this->forceAttack($npcInstance, $profile);
        }
        $this->entityManager->flush($profile);
    }

    // TODO credit bounties for killing people with security rating

    /**
     * @param Profile|NpcInstance $attacker
     * @param Profile|NpcInstance $defender
     */
    protected function forceAttack($attacker, $defender)
    {
        $ws = $this->getWebsocketServer();
        $attackerName = ($attacker instanceof Profile) ? $attacker->getUser()->getUsername() : $attacker->getName();
        $defenderName = ($defender instanceof Profile) ? $defender->getUser()->getUsername() : $defender->getName();
        $currentNode = ($attacker instanceof Profile) ? $attacker->getCurrentNode() : $attacker->getNode();
        if ($attacker instanceof Profile) {
            if ($defender instanceof Profile) {
                $ws->addCombatant($attacker, $defender, $attacker->getCurrentResourceId(), $defender->getCurrentResourceId());
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker, $defender->getCurrentResourceId(), $attacker->getCurrentResourceId());
            }
            if ($defender instanceof NpcInstance) {
                $ws->addCombatant($attacker, $defender, $attacker->getCurrentResourceId());
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker, NULL, $attacker->getCurrentResourceId());
            }
        }
        if ($attacker instanceof NpcInstance) {
            if ($defender instanceof Profile) {
                $ws->addCombatant($attacker, $defender, NULL, $defender->getCurrentResourceId());
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker, $defender->getCurrentResourceId());
            }
            if ($defender instanceof NpcInstance) {
                $ws->addCombatant($attacker, $defender);
                if ($this->isInCombat($defender)) $ws->addCombatant($defender, $attacker);
            }
        }
        // inform players in node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
            $attackerName,
            $defenderName
        );
        $this->messageEveryoneInNode($currentNode, $message);
    }

    /**
     * @param System $system
     * @param string $subject
     * @param string $severity
     * @param null $details
     * @param File|NULL $file
     * @param Node|NULL $node
     * @param Profile|NULL $profile
     */
    public function writeSystemLogEntry(
        System $system,
        $subject = '',
        $severity = 'info',
        $details = NULL,
        File $file = NULL,
        Node $node = NULL,
        Profile $profile = NULL
    )
    {
        $log = new SystemLog();
        $log->setAdded(new \DateTime());
        $log->setSystem($system);
        $log->setSubject($subject);
        $log->setSeverity($severity);
        $log->setDetails($details);
        $log->setFile($file);
        $log->setNode($node);
        $log->setProfile($profile);
        $this->entityManager->persist($log);
        $this->entityManager->flush($log);
    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     * @return mixed
     */
    protected function getProfileGameOption(Profile $profile, $gameOptionId)
    {
        $gameOption = $this->entityManager->find('Netrunners\Entity\GameOption', $gameOptionId);
        $goiRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOptionInstance');
        $gameOptionInstance = $goiRepo->findOneBy([
            'gameOption' => $gameOption,
            'profile' => $profile
        ]);
        return ($gameOptionInstance) ? $gameOptionInstance->getStatus() : $gameOption->getDefaultStatus();
    }

    /**
     * @param Profile $profile
     * @param $gameOptionId
     */
    protected function toggleProfileGameOption(Profile $profile, $gameOptionId)
    {
        $gameOption = $this->entityManager->find('Netrunners\Entity\GameOption', $gameOptionId);
        if ($profile && $gameOption) {
            $goiRepo = $this->entityManager->getRepository('Netrunners\Entity\GameOptionInstance');
            $gameOptionInstance = $goiRepo->findOneBy([
                'gameOption' => $gameOption,
                'profile' => $profile
            ]);
            if ($gameOptionInstance) {
                /** @var GameOptionInstance $gameOptionInstance */
                $currentStatus = $gameOptionInstance->getStatus();
            }
            else {
                $currentStatus = $gameOption->getDefaultStatus();
            }
            $newStatus = ($currentStatus) ? false : true;
            if ($gameOptionInstance) {
                $gameOptionInstance->setStatus($newStatus);
            }
            else {
                $gameOptionInstance = new GameOptionInstance();
                $gameOptionInstance->setStatus($newStatus);
                $gameOptionInstance->setProfile($profile);
                $gameOptionInstance->setGameOption($gameOption);
                $gameOptionInstance->setChanged(new \DateTime());
                $this->entityManager->persist($gameOptionInstance);
            }
            $this->entityManager->flush($gameOptionInstance);
        }
    }

    /**
     * @param User|NULL $user
     * @param $roleId
     * @param bool $checkParents
     * @return bool
     */
    public function hasRole(User $user = NULL, $roleId, $checkParents = true)
    {
        $hasRole = false;
        $neededRole = $this->entityManager->getRepository('TmoAuth\Entity\Role')->findOneBy([
            'roleId' => $roleId
        ]);
        if (!$neededRole) return $hasRole;
        /** @var Role $neededRole */
        $roles = ($user) ? $user->getRoles() : $this->user->getRoles();
        foreach ($roles as $xRole) {
            /** @var Role $xRole */
            if ($this->checkParentRoleSatisfy($xRole, $neededRole, $checkParents) === true) {
                $hasRole = true;
                break;
            }
        }
        return $hasRole;
    }

    /**
     * @param Role $checkRole
     * @param Role $neededRole
     * @param bool $checkParents
     * @return bool
     */
    private function checkParentRoleSatisfy(
        Role $checkRole,
        Role $neededRole,
        $checkParents = true
    )
    {
        $hasRole = false;
        if ($checkRole->getRoleId() == $neededRole->getRoleId()) {
            $hasRole = true;
        }
        if (!$hasRole && $checkParents && $checkRole->getParent()) {
            $hasRole = $this->checkParentRoleSatisfy($checkRole->getParent(), $neededRole, $checkParents);
        }
        return $hasRole;
    }

    /**
     * @param NpcInstance|Profile $combatant
     * @return bool
     */
    protected function isInCombat($combatant)
    {
        $inCombat = false;
        $combatantData = (object)$this->getWebsocketServer()->getCombatants();
        if ($combatant instanceof Profile) {
            if (array_key_exists($combatant->getId(), $combatantData->profiles)) $inCombat = true;
        }
        if ($combatant instanceof NpcInstance) {
            if (array_key_exists($combatant->getId(), $combatantData->npcs)) $inCombat = true;
        }
        return $inCombat;
    }

    /**
     * @param string $string
     * @param int $maxLength
     * @param int $minLength
     */
    protected function stringChecker($string = '', $maxLength = 32, $minLength = 1)
    {
        // check if only alphanumeric
        $validator = new Alnum(array('allowWhiteSpace' => true));
        if (!$this->response && !$validator->isValid($string)) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Invalid string (alpha-numeric only)')
                )
            );
        }
        // check for max characters
        if (!$this->response && mb_strlen($string) > $maxLength) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Invalid string (%s-characters-max)</pre>'),
                    $maxLength
                )
            );
        }
        // check for min characters
        if (!$this->response && mb_strlen($string) < $minLength) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Invalid string (%s-characters-min)</pre>'),
                    $minLength
                )
            );
        }
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showSystemMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $currentSystem = $profile->getCurrentNode()->getSystem();
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
            /** @var NodeRepository $nodeRepo */
            $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
            /** @var ConnectionRepository $connectionRepo */
            $nodes = $nodeRepo->findBySystem($currentSystem);
            foreach ($nodes as $node) {
                /** @var Node $node */
                $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getNodeType()->getId();
                $mapArray['nodes'][] = [
                    'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                    'type' => $group
                ];
                $connections = $connectionRepo->findBySourceNode($node);
                foreach ($connections as $connection) {
                    /** @var Connection $connection */
                    $mapArray['links'][] = [
                        'source' => (string)$connection->getSourceNode()->getId() . '_' .
                            $connection->getSourceNode()->getNodeType()->getShortName() . '_' .
                            $connection->getSourceNode()->getName(),
                        'target' => (string)$connection->getTargetNode()->getId() . '_' .
                            $connection->getTargetNode()->getNodeType()->getShortName() . '_' .
                            $connection->getTargetNode()->getName(),
                        'value' => 2,
                        'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                    ];
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showpanel',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showCyberspaceMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        // TODO make admin+ only
        if (!$this->response) {
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
            /** @var NodeRepository $nodeRepo */
            $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
            /** @var ConnectionRepository $connectionRepo */
            $systems = $this->entityManager->getRepository('Netrunners\Entity\System')->findAll();
            foreach ($systems as $currentSystem) {
                /** @var System $currentSystem */
                $nodes = $nodeRepo->findBySystem($currentSystem);
                foreach ($nodes as $node) {
                    /** @var Node $node */
                    $group = $currentSystem->getId();
                    $mapArray['nodes'][] = [
                        'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                        'type' => $group
                    ];
                    $connections = $connectionRepo->findBySourceNode($node);
                    foreach ($connections as $connection) {
                        /** @var Connection $connection */
                        $mapArray['links'][] = [
                            'source' => (string)$connection->getSourceNode()->getId() . '_' .
                                $connection->getSourceNode()->getNodeType()->getShortName() . '_' .
                                $connection->getSourceNode()->getName(),
                            'target' => (string)$connection->getTargetNode()->getId() . '_' .
                                $connection->getTargetNode()->getNodeType()->getShortName() . '_' .
                                $connection->getTargetNode()->getName(),
                            'value' => 2,
                            'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                        ];
                    }
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showpanel',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showAreaMap($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if ($this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->showSystemMap($resourceId);
        }
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
            /** @var ConnectionRepository $connectionRepo */
            $profile = $this->user->getProfile();
            $currentNode = $profile->getCurrentNode();
            // if the profile or its faction or group owns this system, show them the full map
            $currentSystem = $currentNode->getSystem();
            if (
                $profile === $currentSystem->getProfile() ||
                $profile->getFaction() == $currentSystem->getFaction() ||
                $profile->getGroup() == $currentSystem->getGroup()
            ) {
                return $this->showSystemMap($resourceId);
            }
            $mapArray = [
                'nodes' => [],
                'links' => []
            ];
            $nodes = [];
            $nodes[] = $currentNode;
            $connections = $connectionRepo->findBySourceNode($currentNode);
            foreach ($connections as $xconnection) {
                /** @var Connection $xconnection */
                $nodes[] = $xconnection->getTargetNode();
            }
            $counter = true;
            foreach ($nodes as $node) {
                /** @var Node $node */
                $group = ($node == $profile->getCurrentNode()) ? 99 : $node->getNodeType()->getId();
                $mapArray['nodes'][] = [
                    'name' => (string)$node->getId() . '_' . $node->getNodeType()->getShortName() . '_' . $node->getName(),
                    'type' => $group
                ];
                if ($counter) {
                    $connections = $connectionRepo->findBySourceNode($node);
                    foreach ($connections as $connection) {
                        /** @var Connection $connection */
                        $mapArray['links'][] = [
                            'source' => (string)$connection->getSourceNode()->getId() . '_' . $connection->getSourceNode()->getNodeType()->getShortName() . '_' . $connection->getSourceNode()->getName(),
                            'target' => (string)$connection->getTargetNode()->getId() . '_' . $connection->getTargetNode()->getNodeType()->getShortName() . '_' . $connection->getTargetNode()->getName(),
                            'value' => 2,
                            'type' => ($connection->getType() == Connection::TYPE_NORMAL) ? 'A' : 'E'
                        ];
                    }
                    $counter = false;
                }
            }
            $view = new ViewModel();
            $view->setTemplate('netrunners/partials/map.phtml');
            $view->setVariable('json', json_encode($mapArray));
            $this->response = array(
                'command' => 'showpanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param string $command
     * @param bool $content
     * @param bool $silent
     * @param null $response
     * @return bool|null|array
     */
    protected function addAdditionalCommand(
        $command = 'map',
        $content = false,
        $silent = true,
        $response = NULL
    )
    {
        if ($response) {
            if (!array_key_exists('additionalCommands', $response)) $response['additionalCommands'] = [];
            $response['additionalCommands'][] = [
                'command' => $command,
                'content' => $content,
                'silent' => $silent
            ];
            return $response;
        }
        $this->response['additionalCommands'][] = [
            'command' => $command,
            'content' => $content,
            'silent' => $silent
        ];
        return true;
    }

    /**
     * @param null $setting
     * @return int|string
     * @throws \Exception
     */
    protected function getServerSetting($setting = NULL)
    {
        $serverSetting = $this->entityManager->find('Netrunners\Entity\ServerSetting', 1);
        /** @var ServerSetting $serverSetting */
        switch ($setting) {
            default:
                throw new \Exception('No setting was given');
                break;
            case self::SETTING_MOTD:
                $result = $serverSetting->getMotd();
                break;
            case self::SETTING_CHATSUBO_NODE_ID:
                $result = $serverSetting->getChatsuboNodeId();
                break;
            case self::SETTING_CHATSUBO_SYSTEM_ID:
                $result = $serverSetting->getChatsuboSystemId();
                break;
            case self::SETTING_WILDERNESS_NODE_ID:
                $result = $serverSetting->getWildernessHubNodeId();
                break;
            case self::SETTING_WILDERNESS_SYSTEM_ID:
                $result = $serverSetting->getWildernessSystemId();
                break;
        }
        return $result;
    }

    /**
     * @param System $system
     * @param string $valueType
     * @return int|null
     * @throws \Exception
     */
    protected function getTotalSystemValueByNodeType(System $system, $valueType = '')
    {
        $nodeRepo = $this->entityManager->getRepository('Netrunners\Entity\Node');
        /** @var NodeRepository $nodeRepo */
        $value = NULL;
        $nodeType = NULL;
        switch ($valueType) {
            default:
                break;
            case self::VALUE_TYPE_CODINGNODELEVELS:
                $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CODING);
                break;
            case self::VALUE_TYPE_MEMORYLEVELS:
                $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_MEMORY);
                break;
            case self::VALUE_TYPE_STORAGELEVELS:
                $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_STORAGE);
                break;
        }
        if ($nodeType && !empty($valueType)) {
            $affectedNodes = $nodeRepo->findBy([
                'system' => $system,
                'nodeType' => $nodeType
            ]);
            foreach ($affectedNodes as $affectedNode) {
                /** @var Node $affectedNode */
                $value += $affectedNode->getLevel();
            }
        }
        if (!$value) throw new \Exception('Invalid system or value type given');
        return $value;
    }

    /**
     * @param NpcInstance $actor
     */
    public function checkAggro(NpcInstance $actor)
    {
        if ($actor->getAggressive() && !$this->isInCombat($actor)) {
            $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
            /** @var NpcInstanceRepository $npcInstanceRepo */
            $currentNode = $actor->getNode();
            $npcInstances = $npcInstanceRepo->findByNode($currentNode);
            foreach ($npcInstances as $npcInstance) {
                /** @var NpcInstance $npcInstance */
                if ($npcInstance === $actor) continue;
                if (!$this->canSee($actor, $npcInstance)) continue;
                if ($npcInstance->getProfile() === $actor->getProfile()) continue;
                if ($actor->getGroup() && $npcInstance->getGroup() == $actor->getGroup()) continue;
                if ($actor->getFaction() && $npcInstance->getFaction() == $actor->getFaction()) continue;
                if ($actor->getProfile() == NULL && $actor->getFaction() == NULL && $actor->getGroup() == NULL && $npcInstance->getProfile() == NULL && $npcInstance->getFaction() == NULL && $npcInstance->getGroup() == NULL) continue;
                // set combatants
                $this->getWebsocketServer()->addCombatant($actor, $npcInstance);
                if (!$this->isInCombat($npcInstance)) $this->getWebsocketServer()->addCombatant($npcInstance, $actor);
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                    $actor->getName(),
                    $npcInstance->getName()
                );
                $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
                break;
            }
            if (!$this->isInCombat($actor)) {
                $profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
                /** @var ProfileRepository $profileRepo */
                $profiles = $profileRepo->findByCurrentNode($currentNode);
                foreach ($profiles as $profile) {
                    /** @var Profile $profile */
                    if ($profile->getCurrentResourceId()) {
                        if ($actor->getProfile() === $profile) continue;
                        if ($profile->getGroup() && $actor->getGroup() == $profile->getGroup()) continue;
                        if ($profile->getFaction() && $actor->getFaction() == $profile->getFaction()) continue;
                        // set combatants
                        $this->getWebsocketServer()->addCombatant($actor, $profile, NULL, $profile->getCurrentResourceId());
                        if (!$this->isInCombat($profile)) $this->getWebsocketServer()->addCombatant($profile, $actor, $profile->getCurrentResourceId());
                        // inform other players in node
                        $message = sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                            $actor->getName(),
                            $profile->getUser()->getUsername()
                        );
                        $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @param NpcInstance|Profile $target
     * @param null|int $resourceId
     */
    public function checkNpcAggro($target, $resourceId = NULL)
    {
        $npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        /** @var NpcInstanceRepository $npcInstanceRepo */
        $currentNode = ($target instanceof Profile) ? $target->getCurrentNode() : $target->getNode();
        $npcInstances = $npcInstanceRepo->findByNode($currentNode);
        foreach ($npcInstances as $npcInstance) {
            /** @var NpcInstance $npcInstance */
            if ($npcInstance === $target) continue;
            if (!$npcInstance->getAggressive()) continue;
            if ($this->isInCombat($npcInstance)) continue;
            if (!$this->canSee($npcInstance, $target)) continue;
            if ($target instanceof Profile) {
                if ($npcInstance->getProfile() === $target) continue;
                if ($target->getGroup() && $npcInstance->getGroup() == $target->getGroup()) continue;
                if ($target->getFaction() && $npcInstance->getFaction() == $target->getFaction()) continue;
                // set combatants
                $this->getWebsocketServer()->addCombatant($npcInstance, $target, NULL, $resourceId);
                if (!$this->isInCombat($target)) $this->getWebsocketServer()->addCombatant($target, $npcInstance, $resourceId);
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                    $npcInstance->getName(),
                    $target->getUser()->getUsername()
                );
                $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
            }
            if ($target instanceof NpcInstance) {
                if ($npcInstance->getProfile() === $target->getProfile()) continue;
                if ($target->getGroup() && $npcInstance->getGroup() == $target->getGroup()) continue;
                if ($target->getFaction() && $npcInstance->getFaction() == $target->getFaction()) continue;
                if ($target->getProfile() == NULL && $target->getFaction() == NULL && $target->getGroup() == NULL && $npcInstance->getProfile() == NULL && $npcInstance->getFaction() == NULL && $npcInstance->getGroup() == NULL) continue;
                // set combatants
                $this->getWebsocketServer()->addCombatant($npcInstance, $target);
                if (!$this->isInCombat($target)) $this->getWebsocketServer()->addCombatant($target, $npcInstance);
                // inform other players in node
                $message = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] attacks [%s]</pre>'),
                    $npcInstance->getName(),
                    $target->getName()
                );
                $this->messageEveryoneInNode($currentNode, ['command' => 'showmessageprepend', 'message' => $message]);
            }
        }
    }

    /**
     * @param File $file
     * @param int $chance
     * @param int $integrityLoss
     * @param bool $flush
     * @param File|null $targetFile
     * @param NpcInstance|null $targetNpc
     * @param Node|null $targetNode
     */
    protected function lowerIntegrityOfFile(
        File $file,
        $chance = 100,
        $integrityLoss = 1,
        $flush = false,
        $targetFile = NULL,
        $targetNpc = NULL,
        $targetNode = NULL
    )
    {
        if ($chance == 100 || mt_rand(1, 100) <= $chance) {
            $currentIntegrity = $file->getIntegrity();
            $newIntegrity = $currentIntegrity - $integrityLoss;
            if ($newIntegrity < 0) $newIntegrity = 0;
            $file->setIntegrity($newIntegrity);
            if ($newIntegrity < 1) {
                $file->setRunning(false);
                $message = sprintf(
                    $this->translate("[%s][%s] has lost all of its integrity and needs to be updated"),
                    $file->getName(),
                    $file->getId()
                );
                $this->storeNotification($file->getProfile(), $message, 'warning');
            }
            if ($flush) $this->entityManager->flush($file);
        }
    }

    /**
     * @param Npc $npc
     * @param Node $node
     * @param Profile|NULL $profile
     * @param Faction|NULL $faction
     * @param Group|NULL $group
     * @param Node|NULL $homeNode
     * @param null $baseLevel
     * @param bool $flush
     * @return NpcInstance
     */
    protected function spawnNpcInstance(
        Npc $npc,
        Node $node,
        Profile $profile = NULL,
        Faction $faction = NULL,
        Group $group = NULL,
        Node $homeNode = NULL,
        $baseLevel = NULL,
        $flush = false
    )
    {

        // check if a base level was given or use the node level as the base level
        if (!$baseLevel) {
            $baseLevel = $node->getLevel();
        }
        // determine base values depending on npc type
        switch ($npc->getId()) {
            default:
                $credits = 0;
                $snippets = 0;
                $maxEeg = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                break;
            case Npc::ID_KILLER_VIRUS:
            case Npc::ID_MURPHY_VIRUS:
                $credits = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                $snippets = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                $maxEeg = mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                break;
        }
        // sanity checks for generated values
        if ($maxEeg < 1) $maxEeg = 1;
        // spawn
        $npcInstance = new NpcInstance();
        $npcInstance->setNpc($npc);
        $npcInstance->setAdded(new \DateTime());
        $npcInstance->setProfile($profile);
        $npcInstance->setNode($node);
        $npcInstance->setCredits($npc->getBaseCredits() + $credits);
        $npcInstance->setSnippets($npc->getBaseSnippets() + $snippets);
        $npcInstance->setAggressive($npc->getAggressive());
        $npcInstance->setMaxEeg($npc->getBaseEeg() + $maxEeg);
        $npcInstance->setCurrentEeg($npc->getBaseEeg() + $maxEeg);
        $npcInstance->setDescription($npc->getDescription());
        $npcInstance->setName($npc->getName());
        $npcInstance->setFaction($faction);
        $npcInstance->setHomeNode($homeNode);
        $npcInstance->setRoaming($npc->getRoaming());
        $npcInstance->setGroup($group);
        $npcInstance->setLevel($npc->getLevel() + $baseLevel);
        $npcInstance->setSlots($npc->getBaseSlots());
        $npcInstance->setStealthing($npc->getStealthing());
        $npcInstance->setSystem($node->getSystem());
        $npcInstance->setHomeSystem($node->getSystem());
        $this->entityManager->persist($npcInstance);
        /* add skills */
        $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
        foreach ($skills as $skill) {
            /** @var Skill $skill */
            $rating = 0;
            switch ($skill->getId()) {
                default:
                    continue;
                case Skill::ID_STEALTH:
                    $rating = $npc->getBaseStealth() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_DETECTION:
                    $rating = $npc->getBaseDetection() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_BLADES:
                    $rating = $npc->getBaseBlade() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_BLASTERS:
                    $rating = $npc->getBaseBlaster() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
                case Skill::ID_SHIELDS:
                    $rating = $npc->getBaseShield() + mt_rand(($baseLevel - 1) * 10, $baseLevel * 10);
                    break;
            }
            $skillRating = new SkillRating();
            $skillRating->setNpc($npcInstance);
            $skillRating->setProfile(NULL);
            $skillRating->setSkill($skill);
            $skillRating->setRating($rating);
            $this->entityManager->persist($skillRating);
            $npcInstance->addSkillRating($skillRating);
        }
        // add files
        switch ($npc->getId()) {
            default:
                break;
            case Npc::ID_WILDERSPACE_INTRUDER:
                $dropChance = $npcInstance->getLevel();
                if (mt_rand(1, 100) <= $dropChance) {
                    $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_WILDERSPACE_HUB_PORTAL);
                    /** @var FileType $fileType */
                    $file = new File();
                    $file->setProfile(NULL);
                    $file->setLevel($dropChance);
                    $file->setCreated(new \DateTime());
                    $file->setSystem(NULL);
                    $file->setName($fileType->getName());
                    $file->setNpc($npcInstance);
                    $file->setData(NULL);
                    $file->setRunning(false);
                    $file->setSlots(NULL);
                    $file->setNode(NULL);
                    $file->setCoder(NULL);
                    $file->setExecutable($fileType->getExecutable());
                    $file->setFileType($fileType);
                    $file->setIntegrity($dropChance*10);
                    $file->setMaxIntegrity($dropChance*10);
                    $file->setMailMessage(NULL);
                    $file->setModified(NULL);
                    $file->setSize($fileType->getSize());
                    $file->setVersion(1);
                    $this->entityManager->persist($file);
                    $npcInstance->addFile($file);
                }
                break;
            case Npc::ID_NETWATCH_INVESTIGATOR:
            case Npc::ID_NETWATCH_AGENT:
                $fileType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_CODEBLADE);
                /** @var FileType $fileType */
                $file = new File();
                $file->setProfile(NULL);
                $file->setLevel($baseLevel * 10);
                $file->setCreated(new \DateTime());
                $file->setSystem(NULL);
                $file->setName($fileType->getName());
                $file->setNpc($npcInstance);
                $file->setData(NULL);
                $file->setRunning(true);
                $file->setSlots($baseLevel);
                $file->setNode(NULL);
                $file->setCoder(NULL);
                $file->setExecutable($fileType->getExecutable());
                $file->setFileType($fileType);
                $file->setIntegrity($baseLevel*10);
                $file->setMaxIntegrity($baseLevel*10);
                $file->setMailMessage(NULL);
                $file->setModified(NULL);
                $file->setSize($fileType->getSize());
                $file->setVersion(1);
                $this->entityManager->persist($file);
                $npcInstance->setBladeModule($file);
                $npcInstance->addFile($file);
                break;
        }
        if ($flush) {
            $this->entityManager->flush();
        }
        return $npcInstance;
    }

}
