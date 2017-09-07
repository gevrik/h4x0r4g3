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
     * @return array|bool|false
     */
    public function showResearchers($resourceId)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            $researchers = $this->fileRepo->findByProfileAndType($profile, FileType::ID_RESEARCHER);
            $returnMessage = array();
            if (count($researchers) < 1) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-success">%s</pre>',
                        $this->translate('You do not have any researcher programs')
                    )
                ];
            }
            else {
                $returnMessage[] = sprintf(
                    '<pre style="white-space: pre-wrap;" class="text-sysmsg">%-32s|%-32s|%-3s|%-1s|%s</pre>',
                    $this->translate('NAME'),
                    $this->translate('NODE'),
                    $this->translate('PRG'),
                    $this->translate('R'),
                    $this->translate('DATA')
                );
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
                            '<pre style="white-space: pre-wrap;" class="text-white">%-32s|%-32s|%-3s|%-1s|%s</pre>',
                            $researcher->getName(),
                            ($researcher->getNode()) ? $researcher->getNode()->getName() : $this->translate('---'),
                            $progressString,
                            ($researcher->getRunning()) ? $this->translate('<span class="text-success">Y</span>') : $this->translate('<span class="text-danger">N</span>'),
                            $typeString . ' - ' . $idString
                        );
                        $this->response = [
                            'command' => 'showoutput',
                            'message' => $returnMessage
                        ];
                    }
                }
            }
        }
        return $this->response;
    }

    /**
     * @param $resourceId
     * @param $contentArray
     * @return array|bool|false
     */
    public function researchCommand($resourceId, $contentArray)
    {
        $this->initService($resourceId);
        if (!$this->user) return true;
        $profile = $this->user->getProfile();
        $currentNode = $profile->getCurrentNode();
        $this->response = $this->isActionBlocked($resourceId);
        if (!$this->response) {
            // check if there is a researcher program in the current node
            $researcher = $this->fileRepo->findOneRunningInNodeByTypeAndProfile($currentNode, $profile, FileType::ID_RESEARCHER);
            if (!$researcher) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('There is no running researcher program in this node that belongs to you')
                    )
                ];
            }
            /**
             * get the research type - this can be category to research a random type within that category or
             * type to research a specific type
             */
            list($contentArray, $researchType) = $this->getNextParameter($contentArray, true, false, false, true);
            if (!$this->response && !$researchType) {
                $this->response = [
                    'command' => 'showmessage',
                    'message' => sprintf(
                        '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                        $this->translate('Please specify a research type: "category" or "type"')
                    )
                ];
            }
            if (!$this->response) {
                $researchTypeString = '---';
                $researchIdString = '---';
                $data = NULL;
                $fileType = NULL;
                $fileCategory = NULL;
                switch ($researchType) {
                    default:
                        break;
                    case 'type':
                        $fileTypeString = $this->getNextParameter($contentArray, false, false, true, true);
                        if (empty($fileTypeString)) $fileTypeString = 'chat';
                        $fileType = $this->fileTypeRepo->findLikeName($fileTypeString);
                        if (!$fileType) {
                            $this->response = [
                                'command' => 'showmessage',
                                'message' => sprintf(
                                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                                    $this->translate('Please specify which file-type to research')
                                )
                            ];
                        }
                        if (!$this->response) {
                            /** @var FileType $fileType */
                            $data = [
                                'type' => 'file-type',
                                'id' => $fileType->getId(),
                                'progress' => 0
                            ];
                            $researchTypeString = $this->translate('file-type');
                            $researchIdString = $this->translate($fileType->getName());
                        }
                        break;
                    case 'category':
                        $fileCategoryString = $this->getNextParameter($contentArray, false, false, true, true);
                        if (empty($fileCategoryString)) $fileCategoryString = 'node';
                        $fileCategory = $this->fileCategoryRepo->findLikeName($fileCategoryString);
                        if (!$fileCategory) {
                            $this->response = [
                                'command' => 'showmessage',
                                'message' => sprintf(
                                    '<pre style="white-space: pre-wrap;" class="text-warning">%s</pre>',
                                    $this->translate('Please specify which file-category to research')
                                )
                            ];
                        }
                        if (!$this->response) {
                            /** @var FileCategory $fileCategory */
                            $data = [
                                'type' => 'category',
                                'id' => $fileCategory->getId(),
                                'progress' => 0
                            ];
                            $researchTypeString = $this->translate('category');
                            $researchIdString = $this->translate($fileCategory->getName());
                        }
                        break;
                }
                // now add the data to the researcher
                if (!$this->response && $data && $researcher) {
                    /** @var File $researcher */
                    $researcher->setData(json_encode($data));
                    $this->entityManager->flush($researcher);
                    $this->response = [
                        'command' => 'showmessage',
                        'message' => sprintf(
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] is now researching [%s: %s]</pre>'),
                            $researcher->getName(),
                            $researchTypeString,
                            $researchIdString
                        )
                    ];
                    // inform other players in node
                    $message = sprintf(
                        $this->translate('<pre style="white-space: pre-wrap;" class="text-muted">[%s] has changed some research settings on [%s]</pre>'),
                        $this->user->getUsername(),
                        $researcher->getName()
                    );
                    $this->messageEveryoneInNode($currentNode, $message, $profile, $profile->getId());
                }
            }
        }
        return $this->response;
    }

}
