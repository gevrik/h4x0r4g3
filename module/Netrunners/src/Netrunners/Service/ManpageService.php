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
        $this->response = $this->isActionBlocked($resourceId, true);
        $showInConsole = false;
        $command = 'showmessage';
        $keyword = false;
        $message = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
            $this->translate('Sorry, there is no manpage on that topic')
        );
        if (!$this->response) {
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
        }
        if (!$keyword) {
            $manpage = $this->manpageRepo->find(self::DEFAULT_MANPAGE_ID);
            $message = sprintf(
                '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                $manpage->getContent()
            );
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
                        '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
                        $manpage->getContent()
                    );
                }
            }
        }
        $this->response = [
            'command' => $command,
            'message' => $message
        ];
        return $this->response;
    }

}
