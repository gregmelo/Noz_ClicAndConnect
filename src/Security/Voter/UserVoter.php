<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Allow editing self
        if ($attribute === self::EDIT && $user === $targetUser) {
            return true;
        }

        // Check hierarchy
        $currentLevel = $this->getMaxRoleLevel($user);
        $targetLevel = $this->getMaxRoleLevel($targetUser);

        return $currentLevel > $targetLevel;
    }

    private function getMaxRoleLevel(User $user): int
    {
        $roles = $user->getRoles();
        $maxLevel = 0;

        foreach ($roles as $role) {
            $level = match ($role) {
                'ROLE_DEVELOPER' => 4,
                'ROLE_SUPER_ADMIN' => 3,
                'ROLE_ADMIN' => 2,
                'ROLE_EMPLOYEE' => 1,
                default => 0,
            };
            if ($level > $maxLevel) {
                $maxLevel = $level;
            }
        }

        return $maxLevel;
    }
}
