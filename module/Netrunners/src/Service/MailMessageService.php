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
use Netrunners\Entity\File;
use Netrunners\Entity\MailMessage;
use Netrunners\Entity\NpcInstance;
use Netrunners\Entity\Profile;
use Netrunners\Model\GameClientResponse;
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
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getAmountMails(Profile $profile)
    {
        $mailMessageRepo = $this->entityManager->getRepository('Netrunners\Entity\MailMessage');
        /** @var MailMessageRepository $mailMessageRepo */
        return $mailMessageRepo->countByTotalMails($profile);
    }

    /**
     * Returns a string that shows how many unread messages a profile has in its inbox.
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function displayAmountUnreadMails($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $profile = $this->user->getProfile();
        $countUnreadMails = $this->mailMessageRepo->countByUnreadMails($profile);
        $message = sprintf(
            $this->translate('You have %s unread mails in your inbox'),
            $countUnreadMails
        );
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_INFO)->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function enterMailMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $mails = $this->mailMessageRepo->findBy(
            [
                'recipient' => $profile
            ]
        );
        $message = $this->translate("NeoMail - version 0.1 - '?' for help, 'q' to quit");
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $message = sprintf(
            '<strong>%-3s</strong> | <strong>%-20s</strong> | <strong>%-20s</strong> | <strong>%s</strong>',
            $this->translate('#'),
            $this->translate('FROM'),
            $this->translate('RECEIVED'),
            $this->translate('SUBJECT')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $mailNumber = 0;
        foreach ($mails as $mail) {
            /** @var MailMessage $mail */
            $mailNumber++;
            $preTag = ($mail->getReadDateTime()) ? '<span>' : '<span class="text-white">';
            $fromString = $this->getFromString($mail);
            $message = sprintf(
                '%s%-3s | %-20s | %-20s | %s</span>',
                $preTag,
                $mailNumber,
                $fromString,
                $mail->getSentDateTime()->format('Y/m/d H:i:s'),
                $mail->getSubject()
            );
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_MUTED);
        }
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_ENTERMAILMODE)->addOption(GameClientResponse::OPT_MAIL_NUMBER, $mailNumber);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has entered mail-mode'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param MailMessage $mail
     * @return string
     */
    private function getFromString(MailMessage $mail)
    {
        $result = "[SYSTEM-MAIL]";
        if ($mail->getAuthor()) {
            $result = $mail->getAuthor()->getUser()->getDisplayName();
        }
        if ($mail->getNpcAuthor()) {
            $result = $mail->getNpcAuthor()->getName();
        }
        if ($mail->getFileAuthor()) {
            $result = $mail->getFileAuthor()->getName();
        }
        return $result;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function exitMailMode($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_EXITMAILMODE);
        $profile = $this->user->getProfile();
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has exited mail-mode'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $mailOptions
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function displayMail($resourceId, $mailOptions)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $mailNumberArray = $mailOptions->currentMailNumber - 1;
        $mails = $this->mailMessageRepo->findBy(
            array(
                'recipient' => $profile
            )
        );
        $mail = (isset($mails[$mailNumberArray])) ? $mails[$mailNumberArray] : NULL;
        if (!$mail) {
            return $this->gameClientResponse->addMessage($this->translate('Invalid mail number'))->send();
        }
        /** @var MailMessage $mail */
        // mark mail as read
        if (!$mail->getReadDateTime() && $mail->getRecipient() == $profile) {
            $mail->setReadDateTime(new \DateTime());
            $this->entityManager->flush($mail);
        }
        $authorName = $this->getFromString($mail);
        $message = sprintf(
            $this->translate('Message: %s'),
            $mailOptions->currentMailNumber
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $message = sprintf(
            $this->translate('From: %s %s'),
            $authorName,
            $mail->getSentDateTime()->format('Y/m/d H:i:s')
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf(
            $this->translate('Subject: %s'),
            $mail->getSubject()
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_WHITE);
        $message = sprintf('%s', $mail->getContent());
        $this->gameClientResponse->addMessage(wordwrap($message, 120), GameClientResponse::CLASS_WHITE);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @param $mailOptions
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
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
            return $this->gameClientResponse->addMessage($this->translate('Invalid mail number'), GameClientResponse::CLASS_DANGER)->send();
        }
        /** @var MailMessage $mail */
        $message = sprintf(
            $this->translate('Mail #%s has been deleted'),
            $mailNumber
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        $this->entityManager->remove($mail);
        $this->entityManager->flush();
        return $this->gameClientResponse->send();
    }

    /**
     * @param Profile $recipient
     * @param Profile|NpcInstance|File NULL $author
     * @param string $subject
     * @param string $content
     * @param bool $flush
     * @return MailMessage
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createMail(
        Profile $recipient,
        $author = NULL,
        $subject = 'INVALID SUBJECT',
        $content = 'EMPTY CONTENT',
        $flush = false
    )
    {
        $mailMessage = new MailMessage();
        $mailMessage->setContent($content);
        if ($author instanceof Profile) {
            $mailMessage->setAuthor($author);
        }
        else {
            $mailMessage->setAuthor(NULL);
        }
        if ($author instanceof NpcInstance) {
            $mailMessage->setNpcAuthor($author);
        }
        else {
            $mailMessage->setNpcAuthor(NULL);
        }
        if ($author instanceof File) {
            $mailMessage->setFileAuthor($author);
        }
        else {
            $mailMessage->setFileAuthor(NULL);
        }
        $mailMessage->setParent(NULL);
        $mailMessage->setReadDateTime(NULL);
        $mailMessage->setRecipient($recipient);
        $mailMessage->setSentDateTime(new \DateTime());
        $mailMessage->setSubject($subject);
        $this->entityManager->persist($mailMessage);
        if ($flush) {
            $this->entityManager->flush($mailMessage);
        }
        return $mailMessage;
    }

}
