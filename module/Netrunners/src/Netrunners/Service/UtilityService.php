<?php

/**
 * Utility Service.
 * The service supplies utility methods that are not related to an entity.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Ratchet\ConnectionInterface;

class UtilityService extends BaseService
{

    /**
     * @param ConnectionInterface $from
     * @param $clientData
     * @return bool|ConnectionInterface
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showPrompt(ConnectionInterface $from, $clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        // init prompt string
        $promptString = $currentNode->getName();
        $userAtHostString = $user->getUsername() . '@' . $currentSystem->getName();
        $fullPromptString = '<span class="prompt">[' . $userAtHostString . ':' . $promptString . '][' . Node::$data[$currentNode->getType()]['shortname'] . '][' . $currentNode->getLevel() . ']</span> ';
        $response = array(
            'command' => 'showPrompt',
            'message' => $fullPromptString
        );
        return $from->send(json_encode($response));
    }

    /**
     * Autocomplete a partially typed file or folder name.
     * @param ConnectionInterface $from
     * @param $clientData
     * @param string $content
     * @return bool|ConnectionInterface
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function autocomplete(ConnectionInterface $from, $clientData, $content = '')
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $stringToComplete = array_pop($contentArray);
        $filesInCurrentDirectory = $this->entityManager->getRepository('Netrunners\Entity\File')->findFileInSystemByName(
            $profile->getCurrentDirectory()->getSystem(),
            $profile->getCurrentDirectory()
        );
        $fileResults = array();
        $fileFound = false;
        foreach ($filesInCurrentDirectory as $cdFile) {
            /** @var File $cdFile */
            if (substr($cdFile->getName(), 0, strlen($stringToComplete) ) === $stringToComplete) {
                $contentArray[] = $cdFile->getName();
                $fileFound = true;
                break;
            }
        }
        if ($fileFound) {
            $promptContent = implode(' ', $contentArray);
        }
        else {
            $promptContent = $content;
        }
        $response = array(
            'command' => 'updatePrompt',
            'message' => $promptContent
        );
        return $from->send(json_encode($response));
    }

    /**
     * To create random ipv6 addresses for the systems.
     * @param $length
     * @param string $sep
     * @param int $space
     * @return string
     */
    public function getRandomAddress($length, $sep = ":", $space = 4) {
        if (function_exists("mcrypt_create_iv")) {
            $r = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        } else if (function_exists("openssl_random_pseudo_bytes")) {
            $r = openssl_random_pseudo_bytes($length);
        } else if (is_readable('/dev/urandom')) {
            $r = file_get_contents('/dev/urandom', false, null, 0, $length);
        } else {
            $i = 0;
            $r = "";
            while($i ++ < $length) {
                $r .= chr(mt_rand(0, 255));
            }
        }
        return wordwrap(substr(bin2hex($r), 0, $length), $space, $sep, true);
    }

}
