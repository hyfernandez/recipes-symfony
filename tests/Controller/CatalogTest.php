<?php

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;

final class CatalogTest extends DatabaseWebTestCase
{
    public function testHomeListsRecipes(): void
    {
        $this->createRecipe('Gazpacho');

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Gazpacho');
    }

    public function testFirstPageShowsNineRecipes(): void
    {
        for ($i = 1; $i <= 11; ++$i) {
            $this->createRecipe('Recipe '.$i);
        }

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Recipe 11', $crawler->html());   // newest first
        self::assertStringNotContainsString('Recipe 1<', $crawler->html()); // pushed to page 2
    }

    public function testFilterByTagShowsOnlyThatTag(): void
    {
        $vegan = $this->createTag('Vegan');
        $this->createRecipe('Vegan stew', tag: $vegan);
        $this->createRecipe('Beef stew');

        $this->client->request('GET', '/?tag='.$vegan->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vegan stew');
        self::assertSelectorTextNotContains('body', 'Beef stew');
    }

    public function testUnknownTagIs404(): void
    {
        $this->client->request('GET', '/?tag=999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testPageBeyondTheLastIs404(): void
    {
        $this->createRecipe('Only one');

        $this->client->request('GET', '/?page=5');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminAreaRequiresLogin(): void
    {
        $this->client->request('GET', '/admin/recipes');

        self::assertResponseRedirects();   // to the login form
    }
}
