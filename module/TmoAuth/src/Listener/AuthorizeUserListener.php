<?php
namespace TmoAuth\Listener;

use Netrunners\Entity\Profile;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\Event;

class AuthorizeUserListener extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events)
    {
        $sharedManager = $events->getSharedManager();
        $this->listeners[] = $sharedManager->attach('ZfcUser\Service\User', 'register', array($this, 'onRegister'));
        $this->listeners[] = $sharedManager->attach('ZfcUser\Service\User', 'register.post', array($this, 'onRegisterPost'));
    }

    public function onRegister(Event $e)
    {
        $sm = $e->getTarget()->getServiceManager();
        $em = $sm->get('doctrine.entitymanager.orm_default');
        $user = $e->getParam('user');
        $config = $sm->get('config');
        $criteria = array('roleId' => $config['zfcuser']['new_user_default_role']);
        $defaultUserRole = $em->getRepository('TmoAuth\Entity\Role')->findOneBy($criteria);

        if ($defaultUserRole !== null)
        {
            $user->addRole($defaultUserRole);
        }
    }

    public function onRegisterPost(Event $e)
    {
        $sm = $e->getTarget()->getServiceManager();
        $em = $sm->get('doctrine.entitymanager.orm_default');
        $user = $e->getParam('user');
        $form = $e->getParam('form');
        // create profile for user
        $profile = new Profile();
        $profile->setUser($user);
        $em->persist($profile);
        $em->flush($profile);

    }
}