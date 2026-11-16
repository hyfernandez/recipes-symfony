<?php

namespace App\Tests\Controller\Api;

use App\Tests\DatabaseWebTestCase;

final class TagApiTest extends DatabaseWebTestCase
{
    public function testListReturnsJsonWithOnlyIdAndName(): void
    {
        $this->createTag('Vegan');

        $this->client->request('GET', '/api/tags');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('json', $this->client->getResponse()->headers->get('content-type'));

        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
        self::assertSame(['id', 'name'], array_keys($data[0]));
        self::assertSame('Vegan', $data[0]['name']);
    }

    public function testListIsSortedByName(): void
    {
        // Created out of alphabetical order, so an unsorted query would fail.
        // Known limitation: tag.name has a unique index and the query only selects id and name,
        // so MySQL scans that index and returns alphabetical order even without an ORDER BY.
        // This test checks the observable contract (sorted output), not that the ORDER BY exists;
        // the explicit sort in the controller is what keeps the contract if the plan changes.
        foreach (['Zeta', 'Alfa', 'Medio'] as $name) {
            $this->createTag($name);
        }

        $this->client->request('GET', '/api/tags');

        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['Alfa', 'Medio', 'Zeta'], array_column($data, 'name'));
    }

    public function testListIsPublic(): void
    {
        // No login: reading tags must not require authentication.
        $this->client->request('GET', '/api/tags');

        self::assertResponseStatusCodeSame(200);
    }
}
