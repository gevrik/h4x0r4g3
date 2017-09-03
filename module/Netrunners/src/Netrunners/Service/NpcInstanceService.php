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
                    $this->translate('roaming'),
                    ($npc->getRoaming()) ? $this->translate('yes') : $this->translate('no')
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

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function changeNpcName($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        /* npc param can be given as name or number, so we need to handle both */
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true);
        // check if they have specified the npc instance to change
        if (!$this->response && !$parameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify the name or number of the entity that you want to rename')
                )
            );
        }
        // now check if we can find that npc instance
        $npc = NULL;
        if (!$this->response && $parameter) {
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
        }
        // check if they can change the name
        if (!$this->response && $npc && $profile != $npc->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        $newName = $this->getNextParameter($contentArray, false, false, true, true);
        if (!$this->response && !$newName) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify a name for the entity (32-chars max, alpha-numeric only)')
                )
            );
        }
        $this->stringChecker($newName);
        if (!$this->response) {
            // turn spaces in name to underscores
            $name = str_replace(' ', '_', $newName);
            $npc->setName($name);
            $this->entityManager->flush($npc);
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-success">Entity name changed to [%s]</pre>'),
                    $name
                )
            );
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has edited [%s]</pre>'),
                $this->user->getUsername(),
                $name
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message);
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function esetCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        /* npc param can be given as name or number, so we need to handle both */
        // get parameter
        list($contentArray, $parameter) = $this->getNextParameter($contentArray, true);
        // check if they have specified the npc instance to change
        if (!$this->response && !$parameter) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify the name or number of the entity that you want to modify')
                )
            );
        }
        // now check if we can find that npc instance
        $npc = NULL;
        if (!$this->response && $parameter) {
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
        }
        // check if they can change the entity
        if (!$this->response && $npc && $profile != $npc->getProfile()) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Permission denied')
                )
            );
        }
        // get which property they want to change
        list($contentArray, $npcPropertyString) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$this->response && !$npcPropertyString) {
            $this->response = array(
                'command' => 'showmessage',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Please specify the property that you want to set (roaming, aggressive)')
                )
            );
        }
        if (!$this->response) {
            // get which value the property should be set to (if not given, default is off)
            $propertyValueString = $this->getNextParameter($contentArray, false, false, true, true);
            if (!$propertyValueString) $propertyValueString = 'off';
            switch ($propertyValueString) {
                default:
                    $propertyValue = 0;
                    $propertyValueString = 'off';
                    break;
                case 'on':
                    $propertyValue = 1;
                    break;
                case 'off':
                    $propertyValue = 0;
                    break;
            }
            switch ($npcPropertyString) {
                default:
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                            $this->translate('Please specify the property that you want to set (roaming, aggressive)')
                        )
                    );
                    break;
                case 'roaming':
                    $npc->setRoaming($propertyValue);
                    $this->entityManager->flush($npc);
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] [%s] set to [%s]</pre>'),
                            $npc->getName(),
                            $npcPropertyString,
                            $propertyValueString
                        )
                    );
                break;
                case 'aggressive':
                    $npc->setAggressive($propertyValue);
                    $this->entityManager->flush($npc);
                    $this->response = array(
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] [%s] set to [%s]</pre>'),
                            $npc->getName(),
                            $npcPropertyString,
                            $propertyValueString
                        )
                    );
                break;
            }
        }
        return $this->response;
    }

}
