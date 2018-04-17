<?php

/**
 * MailMessage Service.
 * The service supplies methods that resolve logic around MailMessage objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\FileType;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Repository\NodeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class MainCampaignService extends BaseService
{

    const STEP_NOT_STARTED = NULL;
    const STEP_STARTED = 1;
    const STEP_ABSOLUTE_BASICS = 2;
    const STEP_NIX_FOLLOW_UP = 3;
    const STEP_MORE_BASICS = 4;
    const STEP_NIX_PROGRAMS = 5;
    const STEP_FIRST_MILKRUN = 6;
    const STEP_FIRST_MILKRUN_NIX = 7;
    const STEP_FIRST_MISSION = 8;
    const STEP_COMPLETED = 10000;
    const MAIN_CAMPAIGN_STEP = 'mainCampaignStep';
    const MAIN_CAMPAIGN_STEP_ACTIVATION_DATE = 'mainCampaignStepActivationDate';

    const RUN_CAMPAIGN = true;

    /**
     * @var MailMessageService
     */
    protected $mailMessageService;

    /**
     * @var NodeRepository
     */
    protected $nodeRepository;


    /**
     * MailMessageService constructor.
     * @param EntityManager $entityManager
     * @param MailMessageService $mailMessageService
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        MailMessageService $mailMessageService,
        PhpRenderer $viewRenderer,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->mailMessageService = $mailMessageService;
        $this->nodeRepository = $this->entityManager->getRepository('Netrunners\Entity\Node');
    }

    /**
     * @param $resourceId
     * @param $clientData
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function checkMainCampaignStep($resourceId, $clientData)
    {
        /** @var Profile $profile */
        $profile = $this->entityManager->find(Profile::class, $clientData->profileId);
        switch ($clientData->mainCampaignStep) {
            default:
                break;
            case self::STEP_NOT_STARTED:
                $this->sendStarterMail($resourceId, $clientData);
                break;
            case self::STEP_STARTED:
                $now = new \DateTime();
                if ($clientData->mainCampaignStepActivationDate > $now) continue;
                $this->sendFollowUpMail($resourceId, $clientData);
                break;
            case self::STEP_ABSOLUTE_BASICS:
                $now = new \DateTime();
                if ($clientData->mainCampaignStepActivationDate > $now) continue;
                $this->sendNixFollowUpMail($resourceId, $clientData);
                break;
            case self::STEP_NIX_FOLLOW_UP:
                if ($profile) {
                    $system = $profile->getHomeNode()->getSystem();
                    $storageNodeCount = $this->nodeRepository->countBySystemAndType($system, NodeType::ID_STORAGE);
                    $memoryNodeCount = $this->nodeRepository->countBySystemAndType($system, NodeType::ID_MEMORY);
                    if ($storageNodeCount > 0 && $memoryNodeCount > 0) {
                        $this->sendBasicPrograms($profile);
                    }
                }
                break;
            case self::STEP_MORE_BASICS:
                $now = new \DateTime();
                if ($clientData->mainCampaignStepActivationDate > $now) continue;
                if ($profile) {
                    $this->sendNixProgramMail($profile);
                }
                break;
            case self::STEP_NIX_PROGRAMS:
                if ($profile) {
                    $system = $profile->getHomeNode()->getSystem();
                    $databaseNodeCount = $this->nodeRepository->countBySystemAndType($system, NodeType::ID_DATABASE);
                    $terminalNodeCount = $this->nodeRepository->countBySystemAndType($system, NodeType::ID_TERMINAL);
                    $privateIoCount = $this->nodeRepository->countBySystemAndType($system, NodeType::ID_IO);
                    if ($databaseNodeCount > 0 && $terminalNodeCount > 0 && $privateIoCount > 0) {
                        $this->sendAgentInstructions($profile);
                    }
                }
                break;
            case self::STEP_FIRST_MILKRUN:
                $now = new \DateTime();
                if ($clientData->mainCampaignStepActivationDate > $now) continue;
                if ($profile) {
                    $this->sendNixMilkrunMail($profile);
                }
                break;
            case self::STEP_FIRST_MILKRUN_NIX:
                if ($profile) {
                    $milkrunCount = $profile->getCompletedMilkruns();
                    if ($milkrunCount > 0) {
                        $this->sendMissionInstructions($profile);
                    }
                }
                break;
            case self::STEP_FIRST_MISSION:
                if ($profile) {
                    $missionCount = $profile->getCompletedMissions();
                    if ($missionCount > 0) {
                        // TODO continue here
                    }
                }
                break;
        }
    }

    /**
     * @param $resourceId
     * @param $clientData
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    private function sendStarterMail($resourceId, $clientData)
    {
        $ws = $this->getWebsocketServer();
        /** @var Profile $profile */
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $clientData->profileId);
        $user = $profile->getUser();
        $username = $user->getUsername();
        $subject = $this->translate("Don't believe their lies...");
        $content = <<<EOD
