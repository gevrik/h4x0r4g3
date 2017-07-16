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
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\PlaySession;
use Netrunners\Entity\Profile;
use Netrunners\Entity\Skill;
use Netrunners\Entity\SkillRating;
use Netrunners\Entity\System;
use Netrunners\Model\TextToImage;
use Netrunners\Repository\FeedbackRepository;
use Netrunners\Repository\PlaySessionRepository;
use TmoAuth\Entity\Role;
use TmoAuth\Entity\User;
use Zend\Crypt\Password\Bcrypt;
use Zend\I18n\Validator\Alnum;

class LoginService extends BaseService
{

    /**
     * @param $resourceId
     * @param $content
     * @return array
     */
    public function login($resourceId, $content)
    {
        $disconnect = false;
        $ws = $this->getWebsocketServer();
        $username = strtolower($content);
        $user = $this->entityManager->getRepository('TmoAuth\Entity\User')->findOneBy(array(
            'username' => $username
        ));
        if (!$user) {
            $ws->setClientData($resourceId, 'username', $username);
            $response = array(
                'command' => 'confirmusercreate',
            );
        }
        else {
            $this->setUser($user);
            $isAdmin = $this->isAdmin();
            $isSuperAdmin = $this->isSuperAdmin();
            // check if they are banned
            if ($user->getBanned()) {
                $response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-danger">%s</pre>',
                        $this->translate('This account is banned from playing this game')
                    )
                ];
                $disconnect = true;
            }
            // check if admin mode is active
            else if ($ws->isAdminMode() && !$isAdmin && !$isSuperAdmin) {
                $response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-danger">%s</pre>',
                        $this->translate('The game is currently in admin mode - please try again later')
                    )
                ];
                $disconnect = true;
            }
            else if (count($ws->getClients()) >= WebsocketService::MAX_CLIENTS && !$isAdmin && !$isSuperAdmin) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-danger">MAXIMUM AMOUNT OF CLIENTS REACHED - PLEASE TRY AGAIN LATER</pre>')
                    )
                );
                $disconnect = true;
            }
            else {
                // not banned, populate ws client data
                $ws->setClientData($resourceId, 'username', $user->getUsername());
                $ws->setClientData($resourceId, 'userId', $user->getId());
                $ws->setClientData($resourceId, 'profileId', $user->getProfile()->getId());
                $response = array(
                    'command' => 'promptforpassword',
                );
            }
        }
        return [$response, $disconnect];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     */
    public function confirmUserCreate($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $response = false;
        if ($content == 'yes' || $content == 'y') {
            $validator = new Alnum();
            if ($validator->isValid($clientData->username)) {
                if (strlen($clientData->username) > 30) {
                    $response = array(
                        'command' => 'showmessage',
                        'message' => $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Username must be between 3 and 30 characters, please try again</pre>')
                    );
                    $disconnect = true;
                }
                else if (strlen($clientData->username) < 3) {
                    $response = array(
                        'command' => 'showmessage',
                        'message' => $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Username must be between 3 and 30 characters, please try again</pre>')
                    );
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
                    $response = array(
                        'command' => 'solvecaptcha'
                    );
                }
            }
            else {
                $response = array(
                    'command' => 'showmessage',
                    'message' => $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">Username can only contain alphanumeric characters, please try again</pre>')
                );
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
     */
    public function solveCaptcha($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $response = false;
        if ($content == $clientData->captchasolution) {
            $response = array(
                'command' => 'createpassword'
            );
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
     */
    public function createPassword($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $disconnect = false;
        $validator = new Alnum();
        if ($validator->isValid($content)) {
            if (strlen($content) > 30) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Password must be between 8 and 30 characters, please try again</pre>'
                );
                $disconnect = true;
            }
            else if (strlen($content) < 8) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Password must be between 8 and 30 characters, please try again</pre>'
                );
                $disconnect = true;
            }
            else {
                $ws->setClientData($resourceId, 'tempPassword', $content);
                $response = array(
                    'command' => 'createpasswordconfirm',
                );
            }
        }
        else {
            $response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Password can only contain alphanumeric characters, please try again</pre>'
            );
            $disconnect = true;
        }
        return [$disconnect, $response];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     */
    public function createPasswordConfirm($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $tempPassword = $clientData->tempPassword;
        if ($tempPassword != $content) {
            $response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">The passwords do not match, please confirm again</pre>'
            );
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
                    $response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Unable to initialize your account! Please contact an administrator!</pre>'
                    );
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
            // flush to db
            $this->entityManager->flush();
            $hash = hash('sha256', $ws->getHash() . $user->getId());
            $ws->setClientData($resourceId, 'hash', $hash);
            $ws->setClientData($resourceId, 'userId', $user->getId());
            $ws->setClientData($resourceId, 'username', $user->getUsername());
            $ws->setClientData($resourceId, 'jobs', []);
            $response = array(
                'command' => 'createuserdone',
                'hash' => $hash,
                'prompt' => $ws->getUtilityService()->showPrompt($ws->getClientData($resourceId))
            );
            // inform other clients
            $informer = array(
                'command' => 'showmessageprepend',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-info">a new user [%s] has connected</pre>'),
                    $user->getUsername()
                )
            );
            foreach ($this->getWebsocketServer()->getClients() as $wsClientId => $wsClient) {
                if ($wsClient->resourceId == $resourceId) continue;
                $wsClient->send(json_encode($informer));
            }
            // create play-session
            $playSession = new PlaySession();
            $playSession->setProfile($profile);
            $playSession->setEnd(NULL);
            $playSession->setStart(new \DateTime());
            $playSession->setIpAddy($this->clientData->ipaddy);
            $playSession->setSocketId($resourceId);
            $this->entityManager->persist($playSession);
            $this->entityManager->flush($playSession);
        }
        return [$disconnect, $response];
    }

    /**
     * @param $resourceId
     * @param $content
     * @return array
     */
    public function promptForPassword($resourceId, $content)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $disconnect = false;
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        $currentPassword = $user->getPassword();
//        var_dump('before bcrypt');
        $bcrypt = new Bcrypt();
        if (!$bcrypt->verify($content, $currentPassword)) {
//            var_dump('failed');
            $response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">Invalid password</pre>',
            );
            $disconnect = true;
        }
        else {
//            var_dump('pw ok');
            $wsClients = $ws->getClients();
            $wsClientsData = $ws->getClientsData();
            foreach ($wsClients as $client) {
                if ($client->resourceId != $resourceId && $wsClientsData[$client->resourceId]['username'] == $wsClientsData[$resourceId]['username']) {
                    $response = array(
                        'command' => 'showmessage',
                        'message' => '<pre style="white-space: pre-wrap;" class="text-danger">Your connection has been terminated because you logged in from another location</pre>'
                    );
                    $disconnect = true;
                    return [$disconnect, $response];
                }
            }
//            var_dump('before hash');
            $hash = hash('sha256', $ws->getHash() . $user->getId());
            $ws->setClientData($resourceId, 'hash', $hash);
            $response = array(
                'command' => 'logincomplete',
                'hash' => $hash,
                'prompt' => $ws->getUtilityService()->showPrompt($clientData)
            );
            // message everyone in node
            $messageText = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">%s has logged in to this node</pre>'),
                $user->getUsername()
            );
            $message = array(
                'command' => 'showmessageprepend',
                'message' => $messageText
            );
            $this->messageEveryoneInNode($user->getProfile()->getCurrentNode(), $message, $user->getProfile());
