<?php

namespace App\Dto;

use App\Enum\Difficulty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * JSON body accepted by POST/PUT /api/recipes. It is a separate class from the Recipe entity
 * on purpose: the client can only send these fields (no id, author or createdAt), so there is
 * no way to set a field that is not meant to be set from outside.
 *
 * Defaults make a missing field fail validation (NotBlank/Positive/Choice) with a 422
 * instead of a PHP error.
 */
final class RecipeInput
{
    /**
     * @param list<mixed> $tags ids of existing tags
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $title = '',

        #[Assert\NotBlank]
        public readonly string $description = '',

        #[Assert\Positive]
        #[Assert\LessThanOrEqual(1440, message: 'The preparation time cannot exceed 24 hours (1440 minutes).')]
        public readonly int $prepTimeMinutes = 0,

        // A string checked with Choice, not the Difficulty enum: a wrong value then fails with a clear
        // message instead of the serializer's generic "should be of type int|string".
        #[Assert\Choice(callback: [self::class, 'difficultyValues'], message: 'Choose a difficulty: easy, medium or hard.')]
        public readonly string $difficulty = '',

        #[Assert\All([new Assert\Type('int')])]
        public readonly array $tags = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public static function difficultyValues(): array
    {
        return array_column(Difficulty::cases(), 'value');
    }

    public function difficulty(): Difficulty
    {
        return Difficulty::from($this->difficulty);
    }
}
