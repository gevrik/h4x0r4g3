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
use Netrunners\Repository\ConnectionRepository;
use Netrunners\Repository\WordRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class CodebreakerService extends BaseService
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
     * CodebreakerService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->wordRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Word');
    }

    /**
     * @param $resourceId
     * @param File $file
     * @param $contentArray
     * @return array|bool
     */
    public function startCodebreaker($resourceId, File $file, $contentArray)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$file) return true;
        $profile = $this->user->getProfile();
        $connection = false;
        list($contentArray, $connectionParameter) = $this->getNextParameter($contentArray);
        if (!$connectionParameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('Please specify a connection by name or number')
                )
            );
        }
        if (!$this->response) {
            $connection = $this->findConnectionByNameOrNumber($connectionParameter, $profile->getCurrentNode());
            if (!$connection) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                        $this->translate('Unable to find connection')
                    )
                );
            }
        }
        if (!$this->response) {
            $guess = $this->getNextParameter($contentArray, false);
            if ($guess && !empty($this->clientData->codebreaker)) {
                /* mini game logic solve attempt */
                $this->response = $this->solveCodebreaker($resourceId, $guess);
            }
            if (!$this->response) {
                $this->response = $this->isActionBlocked($resourceId);
            }
            if (!$this->response) {
                /* mini game logic start */
                $wordLength = 4 + $connection->getLevel();
                $hashLength = 8 * $connection->getLevel();
                $words = $this->wordRepo->getRandomWordsByLength(1, $wordLength);
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
                $this->response = array(
                    'command' => 'showmessage',
                    'deadline' => 30,
                    'message' => sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">find the password: %s</pre>'),
                        $theString
                    )
                );
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $guess
     * @return array|bool
     */
    private function solveCodebreaker($resourceId, $guess)
    {
        $ws = $this->getWebsocketServer();
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $codebreakerData = $this->clientData->codebreaker;
        $connection = $this->entityManager->find('Netrunners\Entity\Connection', $codebreakerData['connectionId']);
        /** @var Connection $connection */
        if ($guess == $codebreakerData['thePassword']) {
            $this->movePlayerToTargetNode($resourceId, $profile, $connection);
            $connection->setIsOpen(true);
            $this->entityManager->flush($connection);
            $otherConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($connection->getTargetNode(), $connection->getSourceNode());
            /** @var Connection $otherConnection */
            $otherConnection->setIsOpen(true);
            $this->entityManager->flush($otherConnection);
            $this->response = array(
                'command' => 'showmessage',
                'cleardeadline' => true,
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('Codebreaking attempt success - you have bypassed the codegate')
                )
            );
        }
        else {
            $this->response = array(
                'command' => 'showmessage',
                'cleardeadline' => true,
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Codebreaking attempt failed - security rating and alert level raised')
                )
            );
            $this->raiseProfileSecurityRating($profile, $connection->getLevel());
            $targetSystem = $connection->getSourceNode()->getSystem();
            $this->raiseSystemAlertLevel($targetSystem, $connection->getLevel());
            $this->writeSystemLogEntry(
                $targetSystem,
                'Codebreaking attempt failed',
                'warning',
                NULL,
                NULL,
                $connection->getSourceNode()
            );
        }
        $ws->setClientData($resourceId, 'codebreaker', []);
        return $this->response;
    }

}
