<?php

namespace Netrunners\View\Helper;

use Doctrine\ORM\EntityManager;
use Interop\Container\ContainerInterface;
use Netrunners\Entity\GroupRole;
use Netrunners\Entity\GroupRoleInstance;
use Netrunners\Entity\Profile;
use Netrunners\Repository\ProfileFactionRatingRepository;
use Zend\View\Helper\AbstractHelper;

class ProfileGroupHelper extends AbstractHelper
{

    /**
     * @var EntityManager
     */
    protected $entityManager;


    /**
     * ProfileGroupHelper constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->entityManager = $container->get(EntityManager::class);
    }

    /**
     * @param Profile $profile
     * @return string
     */
    public function getJoinDate(Profile $profile)
    {
        $group = $profile->getGroup();
        if ($group) {
            $griRepo = $this->entityManager->getRepository('Netrunners\Entity\GroupRoleInstance');
            $roles = $griRepo->findBy(
                ['member' => $profile, 'group' => $group],
                ['added' => 'asc']
            );
            if (!empty($roles)) {
                /** @var GroupRoleInstance $earliestRole */
                $earliestRole = $roles[0];
                $addedDate = $earliestRole->getAdded();
                return $addedDate->format('Y-m-d H:i:s');
            }
        }
        return '---';
    }

    /**
     * @param Profile $profile
     * @return string
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getRating(Profile $profile)
    {
        $group = $profile->getGroup();
        if ($group) {
            /** @var ProfileFactionRatingRepository $ratingRepo */
            $ratingRepo = $this->entityManager->getRepository('Netrunners\Entity\ProfileFactionRating');
            $profileFactionRating = $ratingRepo->getProfileFactionRating($profile, $group->getFaction());
            return $profileFactionRating;
        }
        return '---';
    }

    /**
     * @param Profile $profile
     * @return string
     */
    public function getRolesString(Profile $profile)
    {
        $group = $profile->getGroup();
        $rolesString = '';
        if ($group) {
            $griRepo = $this->entityManager->getRepository('Netrunners\Entity\GroupRoleInstance');
            $roles = $griRepo->findBy(
                ['member' => $profile, 'group' => $group]
            );
            /** @var GroupRoleInstance $role */
            foreach ($roles as $role) {
                $rolesString .= $role->getGroupRole()->getName() . '  ';
            }
        }
        return $rolesString;
    }

}
