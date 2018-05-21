<?php

/**
 * Passage Service.
 * The service supplies methods that resolve logic around Passage objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Model\GameClientResponse;
use TmoAuth\Entity\Role;
use TwistyPassages\Entity\Passage;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

final class PassageService extends BaseService
{

    /**
     * PassageService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     * @param EntityGenerator $entityGenerator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator,
        EntityGenerator $entityGenerator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator, $entityGenerator);
    }

    /**
     * @param $resourceId
     * @param int|null $storyId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function passageAddCommand($resourceId, $storyId = null)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $story = ($storyId) ? $this->entityManager->find('TwistyPassages\Entity\Story', $storyId) : null;
        $passage = new Passage();
        $passage->setAdded(new \DateTime());
        $passage->setStory($story);
        $passage->setDescription('empty passage description');
        $passage->setTitle('empty passage title');
        $passage->setStatus(\TwistyPassages\Service\PassageService::STATUS_CREATED);
        $passage->setAllowChoiceSubmissions(false);
        $this->entityManager->persist($passage);
        $this->entityManager->flush($passage);
        return $this->gameClientResponse->addMessage('passage created', GameClientResponse::CLASS_INFO)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function passageListCommand($resourceId, $contentArray = [])
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $storyId = $this->getNextParameter($contentArray, false, true);
        $passageRepo = $this->entityManager->getRepository('TwistyPassages\Entity\Passage');
        if (!$storyId) {
            $passages = $passageRepo->findAll();
        }
        else {
            $passages = $passageRepo->findBy(['story'=>$storyId]);
        }
        $headerMessage = sprintf(
            '%-11s|%-64s|%-32s|%-20s',
            $this->translate('PASSAGE-ID'),
            $this->translate('TITLE'),
            $this->translate('STATUS'),
            $this->translate('CHOICES')
        );
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $messages = [];
        /** @var Passage $passage */
        foreach ($passages as $passage) {
            $messages[] = sprintf(
                '%-11s|%-64s|%-32s|%-20s',
                $passage->getId(),
                $passage->getTitle(),
                \TwistyPassages\Service\PassageService::$status[$passage->getStatus()],
                ($passage->getAllowChoiceSubmissions()) ? $this->translate('yes') : $this->translate('no')
            );
        }
        return $this->gameClientResponse->addMessages($messages)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function passageEditCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $passageId) = $this->getNextParameter($contentArray, true, true);
        if (!$passageId) {
            return $this->gameClientResponse->addMessage('no passage id given')->send();
        }
        /** @var Passage $passage */
        $passage = $this->entityManager->find('TwistyPassages\Entity\Passage', $passageId);
        if (!$passage) {
            return $this->gameClientResponse->addMessage('invalid passage id')->send();
        }
        list($contentArray, $propertyName) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$propertyName) {
            return $this->gameClientResponse->addMessage('no property name given')->send();
        }
        switch ($propertyName) {
            default:
                return $this->gameClientResponse->addMessage('invalid property name')->send();
            case 'title':
                $value = $this->getNextParameter($contentArray, false, false, true, true);
                $passage->setTitle($value);
                break;
            case 'status':
                $value = $this->getNextParameter($contentArray, false, true);
                $passage->setStatus($value);
                break;
            case 'allowchoicesubmissions':
                $value = $this->getNextParameter($contentArray, false, true);
                $passage->setStatus($value);
                break;
            case 'story':
                $value = $this->getNextParameter($contentArray, false, true);
                $story = $this->entityManager->find('TwistyPassages\Entity\Story', $value);
                if (!$story) {
                    return $this->gameClientResponse->addMessage('invalid story id')->send();
                }
                $passage->setStory($story);
                break;
        }
        $this->entityManager->flush($passage);
        $message = sprintf('%s set to %s', $propertyName, $value);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function passageEditorCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $passageId = $this->getNextParameter($contentArray, false, true);
        if (!$passageId) {
            return $this->passageListCommand($resourceId);
        }
        $passage = $this->entityManager->find('TwistyPassages\Entity\Passage', $passageId);
        if (!$passage) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid passage id'))->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/passage/edit-passage.phtml');
        $view->setVariable('passage', $passage);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_OPENMANPAGEMENU);
        // add the rendered view as the gmr message with css-class raw so that it will not wrap it in pre
        $this->gameClientResponse->addMessage($this->viewRenderer->render($view), GameClientResponse::CLASS_RAW);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $passageId
     * @param string $content
     * @param string $title
     * @param bool $allowChoiceSubmissions
     * @param int $status
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function savePassageCommand(
        $resourceId,
        $passageId,
        $content = '===invalid passage desc===',
        $title = '===invalid passage title===',
        $allowChoiceSubmissions = false,
        $status = 1
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        /** @var Passage $passage */
        $passage = $this->entityManager->find('TwistyPassages\Entity\Passage', $passageId);
        if (!$passage) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid passage id'))->send();
        }
        $passage->setStatus($status);
        $passage->setTitle($title);
        $passage->setDescription($content);
        $passage->setAllowChoiceSubmissions($allowChoiceSubmissions);
        $this->entityManager->flush($passage);
        return $this->gameClientResponse
            ->setCommand(GameClientResponse::COMMAND_CLOSEPANEL)
            ->setSilent(true)
            ->send();
    }

}
