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
use Netrunners\Entity\Geocoord;
use Netrunners\Entity\Node;
use Netrunners\Entity\Profile;
use Netrunners\Entity\System;
use Netrunners\Repository\FileRepository;
use Ratchet\ConnectionInterface;
use TmoAuth\Entity\Role;

class UtilityService extends BaseService
{

    /**
     * @param $clientData
     * @return bool|string
     */
    public function showPrompt($clientData)
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
        $fullPromptString = '[<span class="eeg">' . $profile->getEeg() . '/100</span>][<span class="willpower">' .
            $profile->getWillpower() . '/100</span>]<span class="prompt">[' . $userAtHostString . ':' . $promptString .
            '][' . $currentNode->getNodeType()->getShortName() . '][' . $currentNode->getLevel() . ']</span> ';
        return $fullPromptString;
    }

    /**
     * @param ConnectionInterface $from
     * @param $clientData
     * @param string $content
     * @return bool|ConnectionInterface
     */
    public function autocomplete(ConnectionInterface $from, $clientData, $content = '')
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $contentArray = explode(' ', $content);
        $stringToComplete = array_pop($contentArray);
        $filesInCurrentDirectory = $fileRepo->findByNodeOrProfile(
            $profile->getCurrentNode(),
            $profile
        );
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
            'command' => 'updateprompt',
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

    /**
     * @param int $resourceId
     * @return array|bool
     */
    public function showCommands($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $message = 'addconnection  addnode  attack  auction auctionfile  auctionbid bid  auctionbuyout buyout  auctions  auctionclaim claim  bgopacity  bug  cd  changepassword  clear  code  commands  connect  consider  defaultmra  deposit  dl  download  editnode  entityname  equipment  eset  execute  explore  factionchat fc  factionratings  factions  filecats  filemods  filename  filetypes  gc  harvest  help  home  idea  initarmor  inventory inv  invitations  jobs  kill  ls  mail  map  milkrun  mod modfile  mods  newbie  nodename  nodes  nodetype  options  passwd  ps  removeconnection removenode  repairmra  resources res  rm  say  scan  score  secureconnection  setemail  setlocale  showbalance  showmra showmilkrunaivatars  skillpoints  skills  sneak  stat  stealth  survey  system  time  touch  typo  ul  unload  unsecure  update updatefile  upgrademra  upgradenode  use  visible  vis  withdraw';
        $returnMessage = sprintf(
            '<pre style="white-space: pre-wrap;" class="text-white">%s</pre>',
            wordwrap($message, 120)
        );
        if ($this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            $message = 'listmanpages  addmanpage  editmanpage  modchat mc';
            $returnMessage .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-addon">%s</pre>',
                wordwrap($message, 120)
            );
        }
        if ($this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            $message = 'banip  cybermap  unbanip  banuser  unbanuser  clients  giveinvitation  goto  kick  nlist  setcredits  setsnippets  syslist';
            $returnMessage .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-info">%s</pre>',
                wordwrap($message, 120)
            );
        }
        if ($this->hasRole(NULL, Role::ROLE_ID_SUPERADMIN)) {
            $message = 'grantrole  removerole toggleadminmode';
            $returnMessage .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-danger">%s</pre>',
                wordwrap($message, 120)
            );
        }
        $response = array(
            'command' => 'showmessage',
            'message' => $returnMessage
        );
        return $response;
    }

    /**
     * @param $resourceId
     * @return array|bool|false
     */
    public function showMotd($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $this->response = $this->isActionBlocked($resourceId, true);
        if (!$this->response) {
            $message = '<pre style="white-space: pre-wrap;" class="text-sysmsg">MESSAGE OF THE DAY:</pre>';
            $message .= sprintf(
                '<pre style="white-space: pre-wrap;" class="text-attention">%s</pre>',
                wordwrap($this->getServerSetting(self::SETTING_MOTD), 120)
            );
            $this->response = [
                'command' => 'showmessage',
                'message' => $message
            ];
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param Geocoord $location
     * @return array|bool|false
     */
    public function updateSystemCoords($resourceId, Geocoord $location)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        if ($currentSystem->getProfile() === $profile) {
            $currentSystem->setGeocoords($location->getLat() . ',' . $location->getLng());
            $this->response = [
                'command' => 'showmessageprepend',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                    $this->translate('System coords successfully updated')
                )
            ];
        }
        else {
            $this->response = [
                'command' => 'showmessageprepend',
                'message' => sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                    $this->translate('Unable to update system coords - permission denied')
                )
            ];
        }
        return $this->response;
    }

}
