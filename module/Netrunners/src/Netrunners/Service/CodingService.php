<?php

/**
 * Coding Service.
 * The service supplies methods that involve coding of programs.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Netrunners\Entity\File;
use Netrunners\Entity\Profile;
use TmoAuth\Entity\User;

class CodingService extends BaseService
{

    /**
     * @const MIN
     */
    const MIN_LEVEL = 1;

    const MAX_LEVEL = 100;

    public function enterCodeMode($clientData)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $message = "NeoCode - version 0.1 - '?' for help, 'q' to quit";
        $response = array(
            'command' => 'enterCodeMode',
            'type' => 'sysmsg',
            'message' => $message
        );
        return $response;
    }

    public function commandLevel($clientData, $contentArray, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        // init message
        $message = '';
        if (!$parameter) {
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "Choose a number between 1 and 100.");
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => $returnMessage
            );
        }
        else {
            $value = false;
            $parameter = (int)$parameter;
            if ($parameter < 1 || $parameter > 100) {
                $command = 'showMessage';
                $message = sprintf('<pre style="white-space: pre-wrap;">%s</pre>', "Choose a number between 1 and 100.");
            }
            else {
                $command = 'setCodeLevel';
                $value = $parameter;
                $message = sprintf('level set to [%s]', $parameter);
            }
            $response = array(
                'command' => $command,
                'value' => $value,
                'type' => 'sysmsg',
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    public function commandOptions($clientData, $contentArray, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        $message = '';
        foreach ($codeOptions as $optionLabel => $optionValue)
        {
            $message .= sprintf('<pre style="white-space: pre-wrap;">%-10s: %s</pre>', $optionLabel, $optionValue);
        }
        $response = array(
            'command' => 'showMessage',
            'type' => 'sysmsg',
            'message' => $message
        );
        // init response
        return $response;
    }

    public function commandType($clientData, $contentArray, $codeOptions)
    {
        $user = $this->entityManager->find('TmoAuth\Entity\User', $clientData->userId);
        if (!$user) return true;
        /** @var User $user */
        $profile = $user->getProfile();
        /** @var Profile $profile */
        // get parameter
        $parameter = array_shift($contentArray);
        // init message
        $message = '';
        if (!$parameter) {
            foreach (File::$fileTypeData as $fileTypeData) {
                if ($fileTypeData[File::TYPE_KEY_CODABLE]) {
                    $message .= $fileTypeData[File::TYPE_KEY_LABEL] . ' ';
                }
            }
            $returnMessage = sprintf('<pre style="white-space: pre-wrap;">%s</pre>', $message);
            $response = array(
                'command' => 'showMessage',
                'type' => 'sysmsg',
                'message' => $returnMessage
            );
        }
        else {
            $value = false;
            switch ($parameter) {
                default:
                    $command = 'showMessage';
                    $message = 'Invalid type given';
                    break;
                case 'chatclient':
                case 'dataminer':
                    $command = 'setCodeType';
                    $value = $parameter;
                    $message = sprintf('type set to [%s]', $parameter);
                    break;
            }
            $response = array(
                'command' => $command,
                'value' => $value,
                'type' => 'sysmsg',
                'message' => $message
            );
        }
        // init response
        return $response;
    }

    public function exitCodeMode()
    {
        $response = array(
            'command' => 'exitCodeMode'
        );
        return $response;
    }

}
