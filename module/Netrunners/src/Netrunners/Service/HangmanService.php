<?php

/**
 * HangmanService.
 * This service resolves logic around the hangman hacking mini-game.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Profile;
use Netrunners\Entity\Word;
use Netrunners\Repository\WordRepository;
use TmoAuth\Entity\User;
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
        $clientData = $ws->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = $this->isActionBlocked($resourceId);
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
        $response = array(
            'command' => 'showpanel',
            'type' => 'default',
            'content' => $this->viewRenderer->render($view)
        );
        return $response;
    }

    public function letterClicked($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = false;
        $letter = $this->getNextParameter($contentArray, false);
        if (!$letter) return true;
        $letter = strtolower($letter);
        $hangmanData = $clientData->hangman;
        if ($hangmanData['attempts'] < 1) return true;
        $known = $hangmanData['known'];
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
                var_dump('adding letter as true');
                $hangmanData['letters'][$letter] = true;
            }
        }
        else {
            if (!array_key_exists($letter, $hangmanData['letters'])) {
                var_dump('adding letter as false');
                $hangmanData['letters'][$letter] = false;
            }
        }
        $hangmanData['attempts']--;
        $ws->setClientData($resourceId, 'hangman', $hangmanData);
        $view = new ViewModel();
        $view->setTemplate('netrunners/word/hangman-game.phtml');
        $view->setVariable('hangman', (object)$hangmanData);
        $response = array(
            'command' => 'showpanel',
            'type' => 'default',
            'content' => $this->viewRenderer->render($view)
        );
        return $response;
    }

    public function solutionAttempt($resourceId, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $response = false;
        $guess = $this->getNextParameter($contentArray, false);
        if (!$guess) return true;
        $hangmanData = $clientData->hangman;
        if ($hangmanData['word'] == $guess) {
            // player has guessed correctly
            var_dump('correct!');
        }
        else {
            var_dump('false!');
        }
        return $response;
    }

}
