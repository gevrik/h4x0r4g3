<?php

/**
 * Research Service.
 * The service supplies methods that resolve logic around researching file type recipes.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\File;
use Netrunners\Entity\FileCategory;
use Netrunners\Entity\FileType;
use Netrunners\Model\GameClientResponse;
use Netrunners\Repository\FileCategoryRepository;
use Netrunners\Repository\FileRepository;
use Netrunners\Repository\FileTypeRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class ResearchService extends BaseService
{

    /**
     * @var FileRepository
     */
    protected $fileRepo;

    /**
     * @var FileTypeRepository
     */
    protected $fileTypeRepo;

    /**
     * @var FileCategoryRepository
     */
    protected $fileCategoryRepo;


    /**
     * ResearchService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(EntityManager $entityManager, PhpRenderer $viewRenderer, Translator $translator)
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->fileRepo = $this->entityManager->getRepository('Netrunners\Entity\File');
        $this->fileTypeRepo = $this->entityManager->getRepository('Netrunners\Entity\FileType');
        $this->fileCategoryRepo = $this->entityManager->getRepository('Netrunners\Entity\FileCategory');
    }

    /**
     * @param $resourceId
     * @return bool|GameClientResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function showResearchers($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        $researchers = $this->fileRepo->findByProfileAndType($profile, FileType::ID_RESEARCHER);
        if (count($researchers) < 1) {
            $message = $this->translate('You do not have any researcher programs');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        else {
            $returnMessage = [];
            $headerMessage = sprintf(
                '%-32s|%-32s|%-3s|%-1s|%s',
                $this->translate('NAME'),
                $this->translate('NODE'),
                $this->translate('PRG'),
                $this->translate('R'),
                $this->translate('DATA')
            );
            $this->gameClientResponse->addMessage($headerMessage, GameClientResponse::CLASS_SYSMSG);
            foreach ($researchers as $researcher) {
                /** @var File $researcher */
                if ($researcher->getData()) {
                    $researcherData = json_decode($researcher->getData());
                    switch ($researcherData->type) {
                        default:
                            $idString = '---';
                            $typeString = '---';
                            $progressString = '---';
                            break;
                        case 'category':
                            $fileCategory = $this->entityManager->find('Netrunners\Entity\FileCategory', $researcherData->id);
                            $idString = $fileCategory->getName();
                            $typeString = $researcherData->type;
                            $progressString = (isset($researcherData->progress)) ? $researcherData->progress : '---';
                            break;
                        case 'file-type':
                            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', $researcherData->id);
                            $idString = $fileType->getName();
                            $typeString = $researcherData->type;
                            $progressString = (isset($researcherData->progress)) ? $researcherData->progress : '---';
                            break;
                    }
                    $returnMessage[] = sprintf(
                        '%-32s|%-32s|%-3s|%-1s|%s',
                        $researcher->getName(),
                        ($researcher->getNode()) ? $researcher->getNode()->getName() : $this->translate('---'),
                        $progressString,
                        ($researcher->getRunning()) ? $this->translate('<span class="text-success">Y</span>') : $this->translate('<span class="text-danger">N</span>'),
                        $typeString . ' - ' . $idString
                    );
                }
            }
            $this->gameClientResponse->addMessages($returnMessage);
        }
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
    public function researchCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return false;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $isBlocked = $this->isActionBlockedNew($resourceId);
        if ($isBlocked) {
            return $this->gameClientResponse->addMessage($isBlocked)->send();
        }
        // check if there is a researcher program in the current node
        $researcher = $this->fileRepo->findOneRunningInNodeByTypeAndProfile($currentNode, $profile, FileType::ID_RESEARCHER);
        if (!$researcher) {
            $message = $this->translate('There is no running researcher program in this node that belongs to you');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        /**
         * get the research type - this can be category to research a random type within that category or
         * type to research a specific type
         */
        list($contentArray, $researchType) = $this->getNextParameter($contentArray, true, false, false, true);
        if (!$researchType) {
            $message = $this->translate('Please specify a research type: "category" or "type"');
            return $this->gameClientResponse->addMessage($message)->send();
        }
        $data = NULL;
        $fileType = NULL;
        $fileCategory = NULL;
        switch ($researchType) {
            default:
                $message = $this->translate('Invalid research type');
                return $this->gameClientResponse->addMessage($message)->send();
            case 'type':
                $fileTypeString = $this->getNextParameter($contentArray, false, false, true, true);
                if (empty($fileTypeString)) $fileTypeString = 'chat';
                $fileType = $this->fileTypeRepo->findLikeName($fileTypeString);
                if (!$fileType) {
                    $message = $this->translate('Please specify which file-type to research');
                    return $this->gameClientResponse->addMessage($message)->send();
                }
                /** @var FileType $fileType */
                $data = [
                    'type' => 'file-type',
                    'id' => $fileType->getId(),
                    'progress' => 0
                ];
                $researchTypeString = $this->translate('file-type');
                $researchIdString = $this->translate($fileType->getName());
                break;
            case 'category':
                $fileCategoryString = $this->getNextParameter($contentArray, false, false, true, true);
                if (empty($fileCategoryString)) $fileCategoryString = 'node';
                $fileCategory = $this->fileCategoryRepo->findLikeName($fileCategoryString);
                // check if the given category exists
                if (!$fileCategory) {
                    $message = $this->translate('Please specify which file-category to research');
                    return $this->gameClientResponse->addMessage($message)->send();
                }
                // check if category can be researched
                /** @var FileCategory $fileCategory */
                if (!$fileCategory->getResearchable()) {
                    $message = $this->translate('Invalid category');
                    return $this->gameClientResponse->addMessage($message)->send();
                }
                /** @var FileCategory $fileCategory */
                $data = [
                    'type' => 'category',
                    'id' => $fileCategory->getId(),
                    'progress' => 0
                ];
                $researchTypeString = $this->translate('category');
                $researchIdString = $this->translate($fileCategory->getName());
                break;
        }
        // now add the data to the researcher
        /** @var File $researcher */
        $researcher->setData(json_encode($data));
        $this->entityManager->flush($researcher);
        $message = sprintf(
            $this->translate('[%s] is now researching [%s: %s]'),
            $researcher->getName(),
            $researchTypeString,
            $researchIdString
        );
        $this->gameClientResponse->addMessage($message, GameClientResponse::CLASS_SUCCESS);
        // inform other players in node
        $message = sprintf(
            $this->translate('[%s] has changed some research settings on [%s]'),
            $this->user->getUsername(),
            $researcher->getName()
        );
        $this->messageEveryoneInNodeNew($currentNode, $message, GameClientResponse::CLASS_MUTED, $profile, $profile->getId());
        return $this->gameClientResponse->send();
    }

}
