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
use Netrunners\Entity\Profile;
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
     * @return array|bool|false
     */
    public function listAuctions($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response && $currentNode->getNodeType()->getId() != NodeType::ID_MARKET) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to be in a market node to list auctions')
                )
            );
        }
        if (!$this->response) {
            $auctions = $this->auctionRepo->findActiveByNode($currentNode);
            $returnMessage = [];
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-20s|%-32s|%-32s|%-3s|%-3s|%-11s|%-11s|%-19s</pre>',
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
            foreach ($auctions as $auction) {
                /** @var Auction $auction */
                $auctionFile = $auction->getFile();
                $auctioneer = $auction->getAuctioneer();
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-20s|%-32s|%-32s|%-3s|%-3s|%-11s|%-11s|%-19s</pre>',
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
            }
            $this->response = [
                'command' => 'showoutput',
                'message' => $returnMessage
            ];
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function auctionFile($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        $file = false;
        if (!$this->response && $currentNode->getNodeType()->getId() != NodeType::ID_MARKET) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('You need to be in a market node to auction a file')
                )
            );
        }
        if (!$this->response) {
            list($contentArray, $parameter) = $this->getNextParameter($contentArray, true, false, false, true);
            $file = $this->auctionFileChecks($parameter);
        }
        if (!$this->response && $file) {
            list($contentArray, $startingPrice) = $this->getNextParameter($contentArray, true, true);
            $buyoutPrice = $this->getNextParameter($contentArray, false, true);
            if (!$startingPrice) $startingPrice = 0;
            if (!$buyoutPrice) $buyoutPrice = 0;
            if ($buyoutPrice == 0 && $startingPrice == 0) {
                $startingPrice = 1;
            }
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
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] has been put up for auction</pre>'),
                    $file->getName()
                )
            );
        }
        return $this->response;
    }

    /**
     * @param $parameter
     * @return mixed|File|null
     */
    private function auctionFileChecks($parameter)
    {
        $profile = $this->user->getProfile();
        $file = NULL;
        // try to get target file via repo method
        $targetFiles = $this->fileRepo->findByNodeOrProfileAndName($profile->getCurrentNode(), $profile, $parameter);
        if (!$this->response && count($targetFiles) < 1) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => '<pre style="white-space: pre-wrap;" class="text-warning">No such file</pre>'
            );
        }
        if (!$this->response) {
            $file = array_shift($targetFiles);
            /** @var File $file */
            // check if the file belongs to the profile
            if ($file && $file->getProfile() != $profile) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Permission denied')
                    )
                );
            }
            if (!$this->response && $file->getRunning()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Unable to auction running file - please kill the process first')
                    )
                );
            }
            if (!$this->response && $file->getSystem()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Unable to auction file - please unload it first')
                    )
                );
            }
            if (!$this->response && $file->getNode()) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Unable to auction file - please unload it first')
                    )
                );
            }
            // TODO auction fee?
        }
        return $file;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function bidOnAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            list($contentArray, $auctionId) = $this->getNextParameter($contentArray, true, true);
            if (!$auctionId) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Please specify the auction id')
                    )
                );
            }
            $auction = NULL;
            if (!$this->response) {
                $auction = $this->auctionRepo->find($auctionId);
                if (!$auction) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid auction id')
                        )
                    );
                }
            }
            /** @var Auction $auction */
            $now = new \DateTime();
            if (!$this->response && $auction) {
                if ($auction->getExpires() < $now) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction has expired')
                        )
                    );
                }
                if (!$this->response && $auction->getBought() !== NULL) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction is no longer active')
                        )
                    );
                }
                if (!$this->response && $auction->getStartingPrice() === 0) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to bid on buyout-only auctions')
                        )
                    );
                }
                if (!$this->response && $auction->getNode() != $currentNode) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You need to be in the market node that the auction was posted in')
                        )
                    );
                }
                if (!$this->response && $auction->getAuctioneer() === $profile) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to bid on your own auctions')
                        )
                    );
                }
            }
            if (!$this->response && $auction) {
                /** @var Auction $auction */
                $currentBid = $this->bidRepo->findByAuctionAndProfile($auction, $profile);
                /** @var AuctionBid $currentBid */
                $bid = $this->getNextParameter($contentArray, false, true);
                if (!$bid) $bid = $auction->getCurrentPrice() + 1;
                if ($bid <= $auction->getCurrentPrice()) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to bid less than the current bid')
                        )
                    );
                }
                if ($currentBid) {
                    $toPay = $bid - $currentBid->getBid();
                }
                else {
                    $toPay = $bid;
                }
                if (!$this->response && $toPay > $profile->getCredits()) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough credits for that high of a bid - needed: %s</pre>'),
                            $toPay
                        )
                    );
                }
                // all good, we can bid on the auction
                if (!$this->response) {
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
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have bid on auction#%s and raised the current price to %s credits - %s credits spent</pre>'),
                            $auction->getId(),
                            $bid,
                            $toPay
                        )
                    );
                }
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function buyoutAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $auctionId = $this->getNextParameter($contentArray, false, true);
            if (!$auctionId) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Please specify the auction id')
                    )
                );
            }
            $auction = NULL;
            if (!$this->response) {
                $auction = $this->auctionRepo->find($auctionId);
                if (!$auction) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid auction id')
                        )
                    );
                }
            }
            /** @var Auction $auction */
            $now = new \DateTime();
            if (!$this->response && $auction) {
                if ($auction->getExpires() < $now) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction has expired')
                        )
                    );
                }
                if (!$this->response && $auction->getBought() !== NULL) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction is no longer active')
                        )
                    );
                }
                if (!$this->response && $auction->getBuyoutPrice() === 0) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to buyout bid-only auctions')
                        )
                    );
                }
                if (!$this->response && $auction->getNode() != $currentNode) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You need to be in the market node that the auction was posted in')
                        )
                    );
                }
                if (!$this->response && $auction->getAuctioneer() === $profile) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to buyout your own auctions')
                        )
                    );
                }
            }
            if (!$this->response && $auction) {
                $toPay = $auction->getBuyoutPrice();
                if ($toPay > $profile->getCredits()) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-warning">You do not have enough credits to buy-out the auction - needed: %s</pre>'),
                            $toPay
                        )
                    );
                }
                // all good, we can bid on the auction
                if (!$this->response) {
                    $profile->setCredits($profile->getCredits()-$toPay);
                    $auction->setBuyer($profile);
                    $auction->setBought($now);
                    $this->refundBidders($auction);
                    $this->entityManager->flush();
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have bought-out auction#%s for %s credits</pre>'),
                            $auction->getId(),
                            $toPay
                        )
                    );
                }
            }
        }
        return $this->response;
    }

    /**
     * @param Auction $auction
     * @param Profile|NULL $buyer
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
                'info'
            );
        }
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function claimAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        $auctionId = NULL;
        if (!$this->response) {
            $auctionId = $this->getNextParameter($contentArray, false, true);
            if (!$auctionId) {
                $claimableAuctions = $this->auctionRepo->findClaimableForProfile($profile);
                if (count($claimableAuctions) < 1) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('No claimable auctions')
                        )
                    );
                }
                else {
                    $returnMessage = [];
                    $returnMessage[] = sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-32s</pre>',
                        $this->translate('ID'),
                        $this->translate('NAME')
                    );
                    foreach ($claimableAuctions as $claimableAuction) {
                        /** @var Auction $claimableAuction */
                        $auctionFile = $claimableAuction->getFile();
                        $returnMessage[] = sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-32s</pre>',
                            $claimableAuction->getId(),
                            $auctionFile->getName()
                        );
                    }
                    $this->response = [
                        'command' => 'showoutput',
                        'message' => $returnMessage
                    ];
                }
            }
            $auction = NULL;
            if (!$this->response && $auctionId) {
                $auction = $this->auctionRepo->find($auctionId);
                if (!$auction) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid auction id')
                        )
                    );
                }
            }
            /** @var Auction $auction */
            $now = new \DateTime();
            if (!$this->response && $auction) {
                if (!$this->response && $auction->getBought() == NULL ) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction is still active')
                        )
                    );
                }
                if (!$this->response && $auction->getBuyer() !== $profile) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You did not win that auction')
                        )
                    );
                }
                if (!$this->response && $auction->getNode() != $currentNode) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You need to be in the market node that the auction was posted in')
                        )
                    );
                }
                if (!$this->response && $auction->getClaimed()) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You have already claimed that auction')
                        )
                    );
                }
                if (!$this->response && !$this->canStoreFile($profile, $auction->getFile())) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You do not have enough storage space to store the file')
                        )
                    );
                }
            }
            if (!$this->response && $auction) {
                // all good, we can claim the auction
                $auction->setClaimed($now);
                $file = $auction->getFile();
                $file->setProfile($profile);
                $this->entityManager->flush();
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have claimed auction#%s [%s]</pre>'),
                        $auction->getId(),
                        $auction->getFile()->getName()

                    )
                );
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function cancelAuction($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $auctionId = $this->getNextParameter($contentArray, false, true);
            if (!$auctionId) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Please specify the auction id')
                    )
                );
            }
            $auction = NULL;
            if (!$this->response) {
                $auction = $this->auctionRepo->find($auctionId);
                if (!$auction) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Invalid auction id')
                        )
                    );
                }
            }
            /** @var Auction $auction */
            $now = new \DateTime();
            if (!$this->response && $auction) {
                if ($auction->getExpires() < $now) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction has expired')
                        )
                    );
                }
                if (!$this->response && $auction->getBought() !== NULL) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That auction is no longer active')
                        )
                    );
                }
                if (!$this->response && $auction->getNode() != $currentNode) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('You need to be in the market node that the auction was posted in')
                        )
                    );
                }
                if (!$this->response && $auction->getAuctioneer() !== $profile) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('That is not your auction')
                        )
                    );
                }
                if (!$this->response && !$auction->getBuyoutPrice() && $this->bidRepo->countByAuction($auction) >= 1) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Auctions with bids can not be cancelled')
                        )
                    );
                }
                if (!$this->response && !$this->canStoreFile($profile, $auction->getFile())) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Unable to cancel auction - you do not have enough storage space to store the file')
                        )
                    );
                }
            }
            if (!$this->response && $auction) {
                /** @var Auction $auction */
                $auctionedFile = $auction->getFile();
                $auctionedFile->setProfile($profile);
                $this->entityManager->remove($auction);
                $this->entityManager->flush();
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-success">You have cancelled auction#%s</pre>'),
                        $auction->getId()
                    )
                );
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showBids($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $bids = $this->bidRepo->findActiveByProfile($profile);
            $returnMessage = [];
            $returnMessage[] = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-11s|%-32s|%-20s|%-3s|%-3s|%-11s|%-11s</pre>',
                $this->translate('ID'),
                $this->translate('NAME'),
                $this->translate('TYPE'),
                $this->translate('LVL'),
                $this->translate('INT'),
                $this->translate('HIGHEST'),
                $this->translate('YOURS')
            );
            foreach ($bids as $bid) {
                /** @var AuctionBid $bid */
                $auction = $bid->getAuction();
                $file = $auction->getFile();
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%-32s|%-20s|%-3s|%-3s|%-11s|%-11s</pre>',
                    $auction->getId(),
                    $file->getName(),
                    $file->getFileType()->getName(),
                    $file->getLevel(),
                    $file->getMaxIntegrity(),
                    $this->bidRepo->findHighBid($auction),
                    $bid->getBid()
                );
            }
            $this->response = [
                'command' => 'showoutput',
                'message' => $returnMessage
            ];
        }
        return $this->response;
    }

}
