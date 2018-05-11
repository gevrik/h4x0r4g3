<?php

/**
 * Manpage Service.
 * The service supplies methods that resolve logic around manpage objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Manpage;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\ManpageRepository;
use TmoAuth\Entity\Role;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

final class ManpageService extends BaseService
{

    const DEFAULT_MANPAGE_ID = 1;
    const ACTION_SAVE = 'save';

    /**
     * @var ManpageRepository
     */
    protected $manpageRepo;


    /**
     * ManpageService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->manpageRepo = $this->entityManager->getRepository('Netrunners\Entity\Manpage');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function helpCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $showInConsole = false;
        $manpage = NULL;
        $command = GameClientResponse::COMMAND_SHOWOUTPUT;
        $message = $this->translate('Sorry, there is no manpage on that topic');
        $title = $this->translate('NEOCORTEX HELP SYSTEM');
        list($contentArray, $part) = $this->getNextParameter($contentArray, true);
        switch ($part) {
            default:
                $keyword = $part;
                break;
            case '-c':
                $showInConsole = true;
                $keyword = $this->getNextParameter($contentArray, false, false, true);
                break;
        }
        if (!$keyword) {
            $manpage = $this->manpageRepo->find(self::DEFAULT_MANPAGE_ID);
            $message = sprintf(
                '%s',
                $manpage->getContent()
            );
            $title = $manpage->getSubject();
        }
        else {
            $searchById = (is_numeric($keyword)) ? true : false;
            if ($searchById) {
                $manpage = $this->manpageRepo->find($keyword);
                if ($manpage && $manpage->getStatus() != Manpage::STATUS_INVALID) {
                    $message = sprintf(
                        '%s',
                        $manpage->getContent()
                    );
                    $title = $manpage->getSubject();
                }
            }
            else {
                $manpages = $this->manpageRepo->findByKeyword($keyword);
                if (!empty($manpages)) {
                    if (count($manpages) > 1) {
                        $message[] = $this->translate('<span class="text-sysmsg">Multiple manpages matched the keyword:</span>');
                        foreach ($manpages as $manpage) {
                            /** @var Manpage $manpage */
                            $message[] = sprintf(
                                '<span class="text-white">%-11s|%s</span>',
                                $manpage->getId(),
                                $manpage->getSubject()
                            );
                        }
                    }
                    else {
                        $manpage = array_shift($manpages);
                        $message = sprintf(
                            '%s',
                            $manpage->getContent()
                        );
                        $title = $manpage->getSubject();
                    }
                }
            }
        }
        if (!$showInConsole) {
            $view = new ViewModel();
            if (is_array($message)) {
                $view->setTemplate('netrunners/manpage/showoutput.phtml');
                $view->setVariable('messages', $message);
                $view->setVariable('title', $title);
            }
            else {
                $view->setTemplate('netrunners/manpage/showmessage.phtml');
                $view->setVariable('message', $message);
                $view->setVariable('manpage', $manpage);
                $view->setVariable('title', $title);
            }
            $command = GameClientResponse::COMMAND_OPENMANPAGEMENU;
            $message = $this->viewRenderer->render($view);
            $class = GameClientResponse::CLASS_RAW;
        }
        else {
            $class = GameClientResponse::CLASS_MUTED;
        }
        $this->gameClientResponse->setCommand($command)->addMessage($message, $class);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function listManpages($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            return $this->gameClientResponse->addMessage($this->translate('unknown command'))->send();
        }
        $manpages = $this->manpageRepo->findAll();
        foreach ($manpages as $manpage) {
            /** @var Manpage $manpage */
            $message = sprintf(
                '%-11s|%-11s|%s',
                $manpage->getId(),
                Manpage::$lookup[$manpage->getStatus()],
                $manpage->getSubject()
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        }
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
    public function addManpage($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            return $this->gameClientResponse->addMessage($this->translate('unknown command'))->send();
        }
        $parameter = $this->getNextParameter($contentArray, false, false, true, true);
        $subject = ($parameter) ? $parameter : 'new manpage title';
        $content = 'new manpage content';
        $manpage = new Manpage();
        $manpage->setAuthor($this->user->getProfile());
        $manpage->setUpdatedDateTime(NULL);
        $manpage->setCreatedDateTime(new \DateTime());
        $manpage->setParent(NULL);
        $manpage->setContent($content);
        $manpage->setSubject($subject);
        $manpage->setStatus(Manpage::STATUS_INVALID);
        $this->entityManager->persist($manpage);
        $this->entityManager->flush($manpage);
        return $this->editManpage($resourceId, [$manpage->getId()]);
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function editManpage($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            return $this->gameClientResponse->addMessage($this->translate('unknown command'))->send();
        }
        $manpageId = $this->getNextParameter($contentArray, false, true);
        if (!$manpageId) {
            return $this->listManpages($resourceId);
        }
        $manpage = $this->manpageRepo->find($manpageId);
        if (!$manpage) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid manpage id'))->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/manpage/edit-manpage.phtml');
        $view->setVariable('manpage', $manpage);
        $view->setVariable('showstatusdropdown', $this->hasRole(NULL, Role::ROLE_ID_ADMIN));
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_OPENMANPAGEMENU);
        // add the rendered view as the gmr message with css-class raw so that it will not wrap it in pre
        $this->gameClientResponse->addMessage($this->viewRenderer->render($view), GameClientResponse::CLASS_RAW);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param string $content
     * @param string $mpTitle
     * @param $entityId
     * @param int $status
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveManpage(
        $resourceId,
        $content = '===invalid content===',
        $mpTitle = '===invalid title===',
        $entityId,
        $status = Manpage::STATUS_INVALID
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        if (!$this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            return $this->gameClientResponse->addMessage($this->translate('unknown command'))->send();
        }
        $manpage = $this->manpageRepo->find($entityId);
        if (!$manpage) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid manpage id'))->send();
        }
        /** @var Manpage $manpage */
        $content = htmLawed($content, ['safe'=>1,'elements'=>'strong,i,ul,ol,li,p,a,br']);
        $mpTitle = htmLawed($mpTitle, ['safe'=>1,'elements'=>'strong']);
        $manpage->setSubject($mpTitle);
        $manpage->setContent($content);
        $manpage->setStatus($status);
        $manpage->setAuthor($this->user->getProfile());
        $manpage->setUpdatedDateTime(new \DateTime());
        // change status
        $manpage->setStatus($this->getNewStatus($manpage));
        $this->entityManager->flush($manpage);
        return $this->gameClientResponse->addMessage($this->translate('Manpage saved'), GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param Manpage $manpage
     * @param string $action
     * @return int
     */
    private function getNewStatus(Manpage $manpage, $action = self::ACTION_SAVE)
    {
        $newStatus = $manpage->getStatus();
        switch ($action) {
            default:
                break;
            case self::ACTION_SAVE:
                if ($this->hasRole(NULL, Role::ROLE_ID_MODERATOR, false)) {
                    switch ($manpage->getStatus()) {
                        default:
                            $newStatus = Manpage::STATUS_SUGGESTED;
                            break;
                    }
                }
                break;
        }
        return $newStatus;
    }

}
