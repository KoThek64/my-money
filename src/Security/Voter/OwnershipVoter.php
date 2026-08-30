<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Interface\OwnedByUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

/**
 * RG-1.2 — une entité n'est accessible qu'à son propriétaire, y compris en
 * forçant l'uuid dans l'URL. Couvre toute entité OwnedByUser, présente et à venir.
 *
 * @extends Voter<string, OwnedByUser>
 */
final class OwnershipVoter extends Voter
{
    public const string VIEW = 'VIEW';
    public const string EDIT = 'EDIT';
    public const string DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof OwnedByUser;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason("L'utilisateur n'est pas authentifié.");

            return false;
        }

        // supports() a déjà garanti le type du sujet.
        $ownerId = $subject->getUser()?->getId();
        $userId = $user->getId();

        // Une entité sans propriétaire n'appartient à personne : refus.
        if (!$ownerId instanceof Uuid || !$userId instanceof Uuid) {
            $vote?->addReason("L'entité n'a pas de propriétaire identifiable.");

            return false;
        }

        // Comparaison par uuid : l'user du token et celui de l'entité peuvent
        // être deux instances distinctes (rechargement depuis la session).
        if (!$ownerId->equals($userId)) {
            $vote?->addReason("L'entité appartient à un autre compte.");

            return false;
        }

        return true;
    }
}
