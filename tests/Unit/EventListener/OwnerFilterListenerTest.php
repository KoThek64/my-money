<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\OwnerFilterListener;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class OwnerFilterListenerTest extends TestCase
{
    /**
     * Le filtre est armé une fois, par la requête principale : une sous-requête
     * (fragment, page d'erreur) ne doit pas y retoucher.
     */
    public function testSubRequestLeavesTheFilterAlone(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getFilters');

        $listener = new OwnerFilterListener(self::createStub(Security::class), $entityManager);

        $listener(new RequestEvent(
            self::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
        ));
    }
}
