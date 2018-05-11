<?php

/**
 * Bookmark Service.
 * The service supplies methods that resolve logic around bookmark objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Bookmark;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\System;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\BookmarkRepository;
use Netrunners\Repository\NodeRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class BookmarkService extends BaseService
{

    /**
     * @var BookmarkRepository
     */
    protected $bookmarkRepo;

    /**
     * @var SystemRepository
     */
    protected $systemRepo;

    /**
     * @var NodeRepository
     */
    protected $nodeRepo;


    /**
     * BookmarkService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->bookmarkRepo = $this->entityManager->getRepository(Bookmark::class);
        $this->systemRepo = $this->entityManager->getRepository(System::class);
        $this->nodeRepo = $this->entityManager->getRepository(Node::class);
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function bookmarksCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $bookmarks = $this->bookmarkRepo->findBy(
            ['profile' => $profile],
            ['id' => 'asc']
        );
        $message = sprintf(
            '%-11s|%-11s|%-32s|%-32s|%-32s|%s',
            $this->translate('#'),
            $this->translate('ID'),
            $this->translate('SYSTEM'),
            $this->translate('NODE'),
            $this->translate('NAME'),
            $this->translate('ADDED')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $messages = [];
        $count = 0;
        /** @var Bookmark $bookmark */
        foreach ($bookmarks as $bookmark) {
            $count++;
            $messages[] = sprintf(
                '%-11s|%-11s|%-32s|%-32s|%-32s|%s',
                $count,
                $bookmark->getId(),
                $bookmark->getSystem()->getName(),
                $bookmark->getNode()->getName(),
                $bookmark->getName(),
                $bookmark->getAdded()->format('Y/m/d H:i:s')
            );
        }
        return $this->gameClientResponse->addMessages($messages)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function bookmarkCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        list($contentArray, $addy) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$addy) {
            return $this->bookmarksCommand($resourceId);
        }
        /** @var System $targetSystem */
        $targetSystem = $this->systemRepo->findByAddy($addy);
        if (!$targetSystem) {
            $message = sprintf($this->translate('Invalid target system [%s]'), $addy);
            return $this->gameClientResponse->addMessage($message)->send();
        }
        list($contentArray, $nodeId) = $this->getNextParameter($contentArray, true, true);
        if (!$nodeId) {
            $message = sprintf($this->translate('Invalid target node id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var Node $targetNode */
        $targetNode = $this->nodeRepo->find($nodeId);
        if (!$targetNode) {
            $message = sprintf($this->translate('Invalid target node id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $existingBookmark = $this->bookmarkRepo->findOneBy([
            'profile' => $profile,
            'node' => $targetNode,
            'system' => $targetSystem
        ]);
        if ($existingBookmark) {
            $message = sprintf($this->translate('Bookmark already exists for this system and node'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $targetNodeType = $targetNode->getNodeType();
        if ($targetNodeType->getId() != NodeType::ID_IO && $targetNodeType->getId() != NodeType::ID_PUBLICIO) {
            $message = sprintf($this->translate('Invalid target node id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($targetNodeType->getId() == NodeType::ID_IO && !$this->canAccess($profile, $targetSystem)) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid target node id'))->send();
        }
        $bookmarkName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$bookmarkName) {
            $bookmarkName = sprintf('bm_%s_%s', $this->getNameWithoutSpaces($targetSystem->getName()), $targetNode->getId());
        }
        else {
            $bookmarkName = $this->getNameWithoutSpaces($bookmarkName);
        }
        $bookmark = new Bookmark();
        $bookmark->setName($bookmarkName);
        $bookmark->setProfile($profile);
        $bookmark->setAdded(new \DateTime());
        $bookmark->setNode($targetNode);
        $bookmark->setSystem($targetSystem);
        $this->entityManager->persist($bookmark);
        $this->entityManager->flush($bookmark);
        $message = sprintf($this->translate('Bookmark created'));
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return GameClientResponse|bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function removeBookmarkCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $bookmarkId = $this->getNextParameter($contentArray, false, true);
        if (!$bookmarkId) {
            $message = sprintf($this->translate('Please specify a bookmark id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $bookmark = $this->getBookmarkByIdOrNumber($bookmarkId);
        if (!$bookmark || ($bookmark && $bookmark->getProfile() !== $profile)) {
            $message = sprintf($this->translate('Please specify a valid bookmark id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $this->entityManager->remove($bookmark);
        $this->entityManager->flush($bookmark);
        $message = sprintf($this->translate('Bookmark removed'));
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
    public function useBookmarkCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $bookmarkId = $this->getNextParameter($contentArray, false, true);
        if (!$bookmarkId) {
            $message = sprintf($this->translate('Please specify a bookmark id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $bookmark = $this->getBookmarkByIdOrNumber($bookmarkId);
        if (!$bookmark || ($bookmark && $bookmark->getProfile() !== $profile)) {
            $message = sprintf($this->translate('Please specify a valid bookmark id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        return $this->systemConnect($resourceId, [$bookmark->getSystem()->getAddy(), $bookmark->getNode()->getId()]);
    }

    /**
     * @param int $identifier
     * @return Bookmark|null
     */
    private function getBookmarkByIdOrNumber($identifier)
    {
        /** @var Bookmark $bookmark */
        $profile = $this->user->getProfile();
        $bookmark = $this->bookmarkRepo->findOneBy([
            'id' => $identifier,
            'profile' => $profile
        ]);
        if (!$bookmark) {
            // try to get via number
            $bookmarks = $this->bookmarkRepo->findBy(['profile' => $profile], ['id' => 'asc']);
            if (array_key_exists($identifier - 1, $bookmarks)) {
                $bookmark = $bookmarks[$identifier - 1];
            }
        }
        return $bookmark;
    }

}
