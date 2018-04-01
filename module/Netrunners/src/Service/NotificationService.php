<?php

/**
 * Notification Service.
 * The service supplies methods that resolve logic around Notification objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Notification;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\NotificationRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;

class NotificationService extends BaseService
{

    /**
     * @var NotificationRepository
     */
    protected $notificationRepo;


    /**
     * NotificationService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->notificationRepo = $this->entityManager->getRepository('Netrunners\Entity\Notification');
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showNotifications($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $unreadNotifications = $this->notificationRepo->findUnreadByProfile($profile);
        $view = new ViewModel();
        $view->setTemplate('netrunners/notification/list.phtml');
        $view->setVariable('notifications', $unreadNotifications);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOW_NOTIFICATIONS)->setSilent(true);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $entityId
     * @param bool $all
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function dismissNotification($resourceId, $entityId, $all = false)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        if ($all) {
            $notifications = $this->notificationRepo->findUnreadByProfile($profile);
            foreach ($notifications as $notification) {
                /** @var Notification $notification */
                $this->entityManager->remove($notification);
            }
            $this->entityManager->flush();
        }
        else {
            $notification = $this->entityManager->find('Netrunners\Entity\Notification', $entityId);
            if ($notification && $notification->getProfile() == $profile) {
                $this->entityManager->remove($notification);
                $this->entityManager->flush();
            }
        }
        return false;
    }

}