Hoi, $username

my name is NIX, dont't believe anything NeoCortex tells you...

I will show you proof of their deception soon... just hang in there for a while,
I'll get back to you... just don't do anything stupid until then!
EOD;
        $this->mailMessageService->createMail($profile, NULL, $subject, $content);
        $activationDate = new \DateTime();
        $activationDate->add(new \DateInterval('PT10M'));
        $profile->setMainCampaignStep(self::STEP_STARTED);
        $profile->setMainCampaignStepActivationDate($activationDate);
        $ws->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP, self::STEP_STARTED);
        $ws->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, $activationDate);
    }

    /**
     * @param $resourceId
     * @param $clientData
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    private function sendFollowUpMail($resourceId, $clientData)
    {
        $ws = $this->getWebsocketServer();
        /** @var Profile $profile */
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $clientData->profileId);
        $user = $profile->getUser();
        $username = $user->getUsername();
        $subject = $this->translate("Getting you started!");
        $content = <<<EOD
Welcome again, $username

we are happy to have you as a new member of the NeoCortex Network.

To get you started on your ventures, we recommand that you build at least
one memory and one storage node so that you can store and execute programs.

You will also need a private input/output node if you want to connect to 
other systems.

If you need help, please use the "help" command for detailed instructions.

We will monitor your progress and contact you again once you have added the
recommended nodes to your system.
EOD;
        $this->mailMessageService->createMail($profile, NULL, $subject, $content);
        $activationDate = new \DateTime();
        $activationDate->add(new \DateInterval('PT5M'));
        $profile->setMainCampaignStep(self::STEP_ABSOLUTE_BASICS);
        $profile->setMainCampaignStepActivationDate($activationDate);
        $ws->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP, self::STEP_ABSOLUTE_BASICS);
        $ws->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, $activationDate);
    }

    /**
     * @param $resourceId
     * @param $clientData
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    private function sendNixFollowUpMail($resourceId, $clientData)
    {
        $ws = $this->getWebsocketServer();
        /** @var Profile $profile */
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $clientData->profileId);
        $subject = $this->translate("concerning: Getting you started!");
        $content = <<<EOD
That was actually the only good advice they'll ever give you. Add these nodes
to your system and try to earn some credits for an egocasting node. It will be
very important for our future communications...

