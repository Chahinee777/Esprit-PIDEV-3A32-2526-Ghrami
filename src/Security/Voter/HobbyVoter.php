<?php

namespace App\Security\Voter;

use App\Entity\Hobby;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class HobbyVoter extends Voter
{
    const VIEW = 'HOBBY_VIEW';
    const EDIT = 'HOBBY_EDIT';
    const DELETE = 'HOBBY_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Hobby;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $hobby = $subject;

        return match($attribute) {
            self::VIEW => $this->canView($hobby, $user),
            self::EDIT => $this->canEdit($hobby, $user),
            self::DELETE => $this->canDelete($hobby, $user),
            default => false,
        };
    }

    private function canView(Hobby $hobby, User $user): bool
    {
        return $hobby->user->id === $user->id;
    }

    private function canEdit(Hobby $hobby, User $user): bool
    {
        return $hobby->user->id === $user->id;
    }

    private function canDelete(Hobby $hobby, User $user): bool
    {
        return $hobby->user->id === $user->id;
    }
}
