<?php

namespace App\Security;

use App\Entity\Recipe;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides who can change a recipe. access_control only answers "may this role enter /admin?";
 * the voter answers "may THIS user touch THIS recipe?", which depends on the object.
 *
 * Rule: only the author. A recipe without author (legacy data) can be changed by nobody.
 *
 * @extends Voter<string, Recipe>
 */
final class RecipeVoter extends Voter
{
    public const EDIT = 'RECIPE_EDIT';
    public const DELETE = 'RECIPE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Abstain (neither grant nor deny) for anything that is not ours.
        return \in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof Recipe;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false; // anonymous
        }

        // Compare ids, not objects: they are cheaper and do not depend on the identity map.
        $author = $subject->getAuthor();

        return null !== $author && $author->getId() === $user->getId();
    }
}
