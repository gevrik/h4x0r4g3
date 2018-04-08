<?php

/**
 * Choice Service.
 * The service supplies methods that resolve logic around Choice objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Model\GameClientResponse;
use TmoAuth\Entity\Role;
use TwistyPassages\Entity\Choice;
use TwistyPassages\Entity\Passage;
use TwistyPassages\Entity\Story;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class ChoiceService extends BaseService
{

    /**
     * ChoiceService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
    }

    /**
     * @param $resourceId
     * @param int|null $storyId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function choiceAddCommand($resourceId, $storyId = null)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        /** @var Story $story */
        $story = ($storyId) ? $this->entityManager->find(Story::class, $storyId) : null;
        $choice = new Choice();
        $choice->setAdded(new \DateTime());
        $choice->setStory($story);
        $choice->setDescription(null);
        $choice->setTitle('new choice');
        $choice->setStatus(\TwistyPassages\Service\ChoiceService::STATUS_CREATED);
        $this->entityManager->persist($choice);
        $this->entityManager->flush($choice);
        return $this->gameClientResponse->addMessage('choice created', GameClientResponse::CLASS_INFO)->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function choiceListCommand($resourceId, $contentArray = [])
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $storyId = $this->getNextParameter($contentArray, false, true);
        $choiceRepo = $this->entityManager->getRepository(Choice::class);
        if (!$storyId) {
            $choices = $choiceRepo->findAll();
        }
        else {
            $choices = $choiceRepo->findBy(['story'=>$storyId]);
        }
        $headerMessage = sprintf(
            '%-11s|%-64s|%-32s|%-20s',
            $this->translate('CHOICE-ID'),
            $this->translate('TITLE'),
            $this->translate('STATUS'),
            $this->translate('DESC')
        );
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $messages = [];
        /** @var Choice $choice */
        foreach ($choices as $choice) {
            $messages[] = sprintf(
                '%-11s|%-64s|%-32s|%-20s',
                $choice->getId(),
                $choice->getTitle(),
                \TwistyPassages\Service\ChoiceService::$status[$choice->getStatus()],
                $choice->getDescription()
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
    public function choiceEditCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $choiceId) = $this->getNextParameter($contentArray, true, true);
        if (!$choiceId) {
            return $this->gameClientResponse->addMessage('no choice id given')->send();
        }
        /** @var Choice $choice */
        $choice = $this->entityManager->find(Choice::class, $choiceId);
        if (!$choice) {
            return $this->gameClientResponse->addMessage('invalid choice id')->send();
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
                $choice->setTitle($value);
                break;
            case 'status':
                $value = $this->getNextParameter($contentArray, false, true);
                $choice->setStatus($value);
                break;
            case 'story':
                $value = $this->getNextParameter($contentArray, false, true);
                /** @var Story $story */
                $story = $this->entityManager->find(Story::class, $value);
                if (!$story) {
                    return $this->gameClientResponse->addMessage('invalid story id')->send();
                }
                $choice->setStory($story);
                break;
            case 'targetpassage':
                $value = $this->getNextParameter($contentArray, false, true);
                /** @var Passage $targetPassage */
                $targetPassage = $this->entityManager->find(Passage::class, $value);
                if (!$targetPassage) {
                    return $this->gameClientResponse->addMessage('invalid passage id')->send();
                }
                $choice->setTargetPassage($targetPassage);
                break;
        }
        $this->entityManager->flush($choice);
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
    public function choiceEditorCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $choiceId = $this->getNextParameter($contentArray, false, true);
        if (!$choiceId) {
            return $this->choiceListCommand($resourceId);
        }
        $choice = $this->entityManager->find(Choice::class, $choiceId);
        if (!$choice) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid choice id'))->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/choice/edit-choice.phtml');
        $view->setVariable('choice', $choice);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_OPENMANPAGEMENU);
        // add the rendered view as the gmr message with css-class raw so that it will not wrap it in pre
        $this->gameClientResponse->addMessage($this->viewRenderer->render($view), GameClientResponse::CLASS_RAW);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $choiceId
     * @param string $content
     * @param string $title
     * @param int $status
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function savePassageCommand(
        $resourceId,
        $choiceId,
        $content = '===invalid passage desc===',
        $title = '===invalid passage title===',
        $status = 1
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        /** @var Choice $choice */
        $choice = $this->entityManager->find(Choice::class, $choiceId);
        if (!$choice) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid choice id'))->send();
        }
        $choice->setStatus($status);
        $choice->setTitle($title);
        $choice->setDescription($content);
        $this->entityManager->flush($choice);
        return $this->gameClientResponse
            ->setCommand(GameClientResponse::COMMAND_CLOSEPANEL)
            ->setSilent(true)
            ->send();
    }

}
