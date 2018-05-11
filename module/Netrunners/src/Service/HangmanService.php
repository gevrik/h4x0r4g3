<?php

/**
 * HangmanService.
 * This service resolves logic around the hangman hacking mini-game.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\Word;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\SystemRepository;
use Netrunners\Repository\WordRepository;
use Zend\View\Model\ViewModel;

final class HangmanService extends BaseService
{

    const LENGTH_MODIFIER = 4;
    const BASE_GUESSES = 4;

    /**
     * @param $resourceId
     * @param File $file
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function startHangmanGame($resourceId, File $file, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $node = NULL;

        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        if (!$this->canExecuteInNodeType($file, $currentNode)) {
            $message = sprintf(
                $this->translate('%s can only be used in an I/O node'),
                $file->getName()
            );
            return $this->gameClientResponse->addMessage($message)->send();
        }
        list($contentArray, $addy) = $this->getNextParameter($contentArray, true);
        if (!$addy) {
            $message = $this->translate('Please specify a system address to break in to');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $system = $systemRepo->findOneBy([
            'addy' => $addy
        ]);
        if (!$system) {
            $message = $this->translate('Invalid system address');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($system->getProfile() === $profile) {
            $message = $this->translate('Invalid system - unable to break in to your own systems');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now check if a node id was given
        $nodeId = $this->getNextParameter($contentArray, false, true);
        if (!$nodeId) {
            $message = $this->translate('Please specify a node ID to break in to');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $node = $this->entityManager->find('Netrunners\Entity\Node', $nodeId);
        /** @var Node $node */
        if (!$node) {
            $message = $this->translate('Invalid node ID');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // start the mini-game
        $attempts = ceil(round($this->getBonusForFileLevel($file)/10)) + self::BASE_GUESSES;
        $wordRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
        /** @var WordRepository $wordRepo */
        $wordLength = $node->getLevel() + self::LENGTH_MODIFIER;
        $words = $wordRepo->getRandomWordsByLength(1, $wordLength);
        $word = array_shift($words);
        if (!$word) return true;
        /** @var Word $word */
        $theWord = strtolower($word->getContent());
        $hangman = [
            'word' => $theWord,
            'attempts' => $attempts,
            'known' => str_repeat('*', strlen($theWord)),
            'letters' => [],
            'nodeid' => $node->getId(),
            'fileid' => $file->getId()
        ];
        $ws->setClientHangmanData($resourceId, $hangman);
        $view = new ViewModel();
        $view->setTemplate('netrunners/word/hangman-game.phtml');
        $view->setVariable('hangman', (object)$hangman);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL)->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        $this->lowerIntegrityOfFile($file, 100, 1, true);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function letterClicked($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $letter = $this->getNextParameter($contentArray, false);
        if (!$letter) return true;
        $letter = strtolower($letter);
        $hangmanData = $this->clientData->hangman;
        if ($hangmanData['attempts'] < 1) return true;
        $chars = str_split($hangmanData['word']);
        $letterFound = false;
        foreach($chars as $pos => $char){
            if ($char == $letter) {
                $hangmanData['known'] = substr_replace($hangmanData['known'], $letter, $pos, 1);
                $letterFound = true;
            }
        }
        if ($letterFound) {
            if (!array_key_exists($letter, $hangmanData['letters'])) {
                $hangmanData['letters'][$letter] = true;
            }
        }
        else {
            if (!array_key_exists($letter, $hangmanData['letters'])) {
                $hangmanData['letters'][$letter] = false;
            }
        }
        $hangmanData['attempts']--;
        $ws->setClientHangmanData($resourceId, $hangmanData);
        $view = new ViewModel();
        $view->setTemplate('netrunners/word/hangman-game.phtml');
        $view->setVariable('hangman', (object)$hangmanData);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL)->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function solutionAttempt($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $guess = $this->getNextParameter($contentArray, false, false, false, true);
        if (!$guess) return false;
        $hangmanData = $this->clientData->hangman;
        $ws->clearClientHangmanData($resourceId);
        $nodeId = $hangmanData['nodeid'];
        $fileId = $hangmanData['fileid'];
        /** @var Node $node */
        $node = $this->entityManager->find('Netrunners\Entity\Node', $nodeId);
        /** @var File $file */
        $file = $this->entityManager->find('Netrunners\Entity\File', $fileId);
        if ($node && $file && $hangmanData['word'] == $guess) {
            // player has guessed correctly
            $this->movePlayerToTargetNodeNew($resourceId, $profile, NULL, $profile->getCurrentNode(), $node);
            $flyResponse = new GameClientResponse($resourceId);
            $flyResponse->setCommand(GameClientResponse::COMMAND_FLYTO)->setSilent(true);
            $flyResponse->addOption(GameClientResponse::OPT_CONTENT, explode(',',$node->getSystem()->getGeocoords()));
            $flyResponse->send();
            $this->updateMap($resourceId);
            $message = $this->translate('You break in to the target system');
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
            $closePanelResponse = new GameClientResponse($resourceId);
            $closePanelResponse->setCommand(GameClientResponse::COMMAND_CLOSEPANEL)->setSilent(true)->send();
            $this->gameClientResponse->send();
            return $this->showNodeInfoNew($resourceId, NULL, true);
        }
        else {
            $message = $this->translate('You fail to break in to the target system');
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG)->setCommand(GameClientResponse::COMMAND_SHOWOUTPUT_PREPEND);
            $closePanelResponse = new GameClientResponse($resourceId);
            $closePanelResponse->setCommand(GameClientResponse::COMMAND_CLOSEPANEL)->setSilent(true)->send();
            $this->gameClientResponse->send();
            $file = $this->triggerProgramExecutionReaction($file, $node);
            if ($file->getIntegrity() <= 0) {
                $message = $this->translate('Critical integrity failure - program shutdown initiated');
                $this->gameClientResponse->addMessage($message);
            }
        }
        return $this->gameClientResponse->send();
    }

}
