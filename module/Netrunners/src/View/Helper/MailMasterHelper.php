<?php

namespace Netrunners\View\Helper;

use Interop\Container\ContainerInterface;
use Netrunners\Entity\MailMessage;
use Netrunners\Service\MailMessageService;
use Zend\View\Helper\AbstractHelper;

class MailMasterHelper extends AbstractHelper
{

    /**
     * @var MailMessageService
     */
    protected $mailMessageService;

    public function __construct(ContainerInterface $container)
    {
        $this->mailMessageService = $container->get(MailMessageService::class);
    }

    /**
     * @param MailMessage $mailMessage
     * @return string
     */
    public function getFromString(MailMessage $mailMessage)
    {
        return $this->mailMessageService->getFromString($mailMessage);
    }

}
