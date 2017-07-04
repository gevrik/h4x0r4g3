<?php

/**
 * HangmanService.
 * This service resolves logic around the hangman hacking mini-game.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Word;
use Netrunners\Repository\WordRepository;
use Zend\View\Model\ViewModel;

class HangmanService extends BaseService
{

    const LENGTH_MODIFIER = 2;

    /**
     * @param $resourceId
     * @return array|bool
     */
    public function startHangmanGame($resourceId)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $wordRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
            /** @var WordRepository $wordRepo */
            $words = $wordRepo->getRandomWordsByLength();
            $word = array_shift($words);
            if (!$word) return true;
            /** @var Word $word */
            $theWord = strtolower($word->getContent());
            $hangman = [
                'word' => $theWord,
                'attempts' => 5,
                'known' => str_repeat('*', strlen($theWord)),
                'letters' => []
            ];
            $ws->setClientData($resourceId, 'hangman', $hangman);
            $view = new ViewModel();
            $view->setTemplate('netrunners/word/hangman-game.phtml');
            $view->setVariable('hangman', (object)$hangman);
            $this->response = array(
                'command' => 'showpanel',
                'type' => 'default',
                'content' => $this->viewRenderer->render($view)
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool
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
        $ws->setClientData($resourceId, 'hangman', $hangmanData);
        $view = new ViewModel();
        $view->setTemplate('netrunners/word/hangman-game.phtml');
        $view->setVariable('hangman', (object)$hangmanData);
        $this->response = array(
            'command' => 'showpanel',
            'type' => 'default',
            'content' => $this->viewRenderer->render($view)
        );
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool
     */
    public function solutionAttempt($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $guess = $this->getNextParameter($contentArray, false);
        if (!$guess) return true;
        $hangmanData = $this->clientData->hangman;
        if ($hangmanData['word'] == $guess) {
            // player has guessed correctly
            var_dump('correct!');
        }
        else {
            var_dump('false!');
        }
        return $this->response;
    }

}
