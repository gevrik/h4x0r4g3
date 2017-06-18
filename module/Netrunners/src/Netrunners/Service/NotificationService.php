<?php

/**
 * Notification Service.
 * The service supplies methods that resolve logic around Notification objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\Notification;
use Netrunners\Entity\Profile;
use TmoAuth\Entity\User;
use Zend\View\Model\ViewModel;

class NotificationService extends BaseService
{

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showNotifications($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $unreadNotifications = $this->entityManager->getRepository('Netrunners\Entity\Notification')->findUnreadByProfile($profile);
        $view = new ViewModel();
        $view->setTemplate('netrunners/notification/list.phtml');
        $view->setVariable('notifications', $unreadNotifications);
        $response = array(
            'command' => 'showPanel',
            'type' => 'default',
            'content' => $this->viewRenderer->render($view),
            'silent' => true
        );
        return $response;
    }

    /**
     * @param $resourceId
     * @param $entityId
     * @param bool $all
     * @return bool
     */
    public function dismissNotification($resourceId, $entityId, $all = false)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        if ($all) {
            $notifications = $this->entityManager->getRepository('Netrunners\Entity\Notification')->findUnreadByProfile($profile);
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
        return true;
    }

}
