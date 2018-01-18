<?php

/**
 * SystemGenerator Service.
 * The service supplies methods that resolve logic around generating random system layouts.
 * @version 1.0
 * @author gevrik gevrik@totalmadownage.com
 * @copyright TMO
 */

namespace Netrunners\Service;

use Doctrine\ORM\EntityManager;
use Netrunners\Entity\Connection;
use Netrunners\Entity\Faction;
use Netrunners\Entity\File;
use Netrunners\Entity\FileType;
use Netrunners\Entity\Geocoord;
use Netrunners\Entity\Node;
use Netrunners\Entity\NodeType;
use Netrunners\Entity\Npc;
use Netrunners\Entity\System;
use Netrunners\Repository\CompanyNameRepository;
use Netrunners\Repository\GeocoordRepository;
use Netrunners\Repository\NodeTypeRepository;
use Netrunners\Repository\SystemRepository;
use Zend\Mvc\I18n\Translator;
use Zend\View\Renderer\PhpRenderer;

class SystemGeneratorService extends BaseService
{

    const SECURITY_WHITE_STRING = 'white';
    const SECURITY_GREEN_STRING = 'green';
    const SECURITY_ORANGE_STRING = 'orange';
    const SECURITY_RED_STRING = 'red';
    const SECURITY_RED_ULTRAVIOLET = 'ultra-violet';

    const TOTAL_NODE_LEVEL_MOD = 10;

    /**
     * @var NodeTypeRepository
     */
    protected $nodeTypeRepo;

    /**
     * @var null|Node
     */
    protected $previousClusterCpu = NULL;

    /**
     * @var bool
     */
    protected $needIo = true;

    /**
     * SystemGeneratorService constructor.
     * @param EntityManager $entityManager
     * @param PhpRenderer $viewRenderer
     * @param Translator $translator
     */
    public function __construct(
        EntityManager $entityManager,
        PhpRenderer $viewRenderer,
        Translator $translator
    )
    {
        parent::__construct($entityManager, $viewRenderer, $translator);
        $this->nodeTypeRepo = $this->entityManager->getRepository('Netrunners\Entity\NodeType');
    }

