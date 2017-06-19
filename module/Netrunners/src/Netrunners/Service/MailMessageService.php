<?php

/**
 * MailMessage Service.
 * The service supplies methods that resolve logic around MailMessage objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\MailMessage;
use Netrunners\Entity\Profile;
use Netrunners\Repository\MailMessageRepository;
use TmoAuth\Entity\User;

class MailMessageService extends BaseService
{

    /**
     * @const STARTING_NUMBER the default starting number for the mail program
     */
    const STARTING_NUMBER = 1;


    /**
     * Returns the total number of mails for the given profile.
     * @param Profile $profile
     * @return mixed
     */
    protected function getAmountMails(Profile $profile)
    {
        $mailMessageRepo = $this->entityManager->getRepository('Netrunners\Entity\MailMessage');
        /** @var MailMessageRepository $mailMessageRepo */
        return $mailMessageRepo->countByTotalMails($profile);
    }

    /**
     * Returns a string that shows how many unread messages a profile has in its inbox.
     * @param int $resourceId
     * @return array|bool
     */
    public function displayAmountUnreadMails($resourceId)
    {
        $mailMessageRepo = $this->entityManager->getRepository('Netrunners\Entity\MailMessage');
        /** @var MailMessageRepository $mailMessageRepo */
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $countUnreadMails = $mailMessageRepo->countByUnreadMails($profile);
        $response = array(
            'command' => 'showmessage',
            'message' => sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">You have %s unread mails in your inbox</pre>', $countUnreadMails)
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function enterMailMode($resourceId)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $mails = $this->entityManager->getRepository('Netrunners\Entity\MailMessage')->findBy(
            array(
                'recipient' => $profile
            )
        );
        $message = "NeoMail - version 0.1 - '?' for help, 'q' to quit";
        $message .= sprintf('<pre class="text-white"><strong>%-3s</strong> | <strong>%-20s</strong> | <strong>%-20s</strong> | <strong>%s</strong></pre>', '#', 'FROM', 'RECEIVED', 'SUBJECT');
        $mailNumber = 0;
        foreach ($mails as $mail) {
            /** @var MailMessage $mail */
            $mailNumber++;
            $preTag = ($mail->getReadDateTime()) ? '<pre>' : '<pre style="white-space: pre-wrap;" class="text-white">';
            $message .= sprintf(
                '%s%-3s | %-20s | %-20s | %s</pre>',
                $preTag,
                $mailNumber,
                ($mail->getAuthor()) ? $mail->getAuthor()->getUser()->getDisplayName() : "[SYSTEM-MAIL]",
                $mail->getSentDateTime()->format('Y/m/d H:i:s'),
                $mail->getSubject()
            );
        }
        $response = array(
            'command' => 'entermailmode',
            'message' => $message,
            'mailNumber' => $mailNumber
        );
        return $response;
    }

    public function exitMailMode()
    {
        $response = array(
            'command' => 'exitmailmode'
        );
        return $response;
    }

    /**
     * @param int $resourceId
     * @param $mailOptions
     * @return array|bool
     */
    public function displayMail($resourceId, $mailOptions)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        // init response
        $response = false;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $mailNumberArray = $mailOptions->currentMailNumber - 1;
        $mails = $this->entityManager->getRepository('Netrunners\Entity\MailMessage')->findBy(
            array(
                'recipient' => $profile
            )
        );
        $mail = (isset($mails[$mailNumberArray])) ? $mails[$mailNumberArray] : NULL;
        if (!$mail) {
            $response = array(
                'command' => 'showmessage',
                'type' => 'white',
                'message' => 'Unknown mail number'
            );
        }
        if (!$response) {
            /** @var MailMessage $mail */
            // mark mail as read
            if (!$mail->getReadDateTime() && $mail->getRecipient() == $profile) {
                $mail->setReadDateTime(new \DateTime());
                $this->entityManager->flush($mail);
            }
            /** @var MailMessage $mail */
            $authorName = ($mail->getAuthor()) ? $mail->getAuthor()->getUser()->getDisplayName() : 'SYSTEM';
            $message = sprintf('<pre class="text-white">Message: %s</pre>', $mailOptions->currentMailNumber);
            $message .= sprintf('<pre class="text-white">From: %s %s</pre>', $authorName, $mail->getSentDateTime()->format('Y/m/d H:i:s'));
            $message .= sprintf('<pre class="text-white">Subject: %s</pre>', $mail->getSubject());
            $message .= sprintf('<pre class="text-white">%s</pre>', $mail->getContent());
            $response = array(
                'command' => 'showmessage',
                'type' => 'white',
                'message' => $message
            );
        }
        return $response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @param $mailOptions
     * @return array|bool
     */
    public function deleteMail($resourceId, $contentArray, $mailOptions)
    {
        $clientData = $this->getWebsocketServer()->getClientData($resourceId);
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        // init response
        $response = false;
        if (!$parameter) {
            $mailNumber = $mailOptions->currentMailNumber;
        }
        else {
            $mailNumber = $parameter;
        }
        $mailNumberArray = $mailNumber - 1;
        $mails = $this->entityManager->getRepository('Netrunners\Entity\MailMessage')->findBy(
            array(
                'recipient' => $profile
            )
        );
        $mail = $mails[$mailNumberArray];
        if (!$mail) {
            $response = array(
                'command' => 'showmessage',
                'type' => 'danger',
                'message' => 'Invalid mail number'
            );
        }
        if (!$response) {
            /** @var MailMessage $mail */
            $message = sprintf('<pre style="white-space: pre-wrap;" class="text-sysmsg">Mail #%s has been deleted</pre>', $mailNumber);
            $this->entityManager->remove($mail);
            $this->entityManager->flush();
            $response = array(
                'command' => 'showmessage',
                'message' => $message
            );
        }
        return $response;
    }

}
