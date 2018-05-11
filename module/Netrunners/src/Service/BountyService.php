<?php

/**
 * Bounty Service.
 * The service supplies methods that resolve logic around bounties.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Bounty;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\BountyRepository;
use Netrunners\Repository\ProfileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

/**
 * Class BountyService
 * @package Netrunners\Service
 */
final class BountyService extends BaseService
{

    /**
     * @var BountyRepository
     */
    protected $bountyRepo;

    /** @var ProfileRepository $profileRepo */
    protected $profileRepo;


    /**
     * BountyService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->bountyRepo = $this->entityManager->getRepository(Bounty::class);
        $this->profileRepo = $this->entityManager->getRepository(Profile::class);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|\Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showBounties($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($this->user->getProfile()->getCurrentNode()->getNodeType()->getId() != NodeType::ID_BB) {
            $message = $this->translate('You must be in a bulletin-board node to view current bounties');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        list($contentArray, $limit) = $this->getNextParameter($contentArray, true, true);
        $offset = $this->getNextParameter($contentArray, false, true);
        $bounties = $this->bountyRepo->findForShowBountiesCommand($limit, $offset);
        $returnMessage = sprintf(
            '%-11s|%-20s',
            $this->translate('USERNAME'),
            $this->translate('TOTAL-BOUNTY')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($bounties as $bounty) {
            $returnMessage = sprintf(
                '%-11s|%-20s',
                $bounty['username'],
                $this->numberFormat($bounty['totalamount'], $profile->getLocale())
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function postBounty($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($this->user->getProfile()->getCurrentNode()->getNodeType()->getId() != NodeType::ID_BB) {
            $message = $this->translate('You must be in a bulletin-board node to post a bounty');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        list($contentArray, $targetName) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$targetName) {
            $message = $this->translate('Please specify the username to put a bounty on');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $target = $this->profileRepo->findLikeName($targetName);
        if (!$target) {
            $message = $this->translate('Invalid bounty target - unknown user');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($target === $profile) {
            $message = $this->translate('We are starting to worry about you...');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $bountyAmount = $this->getNextParameter($contentArray, false, true);
        if (!$bountyAmount) {
            $message = $this->translate('Please specify an amount for the bounty');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $bountyAmount = $this->checkValueMinMax($bountyAmount, 1);
        $profile = $this->user->getProfile();
        if ($profile->getCredits() < $bountyAmount) {
            $message = $this->translate('You do not have that many credits');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $profile->setCredits($profile->getCredits() - $bountyAmount);
        $bounty = new Bounty();
        $bounty->setAdded(new \DateTime());
        $bounty->setAmount($bountyAmount);
        $bounty->setClaimed(null);
        $bounty->setClaimer(null);
        $bounty->setPlacer($profile);
        $bounty->setTarget($target);
        $this->entityManager->persist($bounty);
        $this->entityManager->flush($bounty);
        $this->entityManager->flush($profile);
        $xmessage = sprintf(
            $this->translate('[<span class="text-white">%s</span>] has just placed a bounty of %s on you'),
            $this->user->getUsername(),
            $this->numberFormat($bountyAmount, $profile->getLocale())
        );
        $this->storeNotification($target, $xmessage, GameClientResponse::CLASS_INFO);
        $message = sprintf(
            $this->translate('You have placed a bounty of %s on [<span class="text-white">%s</span>]'),
            $this->numberFormat($bountyAmount, $profile->getLocale()),
            $target->getUser()->getUsername()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

}
