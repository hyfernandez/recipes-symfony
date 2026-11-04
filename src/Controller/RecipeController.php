<?php

namespace App\Controller;

use App\Repository\RecipeRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class RecipeController extends AbstractController
{
    private const PER_PAGE = 9;

    #[Route('/', name: 'recipe_index')]
    public function index(
        RecipeRepository $recipes,
        TagRepository $tags,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] ?int $tag = null,
    ): Response {
        $currentTag = null;
        if (null !== $tag) {
            $currentTag = $tags->find($tag) ?? throw $this->createNotFoundException('Unknown tag.');
        }

        $paginator = $recipes->paginate(max(1, $page), self::PER_PAGE, $currentTag);
        $total = count($paginator);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $pages) {
            throw $this->createNotFoundException('Page not found.');
        }

        return $this->render('recipe/index.html.twig', [
            'recipes' => $paginator,
            'tags' => $tags->findBy([], ['name' => 'ASC']),
            'currentTag' => $currentTag,
            'page' => max(1, $page),
            'pages' => $pages,
            'total' => $total,
        ]);
    }
}