I'll be in touch once you have one available.
*NIX
EOD;
        $this->mailMessageService->createMail($profile, NULL, $subject, $content);
        $profile->setMainCampaignStep(self::STEP_NIX_FOLLOW_UP);
        $profile->setMainCampaignStepActivationDate(null);
        $ws->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP, self::STEP_NIX_FOLLOW_UP);
        $ws->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, null);
    }

    /**
     * @param Profile $profile
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    private function sendBasicPrograms(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        $user = $profile->getUser();
        $username = $user->getUsername();
        $subject = $this->translate("Well done! Here are some freebies.");
        $content = <<<EOD
Welcome again, $username

your system is progressing nicely. To reward you for your effort, you will find
some free programs that you will need for future assignments attached to
this message.

If you need more storage or memory, you can build additional nodes or upgrade
your existing nodes.

We are also adding 2.000 credits to your funds so that you can expand your
system with one database node and one terminal node. These nodes will supply
a slow but steady credit and snippet income.

You will also need to complete a private input/output node to connect to other
systems. We recommend a private i/o node; it is more expensive but also more
secure. We will be in touch again once you have added these three node types
to your system. 
EOD;
        $jhType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_JACKHAMMER);
        $psType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_PORTSCANNER);
        $lpType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_LOCKPICK);
        $jackhammer = $this->createFile(
            $jhType,
            false,
            null,
            15,
            50,
            false,
            50,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            1
        );
        $portscanner = $this->createFile(
            $psType,
            false,
            null,
            15,
            50,
            false,
            50,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            1
        );
        $lockpick = $this->createFile(
            $lpType,
            false,
            null,
            15,
            50,
            false,
            50,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            1
        );
        $this->mailMessageService->createMail(
            $profile,
            NULL,
            $subject,
            $content,
            false,
            [$jackhammer, $portscanner, $lockpick]
        );
        $activationDate = new \DateTime();
        $activationDate->add(new \DateInterval('PT5M'));
        $profile->setMainCampaignStep(self::STEP_MORE_BASICS);
        $profile->setMainCampaignStepActivationDate($activationDate);
        $profile->setCredits($profile->getCredits() + 2000);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP, self::STEP_MORE_BASICS);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, $activationDate);
    }

    /**
     * @param Profile $profile
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    private function sendNixProgramMail(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        $subject = $this->translate("Well done indeed...");
        $content = <<<EOD
Can you believe these guys? Oh well, those programs show exactly what they're
up to... A bunch of crude and very noisy intrusion programs, talking about
sending the lambs to the slaughter...

No doubt they'll want you to use those soon, but let me tell you, there are
other ways to make credits and snippets... but it's your choice. Whatever
you'll decide to do, you will need some real programs to keep you a bit safer.

I've attached a couple of equipment programs - nothing fancy, I can't afford
to raise the networks suspicions by sending anything more complex... You might
need some more storage and memory to use those, though.

How is that ego-casting node coming along?
*NIX
EOD;
        $cloakType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_CLOAK);
        $bladeType = $this->entityManager->find('Netrunners\Entity\FileType', FileType::ID_CODEBLADE);
        $cloak = $this->createFile(
            $cloakType,
            false,
            'see_nix',
            10,
            50,
            false,
            50,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            1,
            1
        );
        $blade = $this->createFile(
            $bladeType,
            false,
            'nixxer',
            10,
            50,
            false,
            50,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            1,
            1
        );
        $this->mailMessageService->createMail(
            $profile,
            null,
            $subject,
            $content,
            false,
            [$cloak, $blade]
        );
        $profile->setMainCampaignStep(self::STEP_NIX_PROGRAMS);
        $profile->setMainCampaignStepActivationDate(null);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP, self::STEP_NIX_PROGRAMS);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, null);
    }

    /**
     * @param Profile $profile
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    private function sendAgentInstructions(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        $user = $profile->getUser();
        $username = $user->getUsername();
        $subject = $this->translate("System Updates");
        $content = <<<EOD
Welcome again, $username

it is nice to see your system flourish. You have done well and are on your
way to greatness. We have rewarded you with another 1.000 credits for your 
hard work. Before we will send you out into the field, we would like you to 
add an agent node to your system.

Once you have completed this task, use the command "milkrun" in the agent
node and complete at least one of those milkruns.

We will be in touch once you have completed the run to start your first
fully-fledged mission. 
EOD;
        $this->mailMessageService->createMail(
            $profile,
            NULL,
            $subject,
            $content,
            false
        );
        $activationDate = new \DateTime();
        $activationDate->add(new \DateInterval('PT2M'));
        $profile->setMainCampaignStep(self::STEP_FIRST_MILKRUN);
        $profile->setMainCampaignStepActivationDate($activationDate);
        $profile->setCredits($profile->getCredits() + 1000);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP, self::STEP_FIRST_MILKRUN);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, $activationDate);
    }

    /**
     * @param Profile $profile
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function sendNixMilkrunMail(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        $subject = $this->translate("Milkruns, ey?");
        $content = <<<EOD
Guess they've moved away from the cannon-fodder approach and are now
trying something different. Honestly, doing milkruns is not a bad idea for
a newbie like you, easy money and faction rating.

Make sure you don't take on runs against factions that you want to stay
friendly with, though. Listen, I have to go dark for a while, I'll be in
touch, don't forget about that ego-casting node...
*NIX 
EOD;
        $this->mailMessageService->createMail(
            $profile,
            NULL,
            $subject,
            $content,
            false
        );
        $profile->setMainCampaignStep(self::STEP_FIRST_MILKRUN_NIX);
        $profile->setMainCampaignStepActivationDate(null);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP, self::STEP_FIRST_MILKRUN_NIX);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, null);
    }

    /**
     * @param Profile $profile
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    private function sendMissionInstructions(Profile $profile)
    {
        $ws = $this->getWebsocketServer();
        $user = $profile->getUser();
        $username = $user->getUsername();
        $subject = $this->translate("Mission Updates");
        $content = <<<EOD
Welcome again, $username

Now that you have completed your first milkrun, it is time to send you on
your first real mission.

Go back to your agent node and use the command "mission" to see a mission
assignment that is available to you.

To complete the mission you will need the programs that we have
previously sent you. Make good use of them. We will be in touch once you
have completed your first mission.
EOD;
        $this->mailMessageService->createMail(
            $profile,
            NULL,
            $subject,
            $content,
            false
        );
        $profile->setMainCampaignStep(self::STEP_FIRST_MISSION);
        $profile->setMainCampaignStepActivationDate(null);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP, self::STEP_FIRST_MISSION);
        $ws->setClientData($profile->getCurrentResourceId(), self::MAIN_CAMPAIGN_STEP_ACTIVATION_DATE, null);
    }

}