//            var_dump('messaged everyone in node');
            // clear orphaned play-sessions and start a new one
//            var_dump('before playsession cleaner');
            $playSessionRepo = $this->entityManager->getRepository('Netrunners\Entity\PlaySession');
            /** @var PlaySessionRepository $playSessionRepo */
            foreach ($playSessionRepo->findOrphaned($user->getProfile()) as $orphanedPlaySession) {
                $this->entityManager->remove($orphanedPlaySession);
            }
//            var_dump('after playsession cleaner');
            $playSession = new PlaySession();
            $playSession->setProfile($user->getProfile());
            $playSession->setEnd(NULL);
            $playSession->setStart(new \DateTime());
            $playSession->setIpAddy($wsClientsData[$resourceId]['ipaddy']);
            $playSession->setSocketId($resourceId);
            $this->entityManager->persist($playSession);
            $this->entityManager->flush();
//            var_dump('after playsession creater');
            // show feedback info if admin or superadmin
//            if ($this->isSuperAdmin($user) || $this->isAdmin($user)) {
//                var_dump('admin show feedback');
//                $lastPlaySession = $playSessionRepo->findLastPlaySession($user->getProfile());
//                if ($lastPlaySession) {
//                    $feedbackRepo = $this->entityManager->getRepository('Netrunners\Entity\Feedback');
//                    /** @var FeedbackRepository $feedbackRepo */
//                    $feedbackCount = $feedbackRepo->countByNewForProfile($lastPlaySession->getEnd());
//                    $feedbackMessage = [
//                        'command' => 'showmessage',
//                        'silent' => true,
//                        'message' => sprintf(
//                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">There are %s new feedback messages since your last login</pre>'),
//                            $feedbackCount
//                        )
//                    ];
//                    foreach ($wsClients as $xClientId => $xClient) {
//                        if ($xClient->resourceId == $resourceId) {
//                            $xClient->send(json_encode($feedbackMessage));
//                        }
//                    }
//                }
//                var_dump('after admin show feedback');
//            }
        }
//        var_dump('before return');
        return [$disconnect, $response];
    }

}
