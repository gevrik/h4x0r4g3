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
        $known = '';
        $hangman = [
            'word' => $word->getContent(),
            'attempts' => 5,
            'known' => ''
        ];
        return $response;
    }

}
