<?php

/**
 * CodebreakerService.
 * This service resolves logic around the codebreaker mini-game.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Repository\WordRepository;
use TmoAuth\Entity\User;

class CodebreakerService extends BaseService
{

    public function startCodebreaker($resourceId)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $response = $this->isActionBlocked($resourceId);
        if (!$response) {
            $wordRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
            /** @var WordRepository $wordRepo */
            $words = $wordRepo->getRandomWordsByLength(1, 6);
            $word = array_shift($words);
            $thePassword = $word->getContent();
            $randomString = $this->getRandomString(32);
            for ($index = 0; $index < mb_strlen($thePassword); $index++) {
                if (mt_rand(1, 100) > 50) {
                    $thePassword[$index] = strtoupper($thePassword[$index]);
                }
                else {
                    $thePassword[$index] = strtolower($thePassword[$index]);
                }
            }
            $theString = substr_replace($randomString, $thePassword, mt_rand(0, 25), 6);
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">find the password: %s</pre>'),
                    $theString
                )
            );
        }
        return $response;
    }

}