    /**
     * @param int $levels
     * @param Faction|NULL $faction
     * @return System
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function generateRandomSystem($levels = 1, Faction $faction = NULL)
    {
        $randomNpcFactionId = ($faction) ? $faction->getId() : mt_rand(1, 6);
        $faction = $this->entityManager->find('Netrunners\Entity\Faction', $randomNpcFactionId);
        switch ($faction->getId()) {
            default:
                $zone = 'global';
                break;
            case 1:
            case 2:
                $zone = 'aztech';
                break;
            case 3:
            case 4:
                $zone = 'euro';
                break;
            case 5:
            case 6:
                $zone = 'asia';
                break;
        }
        $addy = $this->getWebsocketServer()->getUtilityService()->getRandomAddress(32);
        $companyNameRepo = $this->entityManager->getRepository('Netrunners\Entity\CompanyName');
        /** @var CompanyNameRepository $companyNameRepo */
        $coordRepo = $this->entityManager->getRepository('Netrunners\Entity\Geocoord');
        /** @var GeocoordRepository $coordRepo */
        $randomCoord = $coordRepo->findOneRandomInZone($zone);
        /** @var Geocoord $randomCoord */
        $nameData = $companyNameRepo->getRandomCompanyName();
        $name = $nameData['content'];
        $systemRepo = $this->entityManager->getRepository('Netrunners\Entity\System');
        /** @var SystemRepository $systemRepo */
        $instanceCount = $systemRepo->countLikeName($name);
        $name = $name . '-' . ($instanceCount+1);
        $system = new System();
        $system->setProfile(NULL);
        $system->setName($name);
        $system->setIntegrity(100);
        $system->setNoclaim(true);
        $system->setFaction($faction);
        $system->setGroup(NULL);
        $system->setAddy($addy);
        $system->setAlertLevel(0);
        $system->setGeocoords($randomCoord->getLat() . ',' . $randomCoord->getLat());
        $system->setMaxSize(System::FACTION_MAX_SYSTEM_SIZE);
        $this->entityManager->persist($system);
        for ($i=1;$i<=$levels;$i++) {
            $this->generateCpuCluster($system, $i);
        }
        $this->entityManager->flush();
        $this->previousClusterCpu = NULL;
        $this->needIo = true;
        return $system;
    }

    /**
     * @param System $system
     * @param $cpuLevel
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function generateCpuCluster(System $system, $cpuLevel)
    {
        $previousNode = false;
        $maxNodeLevels = $cpuLevel * self::TOTAL_NODE_LEVEL_MOD;
        $now = new \DateTime();
        $firstNode = true;
        while ($maxNodeLevels > 0) {
            // init node level
            $minLevel = $cpuLevel*1;
            $maxLevel = $cpuLevel*2;
            if ($maxLevel > NodeService::MAX_NODE_LEVEL) $maxLevel = NodeService::MAX_NODE_LEVEL;
            $nodeLevel = mt_rand($minLevel, $maxLevel);
            // the first node will be an io if needed
            if ($this->needIo) {
                $nodeTypeId = NodeType::ID_IO;
                $this->needIo = false;
                $nodeLevel = 1;
            }
            // no io-needed (anymore)
            else {
                if ($nodeLevel >= $maxNodeLevels) {
                    $nodeTypeId = NodeType::ID_CPU;
                    $nodeLevel = $cpuLevel;
                    $maxNodeLevels = 0;
                }
                else {
                    $nodeTypeId = $this->getRandomNodeType();
                }
            }
            switch ($nodeTypeId) {
                default:
                    $secured = (mt_rand(1, 100) <= 50) ? true : false;
                    break;
                case NodeType::ID_FIREWALL:
                    $secured = false;
                    break;
                case NodeType::ID_CPU:
                    $secured = true;
                    break;
                case NodeType::ID_BANK:
                    $secured = true;
                    break;
            }
            $nodeType = $this->nodeTypeRepo->find($nodeTypeId);
            /** @var NodeType $nodeType */
            $node = new Node();
            $node->setNopvp(false);
            $node->setNomob(false);
            $node->setNodeType($nodeType);
            $node->setNoclaim(true);
            $node->setLevel($nodeLevel);
            $node->setData(NULL);
            $node->setDescription($nodeType->getDescription());
            $node->setIntegrity(100);
            $node->setCreated($now);
            $node->setName($nodeType->getShortName());
            $node->setProfile(NULL);
            $node->setSystem($system);
            $this->entityManager->persist($node);
            if ($firstNode) {
                if ($this->previousClusterCpu) {
                    $secured = true;
                    $connectiona = new Connection();
                    $connectiona->setCreated($now);
                    $connectiona->setLevel($this->previousClusterCpu->getLevel());
                    $connectiona->setIsOpen(($secured)?false:true);
                    $connectiona->setType(($secured)?Connection::TYPE_CODEGATE:Connection::TYPE_NORMAL);
                    $connectiona->setSourceNode($this->previousClusterCpu);
                    $connectiona->setTargetNode($node);
                    $this->entityManager->persist($connectiona);
                    $connectionb = new Connection();
                    $connectionb->setCreated($now);
                    $connectionb->setLevel($nodeLevel);
                    $connectionb->setIsOpen(($secured)?false:true);
                    $connectionb->setType(($secured)?Connection::TYPE_CODEGATE:Connection::TYPE_NORMAL);
                    $connectionb->setSourceNode($node);
                    $connectionb->setTargetNode($this->previousClusterCpu);
                    $this->entityManager->persist($connectionb);
                }
                $firstNode = false;
            }
            if ($previousNode) {
                /** @var Node $previousNode */
                $connectiona = new Connection();
                $connectiona->setCreated($now);
                $connectiona->setLevel($previousNode->getLevel());
                $connectiona->setIsOpen(($secured)?false:true);
                $connectiona->setType(($secured)?Connection::TYPE_CODEGATE:Connection::TYPE_NORMAL);
                $connectiona->setSourceNode($previousNode);
                $connectiona->setTargetNode($node);
                $this->entityManager->persist($connectiona);
                $connectionb = new Connection();
                $connectionb->setCreated($now);
                $connectionb->setLevel($nodeLevel);
                $connectionb->setIsOpen(($secured)?false:true);
                $connectionb->setType(($secured)?Connection::TYPE_CODEGATE:Connection::TYPE_NORMAL);
                $connectionb->setSourceNode($node);
                $connectionb->setTargetNode($previousNode);
                $this->entityManager->persist($connectionb);
            }
            if (!$previousNode) {
                $previousNode = $node;
            }
            else {
                if (mt_rand(1, 100) <= 45) $previousNode = $node;
            }
            if ($nodeTypeId == NodeType::ID_CPU) $this->previousClusterCpu = $node;
            $this->populateNode($node, $system);
            $maxNodeLevels -= $nodeLevel;
        }
    }

    /**
     * @param Node $node
     * @param System $system
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    private function populateNode(Node $node, System $system)
    {
        $nodeType = $node->getNodeType();
        $spawnType = NULL;
        switch ($nodeType->getId()) {
            default:
                if (mt_rand(1, 100)<=50) {
                    $spawnType = Npc::ID_MURPHY_VIRUS;
                }
                else {
                    $spawnType = Npc::ID_KILLER_VIRUS;
                }
                $homeNode = false;
                $faction = NULL;
                break;
            case NodeType::ID_FIREWALL:
                $spawnType = Npc::ID_BOUNCER_ICE;
                $homeNode = true;
                $faction = $system->getFaction();
                break;
            case NodeType::ID_TERMINAL:
            case NodeType::ID_DATABASE:
                $spawnType = Npc::ID_WORKER_PROGRAM;
                $homeNode = true;
                $faction = $system->getFaction();
                break;
            case NodeType::ID_CPU:
                $spawnType = Npc::ID_DEBUGGER_PROGRAM;
                $homeNode = true;
                $faction = $system->getFaction();
                break;
        }
        if ($spawnType) {
            $npc = $this->entityManager->find('Netrunners\Entity\Npc', $spawnType);
            /** @var Npc $npc */
            $this->spawnNpcInstance($npc, $node, NULL, $faction, NULL, ($homeNode) ? $node : NULL);
        }
        $fileTypeId = NULL;
        switch ($nodeType->getId()) {
            default:
                break;
            case NodeType::ID_TERMINAL:
                $fileTypeId = (mt_rand(1, 100)<=50) ? FileType::ID_COINMINER : NULL;
                break;
            case NodeType::ID_DATABASE:
                $fileTypeId = (mt_rand(1, 100)<=50) ? FileType::ID_DATAMINER : NULL;
                break;
        }
        if ($fileTypeId) {
            $fileType = $this->entityManager->find('Netrunners\Entity\FileType', $fileTypeId);
            /** @var FileType $fileType */
            $file = new File();
            $file->setIntegrity($node->getLevel()*10);
            $file->setMaxIntegrity($node->getLevel()*10);
            $file->setName($fileType->getName());
            $file->setProfile(NULL);
            $file->setLevel($node->getLevel()*10);
            $file->setCreated(new \DateTime());
            $file->setSystem($system);
            $file->setData(NULL);
            $file->setNpc(NULL);
            $file->setModified(NULL);
            $file->setMailMessage(NULL);
            $file->setNode($node);
            $file->setRunning(true);
            $file->setFileType($fileType);
            $file->setCoder(NULL);
            $file->setExecutable($fileType->getExecutable());
            $file->setSize($fileType->getSize());
            $file->setVersion(1);
            $this->entityManager->persist($file);
        }
    }

    /**
     * @return mixed
     */
    private function getRandomNodeType()
    {
        $possibleTypes = [
            NodeType::ID_BANK,
            NodeType::ID_TERMINAL,
            NodeType::ID_DATABASE,
            NodeType::ID_MEMORY,
            NodeType::ID_STORAGE,
            NodeType::ID_BB,
            NodeType::ID_CODING,
            NodeType::ID_FIREWALL,
            NodeType::ID_MONITORING,
            NodeType::ID_RAW
        ];
        return $possibleTypes[mt_rand(0, count($possibleTypes)-1)];
    }

}
