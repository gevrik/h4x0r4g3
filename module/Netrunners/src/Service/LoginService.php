<?php

/**
 * Login Service.
 * The service supplies methods that resolve logic around the login process.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Application\Service\WebsocketService;
use Doctrine\ORM\EntityManager;
use Netrunners\Entity\MilkrunAivatar;
use Netrunners\Entity\MilkrunAivatarInstance;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\PlaySession;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Model\TextToImage;
use Netrunners\Repository\FeedbackRepository;
use Netrunners\Repository\InvitationRepository;
use Netrunners\Repository\PlaySessionRepository;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Crypt\Password\Bcrypt;
use Zend\I18n\Validator\Alnum;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class LoginService extends BaseService
{

    /**
     * @var MailMessageService
     */
    protected $mailMessageService;

    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        MailMessageService $mailMessageService
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->mailMessageService = $mailMessageService;
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function login($resourceId, $content)
    {
        $disconnect = false;
        $ws = $this->getWebsocketServer();
        $username = strtolower($content);
        $user = $this->entityManager->getRepository('TmoAuth\Entity\User')->findOneBy(array(
            'username' => $username
        ));
        $response = new GameClientResponse($resourceId);
        if (!$user) {
            $ws->setClientData($resourceId, 'username', $username);
            $response->setCommand(GameClientResponse::COMMAND_CONFIRMUSERCREATE);
        }
        else {
            /** @var User $user */
            $this->setUser($user);
            // check if they are banned
            if ($user->getBanned()) {
                $response->addMessage($this->translate('This account is banned from playing this game'), GameClientResponse::CLASS_DANGER);
                $disconnect = true;
            }
            // check if admin mode is active
            else if ($ws->isAdminMode() && !$this->hasRole($user, Role::ROLE_ID_ADMIN)) {
                $response->addMessage($this->translate('The game is currently in admin mode - please try again later'), GameClientResponse::CLASS_DANGER);
                $disconnect = true;
            }
            else if (count($ws->getClients()) >= WebsocketService::MAX_CLIENTS && !$this->hasRole($user, Role::ROLE_ID_ADMIN)) {
                $message = $this->translate('MAXIMUM AMOUNT OF CLIENTS REACHED - PLEASE TRY AGAIN LATER');
                $response->addMessage($message, GameClientResponse::CLASS_DANGER);
                $disconnect = true;
            }
            else {
                // not banned, populate ws client data
                $profile = $user->getProfile();
                $ws->setClientData($resourceId, 'username', $user->getUsername());
                $ws->setClientData($resourceId, 'userId', $user->getId());
                $ws->setClientData($resourceId, 'profileId', $profile->getId());
                $ws->setClientData($resourceId, 'newbieStatusDate', $profile->getNewbieStatusDate());
                $ws->setClientData($resourceId, 'mainCampaignStep', $profile->getMainCampaignStep());
                $response->setCommand(GameClientResponse::COMMAND_PROMPTFORPASSWORD);
            }
        }
        return [$response, $disconnect];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function confirmUserCreate($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $response = false;
        if ($content == 'yes' || $content == 'y') {
            $response = new GameClientResponse($resourceId);
            $validator = new Alnum();
            if ($validator->isValid($clientData->username)) {
                if (strlen($clientData->username) > 30) {
                    $response->addMessage($this->translate('Username must be between 3 and 30 characters, please try again'));
                    $disconnect = true;
                }
                else if (strlen($clientData->username) < 3) {
                    $response->addMessage($this->translate('Username must be between 3 and 30 characters, please try again'));
                    $disconnect = true;
                }
                else {
                    // ok, they want to create a new user, have them solve a captcha
                    $operators = ['+', '-', '*'];
                    $x = mt_rand(1, 20);
                    $y = mt_rand(1, 20);
                    $operatorRand = mt_rand(0, 2);
                    $operator = $operators[$operatorRand];
                    switch ($operator) {
                        default:
                            $solution = $x + $y;
                            break;
                        case '-':
                            $solution = $x - $y;
                            break;
                        case '*':
                            $solution = $x * $y;
                            break;
                    }
                    $clientData->captchasolution = $solution;
                    $ws->setClientData($resourceId, 'captchasolution', $solution);
                    $captchaImage = new TextToImage();
                    $captchaImage->createImage($x . ' ' . $operator . ' ' . $y);
                    $captchaImage->saveAsPng('captcha', getcwd() . '/public/temp/');
                    $response->setCommand(GameClientResponse::COMMAND_SOLVECAPTCHA);
                }
            }
            else {
                $response->addMessage($this->translate('Username can only contain alphanumeric characters, please try again'));
                $disconnect = true;
            }
        }
        else {
            $disconnect = true;
        }
        return [$disconnect, $response];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function solveCaptcha($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $response = false;
        if ($content == $clientData->captchasolution) {
            $response = new GameClientResponse($resourceId);
            $response->setCommand(GameClientResponse::COMMAND_ENTERINVITATIONCODE);
        }
        else {
            $disconnect = true;
        }
        return [$disconnect, $response];
    }


    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function enterInvitationCode($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $disconnect = false;
        $response = false;
        $invitationRepo = $this->entityManager->getRepository('Netrunners\Entity\Invitation');
        /** @var InvitationRepository $invitationRepo */
        $invitation = $invitationRepo->findOneUnusedByCode($content);
        if ($invitation && $content == $invitation->getCode() && !$invitation->getUsed()) {
            $ws->setClientData($resourceId, 'invitationid', $invitation->getId());
            $response = new GameClientResponse($resourceId);
            $response->setCommand(GameClientResponse::COMMAND_CREATEPASSWORD);
        }
        else {
            $disconnect = true;
        }
        return [$disconnect, $response];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function createPassword($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $disconnect = false;
        $validator = new Alnum();
        $response = new GameClientResponse($resourceId);
        if ($validator->isValid($content)) {
            if (strlen($content) > 30) {
                $message = $this->translate('Password must be between 8 and 30 characters, please try again');
                $response->addMessage($message);
                $disconnect = true;
            }
            else if (strlen($content) < 8) {
                $message = $this->translate('Password must be between 8 and 30 characters, please try again');
                $response->addMessage($message);
                $disconnect = true;
            }
            else {
                $ws->setClientData($resourceId, 'tempPassword', $content);
                $response->setCommand(GameClientResponse::COMMAND_CREATEPASSWORDCONFIRM);
            }
        }
        else {
            $message = 'Password can only contain alphanumeric characters, please try again';
            $response->addMessage($message);
            $disconnect = true;
        }
        return [$disconnect, $response];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function createPasswordConfirm($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $tempPassword = $clientData->tempPassword;
        $response = new GameClientResponse($resourceId);
        if ($tempPassword != $content) {
            $message = $this->translate('The passwords do not match, please confirm again');
            $response->addMessage($message);
            $disconnect = true;
        }
        else {
            $utilityService = $ws->getUtilityService();
            // create a new addy for the user's initial system
            $addy = $utilityService->getRandomAddress(32);
            $maxTries = 100;
            $tries = 0;
            while ($this->entityManager->getRepository('Netrunners\Entity\System')->findOneBy(['addy' => $addy])) {
                $addy = $utilityService->getRandomAddress(32);
                $tries++;
                if ($tries >= $maxTries) {
                    $message = $this->translate('Unable to create your account - try again later');
                    $response->addMessage($message, GameClientResponse::CLASS_DANGER);
                    $disconnect = true;
                    return [$disconnect, $response];
                }
            }
            // create new user
            $ws->setClientData($resourceId, 'tempPassword', false);
            $user = new User();
            $user->setUsername(strtolower($clientData->username));
            $user->setDisplayName(strtolower($clientData->username));
            $user->setEmail(NULL);
            $user->setState(1);
            $bcrypt = new Bcrypt();
            $bcrypt->setCost(10);
            $pass = $bcrypt->create($content);
            $user->setPassword($pass);
            $user->setProfile(NULL);
            $this->entityManager->persist($user);
            $profile = new Profile();
            $profile->setUser($user);
            $profile->setCredits(ProfileService::DEFAULT_STARTING_CREDITS);
            $profile->setSnippets(ProfileService::DEFAULT_STARTING_SNIPPETS);
            $profile->setEeg(100);
            $profile->setWillpower(100);
            $profile->setBlade(NULL);
            $profile->setBlaster(NULL);
            $profile->setShield(NULL);
            $profile->setHandArmor(NULL);
            $profile->setHeadArmor(NULL);
            $profile->setLegArmor(NULL);
            $profile->setLowerArmArmor(NULL);
            $profile->setShoesArmor(NULL);
            $profile->setShoulderArmor(NULL);
            $profile->setTorsoArmor(NULL);
            $profile->setUpperArmArmor(NULL);
            $profile->setStealthing(false);
            $newbieStatusDate = new \DateTime();
            $newbieStatusDate->add(new \DateInterval('P7D'));
            $profile->setNewbieStatusDate($newbieStatusDate);
            $profile->setMainCampaignStep(NULL);
            // add skills
            $skills = $this->entityManager->getRepository('Netrunners\Entity\Skill')->findAll();
            foreach ($skills as $skill) {
                /** @var Skill $skill */
                $skillRating = new SkillRating();
                $skillRating->setProfile($profile);
                $skillRating->setNpc(NULL);
                $skillRating->setRating($skill->getLevel());
                $skillRating->setSkill($skill);
                $this->entityManager->persist($skillRating);
            }
            // add default skillpoints
            $profile->setSkillPoints(ProfileService::DEFAULT_SKILL_POINTS);
            $this->entityManager->persist($profile);
            $milkrunAivatar = $this->entityManager->find('Netrunners\Entity\MilkrunAivatar', MilkrunAivatar::ID_SCROUNGER);
            $aivatar = new MilkrunAivatarInstance();
            $aivatar->setName($milkrunAivatar->getName());
            $aivatar->setProfile($profile);
            $aivatar->setCompleted(0);
            $aivatar->setCreated(new \DateTime());
            $aivatar->setCurrentArmor($milkrunAivatar->getBaseArmor());
            $aivatar->setCurrentAttack($milkrunAivatar->getBaseAttack());
            $aivatar->setCurrentEeg($milkrunAivatar->getBaseEeg());
            $aivatar->setMaxArmor($milkrunAivatar->getBaseArmor());
            $aivatar->setMaxAttack($milkrunAivatar->getBaseAttack());
            $aivatar->setMaxEeg($milkrunAivatar->getBaseEeg());
            $aivatar->setMilkrunAivatar($milkrunAivatar);
            $aivatar->setModified(NULL);
            $aivatar->setPointsearned(0);
            $aivatar->setPointsused(0);
            $aivatar->setSpecials(NULL);
            $aivatar->setUpgrades(0);
            $this->entityManager->persist($aivatar);
            $profile->setDefaultMilkrunAivatar($aivatar);
            $user->setProfile($profile);
            $defaultRole = $this->entityManager->find('TmoAuth\Entity\Role', 2);
            /** @var Role $defaultRole */
            $user->addRole($defaultRole);
            $system = new System();
            $system->setProfile($profile);
            $system->setName($user->getUsername());
            $system->setAddy($addy);
            $system->setGroup(NULL);
            $system->setFaction(NULL);
            $system->setMaxSize(System::DEFAULT_MAX_SYSTEM_SIZE);
            $system->setAlertLevel(0);
            $system->setNoclaim(true);
            $system->setGeocoords($clientData->geocoords);
            $this->entityManager->persist($system);
            // default io node
            $nodeType = $this->entityManager->find('Netrunners\Entity\NodeType', NodeType::ID_CPU);
            /** @var NodeType $nodeType */
            $ioNode = new Node();
            $ioNode->setCreated(new \DateTime());
            $ioNode->setLevel(1);
            $ioNode->setName($nodeType->getName());
            $ioNode->setSystem($system);
            $ioNode->setNodeType($nodeType);
            $this->entityManager->persist($ioNode);
            $profile->setCurrentNode($ioNode);
            $profile->setHomeNode($ioNode);
            $profile->setLocale(Profile::DEFAULT_PROFILE_LOCALE);
            // handle invitation
            if ($clientData->invitationid) {
                $invitation = $this->entityManager->find('Netrunners\Entity\Invitation', $clientData->invitationid);
                if ($invitation) {
                    $invitation->setUsed(new \DateTime());
                    $invitation->setUsedBy($profile);
                    $ws->setClientData($resourceId, 'invitationid', NULL);
                }
            }
            // flush to db
            $this->entityManager->flush();
            $hash = hash('sha256', $ws->getHash() . $user->getId());
            $ws->setClientData($resourceId, 'hash', $hash);
            $ws->setClientData($resourceId, 'userId', $user->getId());
            $ws->setClientData($resourceId, 'username', $user->getUsername());
            $ws->setClientData($resourceId, 'jobs', []);
            $response->setCommand(GameClientResponse::COMMAND_CREATEUSERDONE);
            $response->setResourceId($resourceId);
            $response->addOption(GameClientResponse::OPT_HASH, $hash);
            // inform other clients
            $informerText = sprintf(
                $this->translate('a new user [%s] has connected'),
                $user->getUsername()
            );
            foreach ($this->getWebsocketServer()->getClients() as $wsClientId => $wsClient) {
                if ($wsClient->resourceId == $resourceId) continue;
                $informer = new GameClientResponse($wsClient->resourceId);
                $informer->addMessage($informerText, GameClientResponse::CLASS_INFO);
                $informer->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
                $informer->send();
            }
            // create play-session
            $playSession = new PlaySession();
            $playSession->setProfile($profile);
            $playSession->setEnd(NULL);
            $playSession->setStart(new \DateTime());
            $playSession->setIpAddy($clientData->ipaddy);
            $playSession->setSocketId($resourceId);
            $this->entityManager->persist($playSession);
            $this->entityManager->flush($playSession);
            // send welcome mail
            $subject = $this->translate("Welcome to the NeoCortex Network");
            $content = $this->translate("Please use the \"help\" or \"newbie\" commands to get help. You will be receiving additional instructions shortly...");
            $this->mailMessageService->createMail($profile, NULL, $subject, $content, true);
        }
        return [$disconnect, $response];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function promptForPassword($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        /** @var User $user */
        $currentPassword = $user->getPassword();
        $bcrypt = new Bcrypt();
        $response = new GameClientResponse($resourceId);
        if (!$bcrypt->verify($content, $currentPassword)) {
            $response->addMessage($this->translate('Invalid password'))->setSilent(true);
            $disconnect = true;
        }
        else {
            $profile = $user->getProfile();
            $profile->setCurrentResourceId($resourceId);
            $currentNode = $profile->getCurrentNode();
            $currentSystem = $currentNode->getSystem();
            $wsClients = $ws->getClients();
            $wsClientsData = $ws->getClientsData();
            foreach ($wsClients as $client) {
                if ($client->resourceId != $resourceId && $wsClientsData[$client->resourceId]['username'] == $wsClientsData[$resourceId]['username']) {
                    $response->addMessage($this->translate('Your connection has been terminated because you are already logged in from another location'), GameClientResponse::CLASS_DANGER);
                    $disconnect = true;
                    return [$disconnect, $response];
                }
            }
            $hash = hash('sha256', $ws->getHash() . $user->getId());
            $ws->setClientData($resourceId, 'hash', $hash);
            $homeCoords = $profile->getHomeNode()->getSystem()->getGeocoords();
            $currentCoords = $currentSystem->getGeocoords();
            // get some settings
            $bgOpacity = $profile->getBgopacity();
            $response->setCommand(GameClientResponse::COMMAND_LOGINCOMPLETE);
            $response->addOption(GameClientResponse::OPT_HASH, $hash);
            $response->setSilent(true);
            $response->addOption(GameClientResponse::OPT_HOMECOORDS, explode(',', $homeCoords));
            $response->addOption(GameClientResponse::OPT_GEOCOORDS, explode(',', $currentCoords));
            $response->addOption(GameClientResponse::OPT_BGOPACITY, $bgOpacity);
            // message everyone in node
            $messageText = sprintf(
                $this->translate('%s has logged in to this node'),
                $user->getUsername()
            );
            $this->messageEveryoneInNodeNew($currentNode, $messageText, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
            // clear orphaned play-sessions and start a new one
            $playSessionRepo = $this->entityManager->getRepository('Netrunners\Entity\PlaySession');
            /** @var PlaySessionRepository $playSessionRepo */
            foreach ($playSessionRepo->findOrphaned($profile) as $orphanedPlaySession) {
                $this->entityManager->remove($orphanedPlaySession);
            }
            // show feedback info if admin or superadmin
            if ($this->hasRole($user, Role::ROLE_ID_ADMIN)) {
                $lastPlaySession = $playSessionRepo->findLastPlaySession($profile);
                if ($lastPlaySession) {
                    $feedbackRepo = $this->entityManager->getRepository('Netrunners\Entity\Feedback');
                    /** @var FeedbackRepository $feedbackRepo */
                    $feedbackCount = $feedbackRepo->countByNewForProfile($lastPlaySession->getEnd());
                    if ($feedbackCount >= 1) {
                        $message = sprintf(
                            $this->translate('There are %s new feedback messages since your last login'),
                            $feedbackCount
                        );
                        $feedbackResponse = new GameClientResponse($resourceId);
                        $feedbackResponse->setSilent(true)->addMessage($message, GameClientResponse::CLASS_ATTENTION);
                        $feedbackResponse->send();
                    }
                }
            }
            // show amount of new mail messages
            $this->mailMessageService->displayAmountUnreadMails($resourceId, true);
            // create a new play-session
            $playSession = new PlaySession();
            $playSession->setProfile($profile);
            $playSession->setEnd(NULL);
            $playSession->setStart(new \DateTime());
            $playSession->setIpAddy($wsClientsData[$resourceId]['ipaddy']);
            $playSession->setSocketId($resourceId);
            $this->entityManager->persist($playSession);
            // inform admins
            $informerText = sprintf(
                $this->translate('user [%s] has connected'),
                $user->getUsername()
            );
            $ws = $this->getWebsocketServer();
            foreach ($ws->getClients() as $wsClientId => $wsClient) {
                if ($wsClient->resourceId == $resourceId) continue;
                $xClientData = $ws->getClientData($wsClient->resourceId);
                if (!$xClientData) continue;
                if (!$xClientData->userId) continue;
                $xUser = $this->entityManager->find('TmoAuth\Entity\User', $xClientData->userId);
                if (!$xUser) continue;
                if (!$this->hasRole($xUser, Role::ROLE_ID_ADMIN)) continue;
                $informer = new GameClientResponse($wsClient->resourceId);
                $informer->addMessage($informerText, GameClientResponse::CLASS_ADDON);
                $informer->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
                $informer->send();
            }
            // commit all changes to db
            $this->entityManager->flush();
        }
        return [$disconnect, $response];
    }

}
