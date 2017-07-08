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
use Netrunners\Repository\ManpageRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class ManpageService extends BaseService
{

    const DEFAULT_MANPAGE_ID = 1;

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
     * @return array|bool|false
     */
    public function helpCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $showInConsole = false;
        $command = 'showmessage';
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
            $this->translate('Sorry, there is no manpage on that topic')
        );
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
                if ($manpage) {
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
                        $command = 'showoutput';
                        $message = [];
                        $message[] = sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                            $this->translate('Multiple manpages matched the keyword:')
                        );
                        foreach ($manpages as $manpage) {
                            /** @var Manpage $manpage */
                            $message[] = sprintf(
                                '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%s</pre>',
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
                $view->setVariable('title', $title);
            }
            $command = 'openmanpagemenu';
            $message = $this->viewRenderer->render($view);
        }
        $this->response = [
            'command' => $command,
            'message' => $message
        ];
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function listManpages($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isSuperAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $manpages = $this->manpageRepo->findAll();
            $messages = [];
            foreach ($manpages as $manpage) {
                /** @var Manpage $manpage */
                $messages[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-white">%-11s|%s</pre>',
                    $manpage->getId(),
                    $manpage->getSubject()
                );
            }
            $this->response = [
                'command' => 'showoutput',
                'message' => $messages
            ];
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function addManpage($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isSuperAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $parameter = $this->getNextParameter($contentArray, false, false, true, true);
            $subject = ($parameter) ? $parameter : 'new manpage';
            $content = 'new manpage content';
            $manpage = new Manpage();
            $manpage->setAuthor($this->user->getProfile());
            $manpage->setUpdatedDateTime(NULL);
            $manpage->setCreatedDateTime(new \DateTime());
            $manpage->setParent(NULL);
            $manpage->setContent($content);
            $manpage->setSubject($subject);
            $this->entityManager->persist($manpage);
            $this->entityManager->flush($manpage);
            $this->response = $this->editManpage($resourceId, [$manpage->getId()]);
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function editManpage($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isSuperAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $manpageId = $this->getNextParameter($contentArray, false, true);
            $manpage = $this->manpageRepo->find($manpageId);
            if (!$this->response && !$manpage) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Invalid manpage id')
                    )
                );
            }
            if (!$this->response) {
                $view = new ViewModel();
                $view->setTemplate('netrunners/manpage/edit-manpage.phtml');
                $view->setVariable('manpage', $manpage);
                $this->response = array(
                    'command' => 'openmanpagemenu',
                    'message' => $this->viewRenderer->render($view)
                );
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param string $content
     * @param string $mpTitle
     * @param $entityId
     * @return array|bool|false
     */
    public function saveManpage(
        $resourceId,
        $content = '===invalid content===',
        $mpTitle = '===invalid title===',
        $entityId
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->isSuperAdmin()) {
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%s</pre>',
                    $this->translate('unknown command')
                )
            ];
        }
        if (!$this->response) {
            $manpage = $this->manpageRepo->find($entityId);
            if (!$this->response && !$manpage) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('invalid manpage id')
                    )
                ];
            }
            if (!$this->response) {
                /** @var Manpage $manpage */
                $content = htmLawed($content, ['safe'=>1,'elements'=>'strong,i,ul,ol,li,p,a']);
                $mpTitle = htmLawed($mpTitle, ['safe'=>1,'elements'=>'strong']);
                $manpage->setSubject($mpTitle);
                $manpage->setContent($content);
                $manpage->setAuthor($this->user->getProfile());
                $manpage->setUpdatedDateTime(new \DateTime());
                $this->entityManager->flush($manpage);
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('Manpage saved')
                    )
                ];
            }
        }
        return $this->response;
    }

}
