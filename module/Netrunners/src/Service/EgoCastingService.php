<?php

/**
 * EgoCasting Service.
 * The service supplies methods that resolve logic around ego-casting nodes.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Morph;
use Netrunners\Entity\MorphInstance;
use Netrunners\Model\GameClientResponse;
use TwistyPassages\Entity\ChoiceUser;
use TwistyPassages\Entity\PassageChoice;
use TwistyPassages\Entity\Story;
use TwistyPassages\Entity\StoryUser;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

final class EgoCastingService extends BaseService
{

    const ID_INTRO_STORY = 1;

    /**
     * EgoCastingService constructor.
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
     * @param $contentArray
     * @return bool|\Netrunners\Model\GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function egocastCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $command = $this->getNextParameter(
            $contentArray,
            false,
            false,
            true,
            true
        );
        switch ($command) {
            default: // story mode
                if ($currentPlayStory = $profile->getCurrentPlayStory()) {
                    $qb = $this->entityManager->createQueryBuilder();
                    $qb->select('cu');
                    $qb->from(ChoiceUser::class, 'cu');
                    $qb->where('cu.user = :user AND cu.story = :story');
                    $qb->orderBy('cu.added', 'desc');
                    $qb->setParameters([
                        'user' => $this->user,
                        'story' => $currentPlayStory
                    ]);
                    $madeChoices = $qb->getQuery()->getResult();
                    if (count($madeChoices) < 1) {
                        $currentPassage = $currentPlayStory->getStartingPassage();
                    }
                    else {
                        /** @var ChoiceUser $lastMadeChoice */
                        $lastMadeChoice = array_pop($madeChoices);
                        $currentPassage = $lastMadeChoice->getChoice()->getTargetPassage();
                    }
                    $currentChoices = $this->entityManager->getRepository(PassageChoice::class)->findBy([
                        'passage' => $currentPassage
                    ]);
                    $view = new ViewModel();
                    $view->setTemplate('netrunners/story/play.phtml');
                    $view->setVariable('story', $currentPlayStory);
                    $view->setVariable('madeChoices', $madeChoices);
                    $view->setVariable('currentPassage', $currentPassage);
                    $view->setVariable('currentChoices', $currentChoices);
                    $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOW_STORY_PANEL);
                    $this->gameClientResponse->addOption(
                        GameClientResponse::OPT_CONTENT,
                        $this->viewRenderer->render($view)
                    );
                    // inform other players in node
                    $message = sprintf(
                        $this->translate('[%s] has just ego-cast into a morph'),
                        $this->user->getUsername()
                    );
                    $this->messageEveryoneInNodeNew(
                        $profile->getCurrentNode(),
                        $message,
                        GameClientResponse::CLASS_MUTED,
                        $profile,
                        $profile->getId()
                    );
                    return $this->gameClientResponse->send();
                }
                else {
                    $qb = $this->entityManager->createQueryBuilder();
                    $qb->select('su');
                    $qb->from(StoryUser::class, 'su');
                    $qb->where('su.user = :user AND su.completed IS NULL');
                    $qb->setParameter('user', $this->user);
                    $stories = $qb->getQuery()->getResult();
                    if (count($stories) < 1) {
                        return $this->createNixMorph();
                    }
                }
                break;
                // TODO add rogue-like minigame
        }
        return false;
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function createNixMorph()
    {
        $profile = $this->user->getProfile();
        /** @var Story $story */
        $story = $this->entityManager->find(Story::class, self::ID_INTRO_STORY);
        /** @var Morph $caseMorph */
        $caseMorph = $this->entityManager->find(Morph::class, Morph::ID_CASE);
        $morphInstance = new MorphInstance();
        $morphInstance->setDescription($caseMorph->getDescription());
        $morphInstance->setName(sprintf("%s case morph", $this->user->getUsername()));
        $morphInstance->setProfile($profile);
        $morphInstance->setNpcInstance(null);
        $morphInstance->setMorph($caseMorph);
        $this->entityManager->persist($morphInstance);
        $profile->setMorph($morphInstance);
        $storyUserInstance = new StoryUser();
        $storyUserInstance->setAdded(new \DateTime());
        $storyUserInstance->setStory($story);
        $storyUserInstance->setCompleted(null);
        $storyUserInstance->setUser($this->user);
        $this->entityManager->persist($storyUserInstance);
        $profile->setCurrentPlayStory($story);
        $this->entityManager->flush();
        $message = $this->translate('SYSTEM-ERROR - NO VALID MORPH AVAILABLE FOR RESLEEVING');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_DANGER);
        $message = $this->translate('SYSTEM-ALERT - INJECTION - bef8:nix0:dd2d:3456');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_DANGER);
        $message = $this->translate('SYSTEM-RECOVERY - MORPH ASSIGNMENT SUCCESSFUL - PLEASE TRY AGAIN');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        return $this->gameClientResponse->send();
    }

}
