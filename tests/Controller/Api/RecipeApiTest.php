<?php

namespace App\Tests\Controller\Api;

use App\Entity\Recipe;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RecipeApiTest extends DatabaseWebTestCase
{
    private const VALID = [
        'title' => 'Tortilla',
        'description' => 'Eggs and potatoes',
        'prepTimeMinutes' => 30,
        'difficulty' => 'easy',
        'tags' => [],
    ];

    // ---- Reads (public) ----

    public function testListReturnsPaginatedJson(): void
    {
        $this->createRecipe('Gazpacho');

        $data = $this->request('GET', '/api/recipes');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['pages']);
        self::assertSame('Gazpacho', $data['items'][0]['title']);
    }

    public function testRecipesDoNotExposeInternalFields(): void
    {
        $author = $this->createUser('author@example.com');
        $recipe = $this->createRecipe('Gazpacho', $author);

        $data = $this->request('GET', '/api/recipes/'.$recipe->getId());

        self::assertArrayNotHasKey('author', $data);
        self::assertArrayNotHasKey('imageFilename', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testTagsAreAJsonList(): void
    {
        $recipe = $this->createRecipe('Gazpacho', tag: $this->createTag('Vegan'));

        $data = $this->request('GET', '/api/recipes/'.$recipe->getId());

        self::assertTrue(array_is_list($data['tags']));
        self::assertSame('Vegan', $data['tags'][0]['name']);
    }

    public function testShowUnknownRecipeIsJson404(): void
    {
        $data = $this->request('GET', '/api/recipes/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('json', $this->client->getResponse()->headers->get('content-type'));
        self::assertNotEmpty($data);   // a JSON error body, not an HTML page
    }

    public function testRecentReturnsAtMostFive(): void
    {
        for ($i = 1; $i <= 7; ++$i) {
            $this->createRecipe('Recipe '.$i);
        }

        $data = $this->request('GET', '/api/recipes/recent');

        self::assertCount(5, $data);
        self::assertSame('Recipe 7', $data[0]['title']);
    }

    public function testListFiltersByTag(): void
    {
        $vegan = $this->createTag('Vegan');
        $this->createRecipe('Vegan stew', tag: $vegan);
        $this->createRecipe('Beef stew');

        $data = $this->request('GET', '/api/recipes?tag='.$vegan->getId());

        self::assertSame(1, $data['total']);
        self::assertSame('Vegan stew', $data['items'][0]['title']);
    }

    // ---- Login ----

    public function testLoginWithValidCredentials(): void
    {
        $this->createUser('admin@example.com');

        // The stateless CSRF check compares the Origin header with the host
        $data = $this->request('POST', '/api/login', ['email' => 'admin@example.com', 'password' => 'secret1234'], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseIsSuccessful();
        self::assertSame('admin@example.com', $data['email']);
        self::assertContains('ROLE_ADMIN', $data['roles']);
    }

    public function testLoginWithWrongPasswordIs401(): void
    {
        $this->createUser('admin@example.com');

        $this->request('POST', '/api/login', ['email' => 'admin@example.com', 'password' => 'wrong'], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseStatusCodeSame(401);
    }

    // ---- Writes ----

    public function testAnonymousCannotWrite(): void
    {
        $this->request('POST', '/api/recipes', self::VALID);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateReturns201WithLocationAndSetsTheAuthor(): void
    {
        $admin = $this->createUser('admin@example.com');
        $tag = $this->createTag('Quick');
        $this->client->loginUser($admin);

        $data = $this->request('POST', '/api/recipes', ['tags' => [$tag->getId()]] + self::VALID);

        self::assertResponseStatusCodeSame(201);
        self::assertStringEndsWith('/api/recipes/'.$data['id'], $this->client->getResponse()->headers->get('location'));
        self::assertSame('Quick', $data['tags'][0]['name']);

        $this->em->clear();
        $saved = $this->em->getRepository(Recipe::class)->find($data['id']);
        self::assertSame($admin->getId(), $saved->getAuthor()->getId());
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadIs422WithViolations(array $override, string $field): void
    {
        $this->client->loginUser($this->createUser('admin@example.com'));

        $data = $this->request('POST', '/api/recipes', $override + self::VALID);

        self::assertResponseStatusCodeSame(422);
        self::assertContains($field, array_column($data['violations'], 'propertyPath'));
    }

    public static function invalidPayloads(): iterable
    {
        yield 'empty title' => [['title' => ''], 'title'];
        yield 'time over 24h' => [['prepTimeMinutes' => 5000], 'prepTimeMinutes'];
        yield 'unknown difficulty' => [['difficulty' => 'extreme'], 'difficulty'];
        yield 'tag of the wrong type' => [['tags' => ['abc']], 'tags[0]'];
    }

    public function testUnknownTagIdIs422(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com'));

        $this->request('POST', '/api/recipes', ['tags' => [999999]] + self::VALID);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAuthorCanUpdateTheirRecipe(): void
    {
        $author = $this->createUser('author@example.com');
        $recipe = $this->createRecipe('Old title', $author);
        $this->client->loginUser($author);

        $data = $this->request('PUT', '/api/recipes/'.$recipe->getId(), ['title' => 'New title'] + self::VALID);

        self::assertResponseIsSuccessful();
        self::assertSame('New title', $data['title']);
    }

    public function testAdminCannotUpdateSomeoneElsesRecipe(): void
    {
        $recipe = $this->createRecipe('Not yours', $this->createUser('author@example.com'));
        $this->client->loginUser($this->createUser('other@example.com'));

        $this->request('PUT', '/api/recipes/'.$recipe->getId(), self::VALID);

        self::assertResponseStatusCodeSame(403);   // RecipeVoter: only the author
    }

    public function testAuthorCanDeleteTheirRecipe(): void
    {
        $author = $this->createUser('author@example.com');
        $id = $this->createRecipe('To delete', $author)->getId();
        $this->client->loginUser($author);

        $this->client->request('DELETE', '/api/recipes/'.$id);

        self::assertResponseStatusCodeSame(204);
        $this->em->clear();
        self::assertNull($this->em->getRepository(Recipe::class)->find($id));
    }

    public function testAdminCannotDeleteSomeoneElsesRecipe(): void
    {
        $recipe = $this->createRecipe('Not yours', $this->createUser('author@example.com'));
        $this->client->loginUser($this->createUser('other@example.com'));

        $this->client->request('DELETE', '/api/recipes/'.$recipe->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testUserWithoutAdminRoleCannotWrite(): void
    {
        $this->client->loginUser($this->createUser('plain@example.com', ['ROLE_USER']));

        $this->request('POST', '/api/recipes', self::VALID);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Sends a JSON request and returns the decoded JSON response.
     */
    private function request(string $method, string $uri, ?array $body = null, array $server = []): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'] + $server, content: null === $body ? null : json_encode($body));

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }
}
