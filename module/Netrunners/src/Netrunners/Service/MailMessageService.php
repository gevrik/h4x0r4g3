<?php

/**
 * MailMessage Service.
 * The service supplies methods that resolve logic around MailMessage objects.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\MailMessage;
use Netrunners\Entity\Profile;
use Netrunners\Repository\MailMessageRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class MailMessageService extends BaseService
{

    /**
     * @const STARTING_NUMBER the default starting number for the mail program
     */
    const STARTING_NUMBER = 1;

    /**
     * @var MailMessageRepository
     */
    protected $mailMessageRepo;


    /**
     * MailMessageService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->mailMessageRepo = $this->entityManager->getRepository('Netrunners\Entity\MailMessage');
    }

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
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $profile = $this->user->getProfile();
            $countUnreadMails = $this->mailMessageRepo->countByUnreadMails($profile);
            $this->response = [
                'command' => 'showmessage',
                'message' => sprintf(
                    $this->translate('<pre style="white-space: pre-wrap;" class="text-info">You have %s unread mails in your inbox</pre>'),
                    $countUnreadMails
                )
            ];
        }
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function enterMailMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $mails = $this->mailMessageRepo->findBy(
                [
                    'recipient' => $profile
                ]
            );
            $message = $this->translate("NeoMail - version 0.1 - '?' for help, 'q' to quit");
            $message .= sprintf(
                '<pre class="text-white"><strong>%-3s</strong> | <strong>%-20s</strong> | <strong>%-20s</strong> | <strong>%s</strong></pre>',
                $this->translate('#'),
                $this->translate('FROM'),
                $this->translate('RECEIVED'),
                $this->translate('SUBJECT')
            );
            $mailNumber = 0;
            foreach ($mails as $mail) {
                /** @var MailMessage $mail */
                $mailNumber++;
                $preTag = ($mail->getReadDateTime()) ? '<pre>' : '<pre style="white-space: pre-wrap;" class="text-white">';
                $message .= sprintf(
                    '%s%-3s | %-20s | %-20s | %s</pre>',
                    $preTag,
                    $mailNumber,
                    ($mail->getAuthor()) ? $mail->getAuthor()->getUser()->getDisplayName() : $this->translate("[SYSTEM-MAIL]"),
                    $mail->getSentDateTime()->format('Y/m/d H:i:s'),
                    $mail->getSubject()
                );
            }
            $this->response = [
                'command' => 'entermailmode',
                'message' => $message,
                'mailNumber' => $mailNumber
            ];
            // inform other players in node
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has entered mail-mode</pre>'),
                $this->user->getUsername()
            );
            $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @return array
     */
    public function exitMailMode($resourceId)
    {
        $this->initService($resourceId);
        $this->response = array(
            'command' => 'exitmailmode',
            'prompt' => $this->getWebsocketServer()->getUtilityService()->showPrompt($this->clientData)
        );
        // inform other players in node
        $message = sprintf(
            $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has exited mail-mode</pre>'),
            $this->user->getUsername()
        );
        $profile = $this->user->getProfile();
        $this->messageEveryoneInNode($profile->getCurrentNode(), $message, $profile, $profile->getId());
        return $this->response;
    }

    /**
     * @param int $resourceId
     * @param $mailOptions
     * @return array|bool
     */
    public function displayMail($resourceId, $mailOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $mailNumberArray = $mailOptions->currentMailNumber - 1;
        $mails = $this->mailMessageRepo->findBy(
            array(
                'recipient' => $profile
            )
        );
        $mail = (isset($mails[$mailNumberArray])) ? $mails[$mailNumberArray] : NULL;
        if (!$mail) {
            $this->response = array(
                'command' => 'showmessage',
                'type' => 'white',
                'message' => 'Unknown mail number'
            );
        }
        if (!$this->response) {
            /** @var MailMessage $mail */
            // mark mail as read
            if (!$mail->getReadDateTime() && $mail->getRecipient() == $profile) {
                $mail->setReadDateTime(new \DateTime());
                $this->entityManager->flush($mail);
            }
            /** @var MailMessage $mail */
            $authorName = ($mail->getAuthor()) ? $mail->getAuthor()->getUser()->getDisplayName() : 'SYSTEM';
            $message = sprintf(
                $this->translate('<pre class="text-white">Message: %s</pre>'),
                $mailOptions->currentMailNumber
            );
            $message .= sprintf(
                $this->translate('<pre class="text-white">From: %s %s</pre>'),
                $authorName,
                $mail->getSentDateTime()->format('Y/m/d H:i:s')
            );
            $message .= sprintf(
                $this->translate('<pre class="text-white">Subject: %s</pre>'),
                $mail->getSubject()
            );
            $message .= sprintf('<pre class="text-white">%s</pre>', $mail->getContent());
            $this->response = array(
                'command' => 'showmessage',
                'type' => 'white',
                'message' => $message
            );
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @param $mailOptions
     * @return array|bool
     */
    public function deleteMail($resourceId, $contentArray, $mailOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        if (!$parameter) {
            $mailNumber = $mailOptions->currentMailNumber;
        }
        else {
            $mailNumber = $parameter;
        }
        $mailNumberArray = $mailNumber - 1;
        $mails = $this->mailMessageRepo->findBy(
            array(
                'recipient' => $profile
            )
        );
        $mail = $mails[$mailNumberArray];
        if (!$mail) {
            $this->response = array(
                'command' => 'showmessage',
                'type' => 'danger',
                'message' => 'Invalid mail number'
            );
        }
        if (!$this->response) {
            /** @var MailMessage $mail */
            $message = sprintf(
                $this->translate('<pre style="white-space: pre-wrap;" class="text-success">Mail #%s has been deleted</pre>'),
                $mailNumber
            );
            $this->entityManager->remove($mail);
            $this->entityManager->flush();
            $this->response = array(
                'command' => 'showmessage',
                'message' => $message
            );
        }
        return $this->response;
    }

}
