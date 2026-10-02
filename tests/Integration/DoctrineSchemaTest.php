<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Smoke — le mapping est cohérent et la base de test lui correspond.
 */
final class DoctrineSchemaTest extends KernelTestCase
{
    /**
     * Rouge si une entité change sans migration, ou si une migration n'a pas été jouée.
     */
    public function testMappingIsValidAndDatabaseIsInSync(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $exitCode = $tester->run(['command' => 'doctrine:schema:validate']);

        self::assertSame(0, $exitCode, $tester->getDisplay());
    }
}
