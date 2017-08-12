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
use Netrunners\Entity\FileType;
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
    }

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
            list($contentArray, $researchType) = $this->getNextParameter($contentArray);
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
                $data = NULL;
                $fileType = NULL;
                switch ($researchType) {
                    default:
                        break;
                    case 'type':
                        $fileTypeString = $this->getNextParameter($contentArray, false, false, true, true);
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
                                'type' => 'type',
                                'id' => $fileType->getId()
                            ];
                        }
                        break;
                    case 'category':
                        $data = [
                            'type' => 'category',
                            'id' => NULL
                        ];
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
                            $this->translate('<pre style="white-space: pre-wrap;" class="text-success">[%s] is now researching [%s%s]</pre>'),
                            $researcher->getName(),
                            $researchType,
                            ($fileType) ? ' ' . $fileType->getName() : ''
                        )
                    ];
                }
            }
        }
        return $this->response;
    }

}
