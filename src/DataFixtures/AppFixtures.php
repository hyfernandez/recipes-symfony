<?php

namespace App\DataFixtures;

use App\Entity\Recipe;
use App\Entity\Tag;
use App\Enum\Difficulty;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    private const TAG_NAMES = [
        'Vegan', 'Vegetarian', 'Dessert', 'Quick', 'Gluten-free', 'Breakfast', 'Italian', 'Spicy',
    ];

    private const RECIPE_COUNT = 50;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $faker->seed(1234); // same seed => same data on every load

        $tags = [];
        foreach (self::TAG_NAMES as $name) {
            $tag = new Tag($name);
            $manager->persist($tag);
            $tags[] = $tag;
        }

        for ($i = 0; $i < self::RECIPE_COUNT; ++$i) {
            $recipe = new Recipe(
                ucfirst($faker->words(3, true)),
                $faker->paragraphs(2, true),
                $faker->numberBetween(5, 120),
                $faker->randomElement(Difficulty::cases()),
                \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-60 days', 'now')),
            );

            foreach ($faker->randomElements($tags, $faker->numberBetween(1, 3)) as $tag) {
                $recipe->addTag($tag);
            }

            $manager->persist($recipe);
        }

        $manager->flush(); // one flush: all INSERTs in a single transaction
    }
}
