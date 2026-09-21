<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Doctrine\Filter\OwnerFilter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * RG-1.1 — arme l'OwnerFilter sur chaque requête HTTP. Priorité sous celle du
 * firewall (8), sinon le token ne serait pas encore posé.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final readonly class OwnerFilterListener
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filter = $this->entityManager->getFilters()->enable(OwnerFilter::NAME);
        $user = $this->security->getUser();

        // Visiteur anonyme : le filtre reste sans paramètre, donc bloquant.
        if ($user instanceof User && $user->getId() instanceof Uuid) {
            $filter->setParameter(OwnerFilter::PARAMETER, $user->getId(), UuidType::NAME);
        }
    }
}
