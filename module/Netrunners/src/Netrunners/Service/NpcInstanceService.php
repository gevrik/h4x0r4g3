<?php

/**
 * NpcInstance Service.
 * The service supplies methods that resolve logic around NpcInstance objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Repository\NpcInstanceRepository;
use Netrunners\Repository\NpcRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class NpcInstanceService extends BaseService
{

    /**
     * @var NpcInstanceRepository
     */
    protected $npcInstanceRepo;

    /**
     * @var NpcRepository
     */
    protected $npcRepo;


    /**
     * NpcInstanceService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->npcInstanceRepo = $this->entityManager->getRepository('Netrunners\Entity\NpcInstance');
        $this->npcRepo = $this->entityManager->getRepository('Netrunners\Entity\Npc');
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function considerNpc($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            // get parameter
            $parameter = $this->getNextParameter($contentArray, false);
            $npc = $this->findNpcByNameOrNumberInCurrentNode($parameter);
            if (!$this->response && !$npc) {
                $this->response = array(
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('No such entity')
                    )
                );
            }
            if (!$this->response) {
                $messages = [];
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-sysmsg">Consideration info for [%s]</pre>'),
                    $npc->getName()
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('description'),
                    wordwrap($npc->getDescription(), 120)
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s/%s</pre>'),
                    $this->translate('eeg'),
                    $npc->getCurrentEeg(),
                    $npc->getMaxEeg()
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('snippets'),
                    $npc->getSnippets()
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('credits'),
                    $npc->getCredits()
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('level'),
                    $npc->getLevel()
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('aggressive'),
                    ($npc->getAggressive()) ? $this->translate('<span class="text-danger">yes</span>') : $this->translate('no')
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('stealthing'),
                    ($npc->getStealthing()) ? $this->translate('<span class="text-danger">yes</span>') : $this->translate('no')
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('type'),
                    $npc->getNpc()->getName()
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('owner'),
                    ($npc->getProfile()) ? $npc->getProfile()->getUser()->getUsername() : '---'
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('faction'),
                    ($npc->getFaction()) ? $npc->getFaction()->getName() : $this->translate('---')
                );
                $messages[] = sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-white">%-12s : %s</pre>'),
                    $this->translate('group'),
                    ($npc->getGroup()) ? $npc->getGroup()->getName() : $this->translate('---')
                );
                $this->response = [
                    'command' => 'showoutput',
                    'message' => $messages
                ];
            }
        }
        return $this->response;
    }

}
