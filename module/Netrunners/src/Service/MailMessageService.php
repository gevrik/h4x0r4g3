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
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\MailMessageRepository;
use Netrunners\Repository\ProfileRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Model\ViewModel;
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
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var ProfileRepository
     */
    protected $profileRepo;

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
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->profileRepo = $this->entityManager->getRepository('Netrunners\Entity\Profile');
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
     * @param bool $silent
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function displayAmountUnreadMails($resourceId, $silent = false)
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
        if ($silent) $this->gameClientResponse->setSilent(true);
        return $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_ATTENTION)->send();
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
    public function getFromString(MailMessage $mail)
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
        if (!$mail->getReadDateTime() && $mail->getRecipient() === $profile) {
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
     * @param null $author
     * @param string $subject
     * @param string $content
     * @param bool $flush
     * @param array $files
     * @return MailMessage
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createMail(
        Profile $recipient,
        $author = NULL,
        $subject = 'INVALID SUBJECT',
        $content = 'EMPTY CONTENT',
        $flush = false,
        array $files = []
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
        /** @var File $file */
        foreach ($files as $file) {
            $mailMessage->addAttachment($file);
        }
        if ($flush) {
            $this->entityManager->flush();
        }
        return $mailMessage;
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function manageMails($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/index.phtml');
        $mails = $this->mailMessageRepo->findBy(['recipient' => $profile]);
        $view->setVariable('mails', $mails);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is managing their mails'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailReadCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get parameter
        $parameter = $this->getNextParameter($contentArray, false, true);
        if (!$parameter) {
            $message = $this->translate(sprintf('Please specify the mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var MailMessage $mailMessage */
        $mailMessage = $this->mailMessageRepo->find($parameter);
        if (!$mailMessage) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getRecipient()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($mailMessage->getRecipient() !== $profile) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $mailMessage->setReadDateTime(new \DateTime());
        $this->entityManager->flush($mailMessage);
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/read.phtml');
        $view->setVariable('mail', $mailMessage);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] is reading a mail message'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailDetachCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get mail-id
        list($contentArray, $mailId) = $this->getNextParameter($contentArray, true, true);
        if (!$mailId) {
            $message = $this->translate(sprintf('Please specify the mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var MailMessage $mailMessage */
        $mailMessage = $this->mailMessageRepo->find($mailId);
        if (!$mailMessage) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getRecipient()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($mailMessage->getRecipient() !== $profile) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the attachment id
        $attachmentId = $this->getNextParameter($contentArray, false, true);
        if (!$attachmentId) {
            $message = $this->translate(sprintf('Please specify the attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var File $attachment */
        $attachment = $this->fileRepo->find($attachmentId);
        if (!$attachment) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$attachment->getMailMessage()) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($attachment->getMailMessage() !== $mailMessage) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // check if they have enough storage space
        if (!$this->canStoreFile($profile, $attachment)) {
            $message = $this->translate(sprintf('You do not have enough storage space to download that file'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good, we can retrieve the file
        $mailMessage->removeAttachment($attachment);
        $attachment->setProfile($profile);
        $this->entityManager->flush();
        // render output
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/read.phtml');
        $view->setVariable('mail', $mailMessage);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has detached a file from a mail message'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailAttachInfoCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get mail-id
        list($contentArray, $mailId) = $this->getNextParameter($contentArray, true, true);
        if (!$mailId) {
            $message = $this->translate(sprintf('Please specify the mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var MailMessage $mailMessage */
        $mailMessage = $this->mailMessageRepo->find($mailId);
        if (!$mailMessage) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getRecipient()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($mailMessage->getRecipient() !== $profile) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the attachment id
        $attachmentId = $this->getNextParameter($contentArray, false, true);
        if (!$attachmentId) {
            $message = $this->translate(sprintf('Please specify the attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var File $attachment */
        $attachment = $this->fileRepo->find($attachmentId);
        if (!$attachment) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$attachment->getMailMessage()) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($attachment->getMailMessage() !== $mailMessage) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good, we can show info about the file
        $this->generateFileInfo($attachment);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailAttachmentDeleteCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get mail-id
        list($contentArray, $mailId) = $this->getNextParameter($contentArray, true, true);
        if (!$mailId) {
            $message = $this->translate(sprintf('Please specify the mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var MailMessage $mailMessage */
        $mailMessage = $this->mailMessageRepo->find($mailId);
        if (!$mailMessage) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getRecipient()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($mailMessage->getRecipient() !== $profile) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // now get the attachment id
        $attachmentId = $this->getNextParameter($contentArray, false, true);
        if (!$attachmentId) {
            $message = $this->translate(sprintf('Please specify the attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var File $attachment */
        $attachment = $this->fileRepo->find($attachmentId);
        if (!$attachment) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$attachment->getMailMessage()) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($attachment->getMailMessage() !== $mailMessage) {
            $message = $this->translate(sprintf('Invalid attachment-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good, we can delete the file
        $mailMessage->removeAttachment($attachment);
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();
        // render output
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/read.phtml');
        $view->setVariable('mail', $mailMessage);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has deleted a file from a mail message'),
            $this->user->getUsername()
        );
        $this->messageEveryoneInNodeNew($profile->getCurrentNode(), $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailDeleteCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        // get mail-id
        $mailId = $this->getNextParameter($contentArray, false, true);
        if (!$mailId) {
            $message = $this->translate(sprintf('Please specify the mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var MailMessage $mailMessage */
        $mailMessage = $this->mailMessageRepo->find($mailId);
        if (!$mailMessage) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getRecipient()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($mailMessage->getRecipient() !== $profile) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        // all good, we can delete the message
        /** @var File $attachment */
        foreach ($mailMessage->getAttachments() as $attachment) {
            $mailMessage->removeAttachment($attachment);
            $this->entityManager->remove($attachment);
        }
        $this->entityManager->flush();
        $this->entityManager->remove($mailMessage);
        $this->entityManager->flush($mailMessage);
        // render output
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/index.phtml');
        $mails = $this->mailMessageRepo->findBy(['recipient' => $profile]);
        $view->setVariable('mails', $mails);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has deleted a mail message'),
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

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailCreateCommand($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/create.phtml');
        $usernames = $this->profileRepo->getAllUsernames();
        $view->setVariable('usernames', json_encode($usernames));
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_OPENMANPAGEMENU);
        // add the rendered view as the gmr message with css-class raw so that it will not wrap it in pre
        $this->gameClientResponse->addMessage($this->viewRenderer->render($view), GameClientResponse::CLASS_RAW);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function mailReplyCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $mailId = $this->getNextParameter($contentArray, false, true);
        if (!$mailId) {
            $message = $this->translate(sprintf('Please specify the mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /** @var MailMessage $mailMessage */
        $mailMessage = $this->mailMessageRepo->find($mailId);
        if (!$mailMessage) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getRecipient()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($mailMessage->getRecipient() !== $profile) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if (!$mailMessage->getAuthor()) {
            $message = $this->translate(sprintf('Invalid mail-id'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/reply.phtml');
        $recipient = $mailMessage->getAuthor();
        $subject = sprintf($this->translate('Re: %s'), $mailMessage->getSubject());
        $quotedTextLabel = sprintf(
            $this->translate('[%s] wrote at [%s]:'),
            $mailMessage->getAuthor()->getUser()->getUsername(),
            $mailMessage->getSentDateTime()->format('Y-m-d H:i:s')
        );
        $quotedMailText = $mailMessage->getContent();
        $quotedText = <<<EOD
<br />
<br />
<strong>$quotedTextLabel</strong>

<i>$quotedMailText</i>
EOD;
        $view->setVariables([
            'recipient' => $recipient,
            'subject' => $subject,
            'quotedText' => $quotedText
        ]);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_OPENMANPAGEMENU);
        // add the rendered view as the gmr message with css-class raw so that it will not wrap it in pre
        $this->gameClientResponse->addMessage($this->viewRenderer->render($view), GameClientResponse::CLASS_RAW);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param string $content
     * @param string $recipient
     * @param string $subject
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function sendMail(
        $resourceId,
        $content = '===invalid content===',
        $recipient = '===invalid title===',
        $subject = '===invalid subject==='
    )
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $recipientName = filter_var($recipient, FILTER_SANITIZE_STRING);
        $content = htmLawed($content, ['safe'=>1,'elements'=>'strong,i,ul,ol,li,p,br']);
        $subject = htmLawed($subject, ['safe'=>1,'elements'=>'strong']);
        $recipient = $this->profileRepo->findLikeName($recipientName);
        if (!$recipient) {
            $message = $this->translate(sprintf('Invalid recipient'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        if ($recipient->getUser()->getUsername() == $this->user->getUsername()) {
            $message = $this->translate(sprintf('We are starting to worry about you...'));
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $mailMessage = new MailMessage();
        $mailMessage->setAuthor($profile);
        $mailMessage->setRecipient($recipient);
        $mailMessage->setReadDateTime(null);
        $mailMessage->setContent($content);
        $mailMessage->setFileAuthor(null);
        $mailMessage->setFileRecipient(null);
        $mailMessage->setNpcAuthor(null);
        $mailMessage->setNpcRecipient(null);
        $mailMessage->setParent(null);
        $mailMessage->setSentDateTime(new \DateTime());
        $mailMessage->setSubject($subject);
        $this->entityManager->persist($mailMessage);
        $this->entityManager->flush($mailMessage);
        $view = new ViewModel();
        $view->setTemplate('netrunners/mail-message/index.phtml');
        $mails = $this->mailMessageRepo->findBy(['recipient' => $profile]);
        $view->setVariable('mails', $mails);
        $this->gameClientResponse->setCommand(GameClientResponse::COMMAND_SHOWPANEL);
        $this->gameClientResponse->addOption(GameClientResponse::OPT_CONTENT, $this->viewRenderer->render($view));
        return $this->gameClientResponse->send();
    }

}
