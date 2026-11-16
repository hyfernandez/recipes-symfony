<?php

namespace App\Controller\Api;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tags', defaults: ['_format' => 'json'])]
final class TagApiController extends AbstractController
{
    private const READ = ['groups' => ['recipe:read']];

    #[Route('', name: 'api_tag_list', methods: ['GET'])]
    public function list(TagRepository $tags): JsonResponse {
        $tagList = $tags->findBy([], ['name' => 'ASC']);
        return $this->json($tagList, context: self::READ);
    }
}
