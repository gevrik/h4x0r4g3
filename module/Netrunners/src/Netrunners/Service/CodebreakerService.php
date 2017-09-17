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
        $this->connectionRepo = $this->entityManager->getRepository('Netrunners\Entity\Connection');
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
        if (!$this->response && !$connectionParameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
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
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Unable to find connection')
                    )
                );
            }
        }
        if (!$this->response && $connection && $connection->getType() != Connection::TYPE_CODEGATE) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('That connection is not protected by a code-gate')
                )
            );
        }
        if (!$this->response && $connection && $connection->getType() == Connection::TYPE_CODEGATE && $connection->getisOpen()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('The connection is already open')
                )
            );
        }
        if (!$this->response && $connection && $file->getLevel() < ($connection->getLevel()-1)*10) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('The level of your codebreaker is too low for this codegate')
                )
            );
        }
        if (!$this->response) {
            $guess = $this->getNextParameter($contentArray, false);
            if ($guess && !empty($this->clientData->codebreaker)) {
                /* mini game logic solve attempt */
                $this->response = $this->solveCodebreaker($resourceId, $guess);
            }
            else {
                if (!empty($this->clientData->codebreaker) && !$this->response) {
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Codebreaker attempt already running')
                        )
                    );
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
                    $randomString = $this->getRandomString($hashLength, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'); // TODO add to keyspace
                    for ($index = 0; $index < mb_strlen($thePassword); $index++) {
                        if (mt_rand(1, 100) > 50) {
                            $thePassword[$index] = strtoupper($thePassword[$index]);
                        }
                        else {
                            $thePassword[$index] = strtolower($thePassword[$index]);
                        }
                        if (mt_rand(1, 100) > 50) {
                            switch (strtolower($thePassword[$index])) {
                                default:
                                    break;
                                case 'a':
                                    $thePassword[$index] = '4';
                                    break;
                                case 'b':
                                    $thePassword[$index] = '8';
                                    break;
                                case 'e':
                                    $thePassword[$index] = '3';
                                    break;
                                case 'g':
                                    $thePassword[$index] = '6';
                                    break;
                                case 'i':
                                    $thePassword[$index] = '1';
                                    break;
                                case 'o':
                                    $thePassword[$index] = '0';
                                    break;
                                case 'p':
                                    $thePassword[$index] = '9';
                                    break;
                                case 's':
                                    $thePassword[$index] = '5';
                                    break;
                                case 't':
                                    $thePassword[$index] = '7';
                                    break;
                            }
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
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-directory">find the password: %s</pre>'),
                            $theString
                        )
                    );
                    $this->lowerIntegrityOfFile($file, 100, 1, true);
                }
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
            //$this->movePlayerToTargetNode($resourceId, $profile, $connection);
            $connection->setIsOpen(true);
            $otherConnection = $this->connectionRepo->findBySourceNodeAndTargetNode($connection->getTargetNode(), $connection->getSourceNode());
            /** @var Connection $otherConnection */
            $otherConnection->setIsOpen(true);
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'cleardeadline' => true,
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('Codebreaking attempt success - you have opened the codegate')
                )
            );
            $this->addAdditionalCommand();
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
            $this->raiseSystemAlertLevel($targetSystem, $connection->getLevel()); // TODO use system alert level in other places
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
