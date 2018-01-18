<?php

/**
 * Auction Service.
 * The service supplies methods that resolve logic around auction objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Auction;
use Netrunners\Entity\AuctionBid;
use Netrunners\Entity\File;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Notification;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\AuctionBidRepository;
use Netrunners\Repository\AuctionRepository;
use Netrunners\Repository\FileCategoryRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class AuctionService extends BaseService
{

    /**
     * @var AuctionRepository
     */
    protected $auctionRepo;

    /**
     * @var AuctionBidRepository
     */
    protected $bidRepo;

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var FileTypeRepository
     */
    protected $fileTypeRepo;

    /**
     * @var FileCategoryRepository
     */
    protected $fileCategoryRepo;


    /**
     * AuctionService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->auctionRepo = $this->entityManager->getRepository('Netrunners\Entity\Auction');
        $this->bidRepo = $this->entityManager->getRepository('Netrunners\Entity\AuctionBid');
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->fileTypeRepo = $this->entityManager->getRepository('Netrunners\Entity\FileType');
        $this->fileCategoryRepo = $this->entityManager->getRepository('Netrunners\Entity\FileCategory');
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listAuctions($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($currentNode->getNodeType()->getId() != NodeType::ID_MARKET) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in a market node to list auctions'))->send();
        }
        $auctions = $this->auctionRepo->findActiveByNode($currentNode);
        $returnMessage = sprintf(
            '%-11s|%-20s|%-32s|%-32s|%-3s|%-3s|%-11s|%-11s|%-19s',
            $this->translate('ID'),
            $this->translate('SELLER'),
            $this->translate('TYPE'),
            $this->translate('NAME'),
            $this->translate('LVL'),
            $this->translate('INT'),
            $this->translate('CURRENT'),
            $this->translate('BUYOUT'),
            $this->translate('EXPIRES')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($auctions as $auction) {
            /** @var Auction $auction */
            $auctionFile = $auction->getFile();
            $auctioneer = $auction->getAuctioneer();
            $returnMessage = sprintf(
                '%-11s|%-20s|%-32s|%-32s|%-3s|%-3s|%-11s|%-11s|%-19s',
                $auction->getId(),
                $auctioneer->getUser()->getUsername(),
                $auctionFile->getFileType()->getName(),
                $auctionFile->getName(),
                $auctionFile->getLevel(),
                $auctionFile->getMaxIntegrity(),
                $auction->getCurrentPrice(),
                $auction->getBuyoutPrice(),
                $auction->getExpires()->format('Y/m/d H:i:s')
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function auctionFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        if ($currentNode->getNodeType()->getId() != NodeType::ID_MARKET) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in a market node to auction a file'))->send();
        }
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true, false, false, true);
        $checkResult = $this->auctionFileChecks($parameter);
        if (!$checkResult instanceof File) {
            return $this->gameClientResponse->addMessage($checkResult)->send();
        }
        $file = $checkResult;
        list($contentArray, $startingPrice) = $this->getNextParameter($contentArray, true, true);
        $buyoutPrice = $this->getNextParameter($contentArray, false, true);
        if (!$startingPrice) $startingPrice = 0;
        if (!$buyoutPrice) $buyoutPrice = 0;
        if ($buyoutPrice == 0 && $startingPrice == 0) {
            $startingPrice = 1;
        }
        $buyoutPrice = $this->checkValueMinMax($buyoutPrice, 0, NULL);
        $startingPrice = $this->checkValueMinMax($startingPrice, 0, NULL);
        $expires = new \DateTime();
        $expires->modify('+1 week');
        $auction = new Auction();
        $auction->setAdded(new \DateTime());
        $auction->setAuctioneer($profile);
        $auction->setBought(NULL);
        $auction->setBuyer(NULL);
        $auction->setBuyoutPrice($buyoutPrice);
        $auction->setCurrentPrice($startingPrice);
        $auction->setExpires($expires);
        $auction->setFile($file);
        $auction->setNode($currentNode);
        $auction->setStartingPrice($startingPrice);
        $this->entityManager->persist($auction);
        $this->entityManager->flush($auction);
        $file->setProfile(NULL);
        $file->setSystem(NULL);
        $file->setNode(NULL);
        $file->setMailMessage(NULL);
        $this->entityManager->flush($file);
        $message = sprintf(
            $this->translate('[%s] has been put up for auction'),
            $file->getName()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $parameter
     * @return mixed|File|null|string
     */
    private function auctionFileChecks($parameter)
    {
        $profile = $this->user->getProfile();
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $parameter);
        if (count($targetFiles) < 1) {
            return $this->translate('No such file');
        }
        $file = array_shift($targetFiles);
        /** @var File $file */
        // check if the file belongs to the profile
        if ($file->getProfile() != $profile) {
            return $this->translate('Permission denied');
        }
        if ($file->getRunning()) {
            return $this->translate('Unable to auction running file - please kill the process first');
        }
        if ($file->getSystem()) {
            return $this->translate('Unable to auction file - please unload it first');
        }
        if ($file->getNode()) {
            return $this->translate('Unable to auction file - please unload it first');
        }
        // TODO auction fee?
        return $file;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function bidOnAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        list($contentArray, $auctionId) = $this->getNextParameter($contentArray, true, true);
        if (!$auctionId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the auction id'))->send();
        }
        // get auction
        $auction = $this->auctionRepo->find($auctionId);
        if (!$auction) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid auction id'))->send();
        }
        /** @var Auction $auction */
        $now = new \DateTime();
        if ($auction->getExpires() < $now) {
            return $this->gameClientResponse->addMessage($this->translate('That auction has expired'))->send();
        }
        if ($auction->getBought() !== NULL) {
            return $this->gameClientResponse->addMessage($this->translate('That auction is no longer active'))->send();
        }
        if ($auction->getStartingPrice() === 0) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to bid on buyout-only auctions'))->send();
        }
        if ($auction->getNode() != $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in the market node that the auction was posted in'))->send();
        }
        if ($auction->getAuctioneer() === $profile) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to bid on your own auctions'))->send();
        }
        /** @var Auction $auction */
        $currentBid = $this->bidRepo->findByAuctionAndProfile($auction, $profile);
        /** @var AuctionBid $currentBid */
        $bid = $this->getNextParameter($contentArray, false, true);
        if (!$bid) {
            $bid = $auction->getCurrentPrice() + 1;
        }
        else {
            $bid = $this->checkValueMinMax($bid, 1, NULL);
        }
        if ($bid <= $auction->getCurrentPrice()) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to bid less than the current bid'))->send();
        }
        if ($currentBid) {
            $toPay = $bid - $currentBid->getBid();
        }
        else {
            $toPay = $bid;
        }
        if ($toPay > $profile->getCredits()) {
            $message = sprintf(
                $this->translate('You do not have enough credits for that high of a bid - needed: %s'),
                $toPay
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good, we can bid on the auction
        if ($currentBid) {
            $currentBid->setBid($bid);
            $currentBid->setModified($now);
        }
        else {
            $currentBid = new AuctionBid();
            $currentBid->setModified(NULL);
            $currentBid->setBid($bid);
            $currentBid->setProfile($profile);
            $currentBid->setAdded($now);
            $currentBid->setAuction($auction);
            $this->entityManager->persist($currentBid);
        }
        $profile->setCredits($profile->getCredits()-$toPay);
        $auction->setCurrentPrice($bid);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have bid on auction#%s and raised the current price to %s credits - %s credits spent'),
            $auction->getId(),
            $bid,
            $toPay
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function buyoutAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $auctionId = $this->getNextParameter($contentArray, false, true);
        if (!$auctionId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the auction id'))->send();
        }
        $auction = $this->auctionRepo->find($auctionId);
        if (!$auction) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid auction id'))->send();
        }
        /** @var Auction $auction */
        $now = new \DateTime();
        if ($auction->getExpires() < $now) {
            return $this->gameClientResponse->addMessage($this->translate('That auction has expired'))->send();
        }
        if ($auction->getBought() !== NULL) {
            return $this->gameClientResponse->addMessage($this->translate('That auction is no longer active'))->send();
        }
        if ($auction->getBuyoutPrice() === 0) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to buyout bid-only auctions'))->send();
        }
        if ($auction->getNode() != $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in the market node that the auction was posted in'))->send();
        }
        if ($auction->getAuctioneer() === $profile) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to buyout your own auctions'))->send();
        }
        $toPay = $auction->getBuyoutPrice();
        if ($toPay > $profile->getCredits()) {
            $message = sprintf(
                $this->translate('You do not have enough credits to buy-out the auction - needed: %s'),
                $toPay
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good, we can bid on the auction
        $profile->setCredits($profile->getCredits()-$toPay);
        $auction->setBuyer($profile);
        $auction->setBought($now);
        $this->refundBidders($auction);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have bought-out auction#%s for %s credits'),
            $auction->getId(),
            $toPay
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param Auction $auction
     * @param Profile|NULL $buyer
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function refundBidders(Auction $auction, Profile $buyer = NULL)
    {
        $bids = $this->bidRepo->findByAuction($auction);
        foreach ($bids as $bid) {
            /** @var AuctionBid $bid */
            $bidder = $bid->getProfile();
            if ($buyer === $bidder) continue;
            $bidder->setBankBalance($bidder->getBankBalance()+$bid->getBid());
            $message = sprintf(
                $this->translate('Unfortunately you did not win auction#%s for [%s] - you have been refunded %sc to your bank balance'),
                $auction->getId(),
                $auction->getFile()->getName(),
                $bid->getBid()
            );
            $this->storeNotification(
                $bidder,
                $message,
                Notification::SEVERITY_INFO
            );
        }
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function claimAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $auctionId = $this->getNextParameter($contentArray, false, true);
        if (!$auctionId) {
            $claimableAuctions = $this->auctionRepo->findClaimableForProfile($profile);
            if (count($claimableAuctions) < 1) {
                return $this->gameClientResponse->addMessage($this->translate('No claimable auctions'))->send();
            }
            else {
                $returnMessage = sprintf(
                    '%-11s|%-32s',
                    $this->translate('ID'),
                    $this->translate('NAME')
                );
                $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
                foreach ($claimableAuctions as $claimableAuction) {
                    /** @var Auction $claimableAuction */
                    $auctionFile = $claimableAuction->getFile();
                    $returnMessage = sprintf(
                        '%-11s|%-32s',
                        $claimableAuction->getId(),
                        $auctionFile->getName()
                    );
                    $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
                }
                return $this->gameClientResponse->send();
            }
        }
        $auction = $this->auctionRepo->find($auctionId);
        if (!$auction) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid auction id'))->send();
        }
        /** @var Auction $auction */
        $now = new \DateTime();
        if ($auction->getBought() == NULL ) {
            return $this->gameClientResponse->addMessage($this->translate('That auction is still active'))->send();
        }
        if ($auction->getBuyer() !== $profile) {
            return $this->gameClientResponse->addMessage($this->translate('You did not win that auction'))->send();
        }
        if ($auction->getNode() != $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in the market node that the auction was posted in'))->send();
        }
        if ($auction->getClaimed()) {
            return $this->gameClientResponse->addMessage($this->translate('You have already claimed that auction'))->send();
        }
        if (!$this->canStoreFile($profile, $auction->getFile())) {
            return $this->gameClientResponse->addMessage($this->translate('You do not have enough storage space to store the file'))->send();
        }
        // all good, we can claim the auction
        $auction->setClaimed($now);
        $file = $auction->getFile();
        $file->setProfile($profile);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have claimed auction#%s [%s]'),
            $auction->getId(),
            $auction->getFile()->getName()
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function cancelAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $auctionId = $this->getNextParameter($contentArray, false, true);
        if (!$auctionId) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify the auction id'))->send();
        }
        $auction = $this->auctionRepo->find($auctionId);
        if (!$auction) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid auction id'))->send();
        }
        /** @var Auction $auction */
        $now = new \DateTime();
        if ($auction->getExpires() < $now) {
            return $this->gameClientResponse->addMessage($this->translate('That auction has expired'))->send();
        }
        if ($auction->getBought() !== NULL) {
            return $this->gameClientResponse->addMessage($this->translate('That auction is no longer active'))->send();
        }
        if ($auction->getNode() != $currentNode) {
            return $this->gameClientResponse->addMessage($this->translate('You need to be in the market node that the auction was posted in'))->send();
        }
        if ($auction->getAuctioneer() !== $profile) {
            return $this->gameClientResponse->addMessage($this->translate('That is not your auction'))->send();
        }
        if ($auction->getBuyoutPrice() && $this->bidRepo->countByAuction($auction) >= 1) {
            return $this->gameClientResponse->addMessage($this->translate('Auctions with bids can not be cancelled'))->send();
        }
        if (!$this->canStoreFile($profile, $auction->getFile())) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to cancel auction - you do not have enough storage space to store the file'))->send();
        }
        /** @var Auction $auction */
        $auctionedFile = $auction->getFile();
        $auctionedFile->setProfile($profile);
        $this->entityManager->remove($auction);
        $this->entityManager->flush();
        $message = sprintf(
            $this->translate('You have cancelled auction#%s'),
            $auctionId
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showBids($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $bids = $this->bidRepo->findActiveByProfile($profile);
        $returnMessage = sprintf(
            '%-11s|%-32s|%-20s|%-3s|%-3s|%-11s|%-11s',
            $this->translate('ID'),
            $this->translate('NAME'),
            $this->translate('TYPE'),
            $this->translate('LVL'),
            $this->translate('INT'),
            $this->translate('HIGHEST'),
            $this->translate('YOURS')
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_SYSMSG);
        foreach ($bids as $bid) {
            /** @var AuctionBid $bid */
            $auction = $bid->getAuction();
            $file = $auction->getFile();
            $returnMessage = sprintf(
                '%-11s|%-32s|%-20s|%-3s|%-3s|%-11s|%-11s',
                $auction->getId(),
                $file->getName(),
                $file->getFileType()->getName(),
                $file->getLevel(),
                $file->getMaxIntegrity(),
                $this->bidRepo->findHighBid($auction),
                $bid->getBid()
            );
            $this->gameClientResponse->addMessage($returnMessage);
        }
        return $this->gameClientResponse->send();
    }

}
