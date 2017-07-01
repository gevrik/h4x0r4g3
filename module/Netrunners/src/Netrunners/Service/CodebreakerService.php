<?php

/**
 * CodebreakerService.
 * This service resolves logic around the codebreaker mini-game.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Connection;
use Netrunners\Entity\File;
use Netrunners\Repository\WordRepository;
use TmoAuth\Entity\User;

class CodebreakerService extends BaseService
{

    public function startCodebreaker($resourceId, File $file, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        if (!$file) return true;
        $profile = $user->getProfile();
        $response = false;
        $connection = false;
        list($contentArray, $connectionParameter) = $this->getNextParameter($contentArray);
        if (!$connectionParameter) {
            $response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a connection by name or number')
                )
            );
        }
        if (!$response) {
            $connection = $this->findConnectionByNameOrNumber($connectionParameter, $profile->getCurrentNode());
            if (!$connection) {
                $response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Unable to find connection')
                    )
                );
            }
        }
        if (!$response) {
            $guess = $this->getNextParameter($contentArray, false);
            if ($guess && !empty($clientData->codebreaker)) {
                /* mini game logic solve attempt */
                $response = $this->solveCodebreaker($resourceId, $guess);
            }
            if (!$response) {
                $response = $this->isActionBlocked($resourceId);
            }
            if (!$response) {
                /* mini game logic start */
                $wordRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
                /** @var WordRepository $wordRepo */
                $wordLength = 3 + $connection->getLevel();
                $hashLength = 8 * $connection->getLevel();
                $words = $wordRepo->getRandomWordsByLength(1, $wordLength);
                $word = array_shift($words);
                $thePassword = $word->getContent();
                $randomString = $this->getRandomString($hashLength);
                for ($index = 0; $index < mb_strlen($thePassword); $index++) {
                    if (mt_rand(1, 100) > 50) {
                        $thePassword[$index] = strtoupper($thePassword[$index]);
                    }
                    else {
                        $thePassword[$index] = strtolower($thePassword[$index]);
                    }
                }
                $theString = substr_replace($randomString, $thePassword, mt_rand(0, ($hashLength - $wordLength - 1)), $wordLength);
                $deadline = new \DateTime();
                $deadline->add(new \DateInterval('PT30S'));
                $ws->setClientData($resourceId, 'codebreaker', [
                    'thePassword' => $thePassword,
                    'theString' => $theString,
                    'deadline' => 30,
                    'fileId' => $file->getId(),
                    'connectionId' => $connection->getId()
                ]);
                $response = array(
                    'command' => 'showmessage',
                    'deadline' => 30,
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">find the password: %s</pre>'),
                        $theString
                    )
                );
            }
        }
        return $response;
    }

    private function solveCodebreaker($resourceId, $guess)
    {
        $ws = $this->getWebsocketServer();
        $clientData = $ws->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return false;
        /** @var User $user */
        $profile = $user->getProfile();
        $codebreakerData = $clientData->codebreaker;
        if ($guess == $codebreakerData['thePassword']) {
            $connection = $this->entityManager->find('Netrunners\Entity\Connection', $codebreakerData['connectionId']);
            /** @var Connection $connection */
            $this->movePlayerToTargetNode($resourceId, $profile, $connection);
            $connection->setIsOpen(true);
            $this->entityManager->flush($connection);
            $response = array(
                'command' => 'showmessage',
                'cleardeadline' => true,
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('Codebreaking attempt success')
                )
            );
        }
        else {
            $response = array(
                'command' => 'showmessage',
                'cleardeadline' => true,
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Codebreaking attempt failed')
                )
            );
        }
        $ws->setClientData($resourceId, 'codebreaker', []);
        return $response;
    }

}
