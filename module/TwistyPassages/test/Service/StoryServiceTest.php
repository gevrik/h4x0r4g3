<?php

namespace TwistyPassagesTest\Service;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class StoryServiceTest extends TestCase
{

    protected $entityManager;

    /**
     *
     */
    public function setUp()
    {
        parent::setUp();
        $this->entityManager = $this->prophesize(EntityManager::class);
    }

    public function testTest()
    {
        $this->assertTrue($this->entityManager->reveal() instanceof EntityManager);
    }

}
