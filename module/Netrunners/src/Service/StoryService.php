<?php

/**
 * Story Service.
 * The service supplies methods that resolve logic around Story objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Model\GameClientResponse;
use TmoAuth\Entity\Role;
use TwistyPassages\Entity\Passage;
use TwistyPassages\Entity\Story;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

final class StoryService extends BaseService
{

    /**
     * StoryService constructor.
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
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function storyAddCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $story = new Story();
        $story->setAdded(new \DateTime());
        $story->setAuthor($this->user);
        $story->setDescription('empty description');
        $story->setStatus(\TwistyPassages\Service\StoryService::STATUS_CREATED);
        $story->setTitle('new story');
        $this->entityManager->persist($story);
        $this->entityManager->flush($story);
        return $this->gameClientResponse->addMessage('story created', GameClientResponse::CLASS_INFO)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function storyListCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $headerMessage = sprintf(
            '%-11s|%-64s|%-32s|%-20s',
            $this->translate('STORY-ID'),
            $this->translate('STORY-NAME'),
            $this->translate('STORY-AUTHOR'),
            $this->translate('STORY-STATUS')
        );
        $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
        $messages = [];
        /** @var Story $story */
        foreach ($this->entityManager->getRepository('TwistyPassages\Entity\Story')->findAll() as $story) {
            $messages[] = sprintf(
                '%-11s|%-64s|%-32s|%-20s',
                $story->getId(),
                $story->getTitle(),
                $story->getAuthor()->getUsername(),
                \TwistyPassages\Service\StoryService::$status[$story->getStatus()]
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
    public function storyEditCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        list($contentArray, $storyId) = $this->getNextParameter($contentArray, true, true);
        if (!$storyId) {
            return $this->gameClientResponse->addMessage('no story id given')->send();
        }
        /** @var Story $story */
        $story = $this->entityManager->find('TwistyPassages\Entity\Story', $storyId);
        if (!$story) {
            return $this->gameClientResponse->addMessage('invalid story id')->send();
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
                $story->setTitle($value);
                break;
            case 'status':
                $value = $this->getNextParameter($contentArray, false, true);
                $story->setStatus($value);
                break;
            case 'startingpassage':
                $value = $this->getNextParameter($contentArray, false, true);
                $startingPassage = $this->entityManager->find(Passage::class, $value);
                if (!$startingPassage) {
                    return $this->gameClientResponse->addMessage('invalid passage id')->send();
                }
                $story->setStartingPassage($startingPassage);
                break;
        }
        $this->entityManager->flush($story);
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
    public function storyEditorCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        $storyId = $this->getNextParameter($contentArray, false, true);
        if (!$storyId) {
            return $this->storyListCommand($resourceId);
        }
        $story = $this->entityManager->find('TwistyPassages\Entity\Story', $storyId);
        if (!$story) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid story id'))->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/story/edit-story.phtml');
        $view->setVariable('story', $story);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_OPENMANPAGEMENU);
        // add the rendered view as the gmr message with css-class raw so that it will not wrap it in pre
        $this->gameClientResponse->addMessage($this->viewRenderer->render($view), GameClientResponse::CLASS_RAW);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $storyId
     * @param string $content
     * @param string $title
     * @param int $status
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveStoryCommand(
        $resourceId,
        $storyId,
        $content = '===invalid story desc===',
        $title = '===invalid story title===',
        $status = 1
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        if (!$this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            return $this->gameClientResponse->send();
        }
        /** @var Story $story */
        $story = $this->entityManager->find('TwistyPassages\Entity\Story', $storyId);
        if (!$story) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid story id'))->send();
        }
        $story->setStatus($status);
        $story->setTitle($title);
        $story->setDescription($content);
        $this->entityManager->flush($story);
        return $this->gameClientResponse
            ->setCommand(GameClientResponse::COMMAND_CLOSEPANEL)
            ->setSilent(true)
            ->send();
    }

}
