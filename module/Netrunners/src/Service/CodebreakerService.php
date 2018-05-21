<?php

/**
 * CodebreakerService.
 * This service resolves logic around the codebreaker mini-game.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Entity\Notification;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\WordRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

final class CodebreakerService extends BaseService
{

    /**
     * @var WordRepository
     */
    protected $wordRepo;

    /**
     * @var ConnectionRepository
     */
    protected $connectionRepo;

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;


    /**
     * CodebreakerService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator, $entityGenerator);
        $this->wordRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function startCodebreaker($resourceId, File $file, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$file) return true;
        $profile = $this->user->getProfile();
        list($contentArray, $connectionParameter) = $this->getNextParameter($contentArray);
        if (!$connectionParameter) {
            return $this->gameClientResponse->addMessage($this->translate('Please specify a connection by name or number'))->send();
        }
        $connection = $this->findConnectionByNameOrNumber($connectionParameter, $profile->getCurrentNode());
        if (!$connection) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to find connection'))->send();
        }
        if ($connection && $connection->getType() != Connection::TYPE_CODEGATE) {
            return $this->gameClientResponse->addMessage($this->translate('That connection is not protected by a code-gate'))->send();
        }
        if ($connection && $connection->getType() == Connection::TYPE_CODEGATE && $connection->getisOpen()) {
            return $this->gameClientResponse->addMessage($this->translate('The connection is already open'))->send();
        }
        if ($connection && $file->getLevel() < ($connection->getLevel()-1)*10) {
            return $this->gameClientResponse->addMessage($this->translate('The level of your codebreaker is too low for this codegate'))->send();
        }
        // check if there are hostile npc in the node
        if ($this->npcInstanceRepo->countByHostileToProfileInNode($profile) >= 1) {
            return $this->gameClientResponse->addMessage($this->translate('Unable to use codebreaker when there are hostile entities in the node'))->send();
        }
        // first checks passed - start logic
        $guess = $this->getNextParameter($contentArray, false);
        if ($guess && !empty($this->clientData->codebreaker)) {
            /* mini game logic solve attempt */
            return $this->solveCodebreaker($resourceId, $guess);
        }
        else {
            if (!empty($this->clientData->codebreaker)) {
                return $this->gameClientResponse->addMessage($this->translate('Codebreaker attempt already running'))->send();
            }
            $isBlocked = $this->isActionBlockedNew($resourceId);
            if ($isBlocked) {
                return $this->gameClientResponse->addMessage($isBlocked)->send();
            }
            /* mini game logic start */
            $wordLength = 4 + $connection->getLevel();
            $hashLength = 8 * $connection->getLevel();
            $words = $this->wordRepo->getRandomWordsByLength(1, $wordLength);
            $word = array_shift($words);
            $thePassword = $word->getContent();
            $randomString = $this->getRandomString($hashLength, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
            $thePassword = $this->leetifyString($thePassword);
            $theString = substr_replace($randomString, $thePassword, mt_rand(0, ($hashLength - $wordLength - 1)), $wordLength);
            $deadline = new \DateTime();
            $deadline->add(new \DateInterval('PT30S'));
            $ws->setClientCodebreakerData($resourceId, [
                'thePassword' => $thePassword,
                'theString' => $theString,
                'deadline' => 30,
                'fileId' => $file->getId(),
                'connectionId' => $connection->getId()
            ]);
            $message = sprintf(
                $this->translate('find the password: %s'),
                $theString
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_DIRECTORY);
            $this->lowerIntegrityOfFile($file, 100, 1, true);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $guess
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function solveCodebreaker($resourceId, $guess)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $codebreakerData = $this->clientData->codebreaker;
        /** @var Connection $connection */
        $connection = $this->entityManager->find('Netrunners\Entity\Connection', $codebreakerData['connectionId']);
        if ($guess == $codebreakerData['thePassword']) {
            //$this->movePlayerToTargetNode($resourceId, $profile, $connection);
            $connection->setIsOpen(true);
            /** @var Connection $otherConnection */
            $otherConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($connection->getTargetNode(), $connection->getSourceNode());
            $otherConnection->setIsOpen(true);
            $this->entityManager->flush();
            $this->updateMap($resourceId);
            $this->gameClientResponse
                ->addMessage(
                    $this->translate('Codebreaking attempt success - you have opened the codegate'),
                    GameClientResponse::CLASS_SUCCESS
                );
        }
        else {
            $this->gameClientResponse->addMessage($this->translate('Codebreaking attempt failed - security rating and alert level raised'));
            $this->raiseProfileSecurityRating($profile, $connection->getLevel());
            $targetSystem = $connection->getSourceNode()->getSystem();
            $this->raiseSystemAlertLevel($targetSystem, $connection->getLevel()); // TODO use system alert level in other places
            $this->writeSystemLogEntry(
                $targetSystem,
                'Codebreaking attempt failed',
                Notification::SEVERITY_WARNING,
                NULL,
                NULL,
                $connection->getSourceNode()
            );
        }
        $ws->clearClientCodebreakerData($resourceId);
        return $this->gameClientResponse->send();
    }

}
