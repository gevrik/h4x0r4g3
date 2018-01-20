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
use Netrunners\Entity\Profile;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class MainCampaignService extends BaseService
{

    const STEP_NOT_STARTED = NULL;
    const STEP_STARTED = 1;
    const STEP_COMPLETED = 10000;
    const MAIN_CAMPAIGN_STEP = 'mainCampaignStep';

    /**
     * @var MailMessageService
     */
    protected $mailMessageService;


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
        switch ($clientData->mainCampaignStep) {
            default:
                break;
            case self::STEP_NOT_STARTED:
                $this->sendStarterMail($resourceId, $clientData);
                break;
            case self::STEP_STARTED:
                $this->sendFollowUpMail($resourceId, $clientData);
                break;
        }
    }

    /**
     * @param $resourceId
     * @param $clientData
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function sendStarterMail($resourceId, $clientData)
    {
        /** @var Profile $profile */
        $profile = $this->entityManager->find('Netrunners\Entity\Profile', $clientData->profileId);
        $user = $profile->getUser();
        $username = $user->getUsername();
        $subject = $this->translate("Don't believe their lies...");
        $content = <<<EOD
Hoi, $username
my name is NIX, dont't believe anything NeoCortex tells you...
I will show you proof of their deceit soon... just hang in there for a while,
I'll get back to you... just don't do anything stupid until then!
EOD;
        $this->mailMessageService->createMail($profile, NULL, $subject, $content);
        $profile->setMainCampaignStep(self::STEP_STARTED);
        $this->getWebsocketServer()->setClientData($resourceId, self::MAIN_CAMPAIGN_STEP, self::STEP_STARTED);
    }

    private function sendFollowUpMail($resourceId, $clientData)
    {

    }

}
