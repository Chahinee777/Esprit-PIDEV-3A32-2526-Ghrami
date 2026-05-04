<?php

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class BaseIntegrationTest extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        
        // Start transaction
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Commit transaction so data persists in database for verification
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->commit();
        }
        
        parent::tearDown();
    }

    /**
     * Flush and clear the entity manager
     */
    protected function flush(): void
    {
        $this->em->flush();
    }

    /**
     * Persist an entity
     */
    protected function persist(object $entity): void
    {
        $this->em->persist($entity);
    }
}

