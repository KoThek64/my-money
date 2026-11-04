<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Category;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Category> */
final class ArchivedCategoryVoter extends Voter
{
    public const string MODIFY = 'MODIFY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MODIFY === $attribute
            && $subject instanceof Category;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($subject->isArchived()) {
            $vote?->addReason('La catégorie est archivée : elle est en lecture seule.');

            return false;
        }

        return true;
    }
}
