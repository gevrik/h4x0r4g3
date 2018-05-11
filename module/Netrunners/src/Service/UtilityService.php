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
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileRepository;
use Ratchet\ConnectionInterface;
use TmoAuth\Entity\Role;

final class UtilityService extends BaseService
{

    /**
     * @param null|object $clientData
     * @return bool|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showPrompt($clientData = NULL)
    {
        if (!$clientData) return false;
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return false;
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $currentNode = $profile->getCurrentNode();
        /** @var Node $currentNode */
        $currentSystem = $currentNode->getSystem();
        /** @var System $currentSystem */
        // init prompt string
        $promptString = $currentNode->getName();
        $userAtHostString = $user->getUsername() . '@' . $currentSystem->getName();
        $sneaking = ($profile->getStealthing()) ? '[<span class="text-warning">*</span>]' : '[<span class="text-muted">*</span>]';
        $fullPromptString = '[<span class="eeg"><span class="current-eeg">' . $profile->getEeg() . '</span>/100</span>][<span class="willpower">' .
            $profile->getWillpower() . '/100</span>]<span class="prompt">[' . $userAtHostString . ':' . $promptString .
            '][' . $currentNode->getNodeType()->getShortName() . '][' . $currentNode->getLevel() . ']</span>' . $sneaking .  ' ';
        return $fullPromptString;
    }

    /**
     * @param null|object $clientData
     * @return bool|string
     * @throws \Exception
     */
    public function showPromptMemory($clientData = NULL)
    {
        if (!$clientData) return false;
        $user = $this->getWebsocketServer()->findById('users', $clientData->userId);
        if (!$user) return false;
        $profile = $this->getWebsocketServer()->findById('profiles', $user['profileId']);
        if (!$profile) return false;
        $currentNode = $this->getWebsocketServer()->findById('nodes', $profile['currentNodeId']);
        $currentNodeType = $this->getWebsocketServer()->findById('nodetypes', $currentNode['nodeTypeId']);
        $currentSystem = $this->getWebsocketServer()->findById('systems', $currentNode['systemId']);
        // init prompt string
        $promptString = $currentNode['name'];
        $userAtHostString = $user['username'] . '@' . $currentSystem['name'];
        $sneaking = ($profile['stealthing']) ? '[<span class="text-warning">*</span>]' : '[<span class="text-muted">*</span>]';
        $fullPromptString = '[<span class="eeg"><span class="current-eeg">' . $profile['eeg'] . '</span>/100</span>][<span class="willpower">' .
            $profile['willpower'] . '/100</span>]<span class="prompt">[' . $userAtHostString . ':' . $promptString .
            '][' . $currentNodeType['shortName'] . '][' . $currentNode['level'] . ']</span>' . $sneaking .  ' ';
        return $fullPromptString;
    }

    /**
     * @param ConnectionInterface $from
     * @param $clientData
     * @param string $content
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function autocomplete(ConnectionInterface $from, $clientData, $content = '')
    {
        $fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        /** @var FileRepository $fileRepo */
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return false;
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
        /** @noinspection PhpUndefinedFieldInspection */
        $response = new GameClientResponse($from->resourceId);
        $response->setCommand(GameClientResponse::COMMAND_UPDATEPROMPT);
        $response->addOption(GameClientResponse::OPT_CONTENT, $promptContent);
        return $response->send();
    }

    /**
     * To create random ipv6 addresses for the systems.
     * @param $length
     * @param string $sep
     * @param int $space
     * @return string
     */
    public function getRandomAddress($length, $sep = ":", $space = 4) {
        if (function_exists("openssl_random_pseudo_bytes")) {
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
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showCommands($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $message = <<<EOD
addconnection  addnode  a attack  auction auctionfile  auctionbid bid  auctionbids bids  auctionbuyout buyout  
auctioncancel cancelauction  auctions  auctionclaim  bgopacity  bug  cancel  cd  changepassword  claim  clear  close
code  commands  connect  con consider  creategroup  createpasskey passkey  date  decompile  defaultmra  deposit  dl 
download  editfile  editnode  entityname  equipment  eset  exe execute  explore  factionchat fc  factionratings  
factions  filecats  filemods  filename  filetypes  gc  harvest  help  home  idea  initarmor  inventory inv  invitations
jobs  joinfaction  k kill  killp killprocess  listpasskeys passkeys  logout  ls  mail  manageparts  map  milkrun  
mission  missiondetails  mod modfile  mods  newbie  ninfo  nodename  nodes  nodetype  nset  open  options  parts  
passwd  ps  recipes  removeconnection rmconnection  removenode rmnode  removepasskey rmpasskey  removeresource rmres  
repairmra  reply  research  resources res  rm  say  scan  score  secureconnection  setemail  setlocale  slay 
showbalance  showmra showmilkrunaivatars  showresearch  skillpoints  skills  sneak  stat  stealth  survey  system  
tell  time  touch  typo  ul  unload upload  updatesystemcoords  unsecure  update updatefile  upgrademra  upgradenode  
use  visible  vis  withdraw
EOD;
        $returnMessage = sprintf(
            '%s',
            wordwrap($message, 120)
        );
        $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_WHITE);
        if ($this->hasRole(NULL, Role::ROLE_ID_MODERATOR)) {
            $message = 'listmanpages  addmanpage  editmanpage  modchat mc';
            $returnMessage = sprintf(
                '%s',
                wordwrap($message, 120)
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_ADDON);
        }
        if ($this->hasRole(NULL, Role::ROLE_ID_ADMIN)) {
            $message = <<<EOD
banip  cybermap  unbanip  banuser  unbanuser  clients  giveinvitation  goto  kickclient  nlist  setcredits  setmotd  
setsnippets  showusers  syslist
EOD;
            $returnMessage = sprintf(
                '%s',
                wordwrap($message, 120)
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_INFO);
        }
        if ($this->hasRole(NULL, Role::ROLE_ID_SUPERADMIN)) {
            $message = <<<EOD
grantrole  invokefile  invokefilemod invokenpc  removerole  toggleadminmode  setfileproperty silefilemodproperty  
setfiletypeproperty  setnpcproperty
EOD;
            $returnMessage = sprintf(
                '%s',
                wordwrap($message, 120)
            );
            $this->gameClientResponse->addMessage($returnMessage, GameClientResponse::CLASS_DANGER);
        }
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    public function showMotd($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $isBlocked = $this->isActionBlockedNew($resourceId, true);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $message = $this->translate('MESSAGE OF THE DAY:');
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SYSMSG);
        $message = wordwrap($this->getServerSetting(self::SETTING_MOTD), 120);
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_ATTENTION);
        return $this->gameClientResponse->send();
    }

    /**
     * @param $resourceId
     * @param Geocoord $location
     * @param bool $flush
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function updateSystemCoords($resourceId, Geocoord $location, $flush = false)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $currentSystem = $currentNode->getSystem();
        if ($currentSystem->getProfile() === $profile) {
            $currentSystem->setGeocoords($location->getLat() . ',' . $location->getLng());
            if ($flush) $this->entityManager->flush($currentSystem);
            $message = $this->translate('System coords successfully updated');
            $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        }
        else {
            $message = $this->translate('Unable to update system coords - permission denied');
            $this->gameClientResponse->addMessage($message);
        }
        return $this->gameClientResponse->send();
    }

}
