<?php

namespace App\Tests;

use App\Entity\Recipe;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Difficulty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for tests that need the database. Each test creates only the data it needs.
 * DAMA\DoctrineTestBundle wraps every test in a transaction and rolls it back at the end,
 * so tests do not see each other's data and the test database stays empty.
 */
abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email, array $roles = ['ROLE_ADMIN']): User
    {
        $user = (new User($email))->setRoles($roles)->setPassword(
            static::getContainer()->get('security.user_password_hasher')->hashPassword(new User($email), 'secret1234'),
        );
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createTag(string $name): Tag
    {
        $tag = new Tag($name);
        $this->em->persist($tag);
        $this->em->flush();

        return $tag;
    }

    protected function createRecipe(string $title, ?User $author = null, ?Tag $tag = null, ?\DateTimeImmutable $createdAt = null): Recipe
    {
        $recipe = new Recipe($title, 'A description', 20, Difficulty::Easy, $createdAt);
        $recipe->setAuthor($author);
        if (null !== $tag) {
            $recipe->addTag($tag);
        }
        $this->em->persist($recipe);
        $this->em->flush();

        return $recipe;
    }
}
