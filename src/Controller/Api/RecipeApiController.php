<?php

namespace App\Controller\Api;

use App\Dto\RecipeInput;
use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use App\Repository\TagRepository;
use App\Security\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

// _format: json makes every response under /api, errors included (404, 401, 403, 422), JSON instead of HTML.
// Who may write is decided in security.yaml (access_control: login required) and by RecipeVoter (author only).
#[Route('/api/recipes', defaults: ['_format' => 'json'])]
final class RecipeApiController extends AbstractController
{
    private const PER_PAGE = 9;
    private const RECENT = 5;

    // Only the fields marked with #[Groups(['recipe:read'])] are exposed (never the author or the filename).
    private const READ = ['groups' => ['recipe:read']];

    #[Route('/recent', name: 'api_recipe_recent', methods: ['GET'])]
    public function recent(RecipeRepository $recipes): JsonResponse
    {
        // paginate() already sorts newest first and loads the tags in the same query (no N+1).
        return $this->json(iterator_to_array($recipes->paginate(1, self::RECENT), false), context: self::READ);
    }

    #[Route('', name: 'api_recipe_list', methods: ['GET'])]
    public function list(
        RecipeRepository $recipes,
        TagRepository $tags,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] ?int $tag = null,
    ): JsonResponse {
        $currentTag = null;
        if (null !== $tag) {
            $currentTag = $tags->find($tag) ?? throw $this->createNotFoundException('Unknown tag.');
        }

        $page = max(1, $page);
        $paginator = $recipes->paginate($page, self::PER_PAGE, $currentTag);
        $total = count($paginator);

        return $this->json([
            'items' => iterator_to_array($paginator, false),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
        ], context: self::READ);
    }

    #[Route('/{id}', name: 'api_recipe_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Recipe $recipe): JsonResponse
    {
        return $this->json($recipe, context: self::READ);
    }

    #[Route('', name: 'api_recipe_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] RecipeInput $input,
        TagRepository $tags,
        EntityManagerInterface $em,
    ): JsonResponse {
        $recipe = new Recipe($input->title, $input->description, $input->prepTimeMinutes, $input->difficulty());
        $recipe->setAuthor($this->getUser());   // the voter will let only this user edit/delete it
        $this->syncTags($recipe, $input, $tags);

        $em->persist($recipe);
        $em->flush();

        return $this->json($recipe, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_recipe_show', ['id' => $recipe->getId()]),
        ], self::READ);
    }

    // PUT replaces the whole resource: every field must be sent (a missing one fails validation).
    #[Route('/{id}', name: 'api_recipe_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted(RecipeVoter::EDIT, subject: 'recipe')]
    public function update(
        Recipe $recipe,
        #[MapRequestPayload] RecipeInput $input,
        TagRepository $tags,
        EntityManagerInterface $em,
    ): JsonResponse {
        $recipe
            ->setTitle($input->title)
            ->setDescription($input->description)
            ->setPrepTimeMinutes($input->prepTimeMinutes)
            ->setDifficulty($input->difficulty());
        $this->syncTags($recipe, $input, $tags);

        $em->flush();   // no persist(): the entity is already managed

        return $this->json($recipe, context: self::READ);
    }

    #[Route('/{id}', name: 'api_recipe_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(RecipeVoter::DELETE, subject: 'recipe')]
    public function delete(Recipe $recipe, EntityManagerInterface $em): Response
    {
        $em->remove($recipe);   // RecipeImageCleanupListener deletes the photo file after the flush
        $em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Replaces the recipe tags with the ones named in the payload. An unknown id is a client
     * error, reported as a 422 in the same format as the other validation errors.
     */
    private function syncTags(Recipe $recipe, RecipeInput $input, TagRepository $tags): void
    {
        $ids = array_values(array_unique($input->tags));
        $found = [] === $ids ? [] : $tags->findBy(['id' => $ids]);

        if (count($found) !== count($ids)) {
            $unknown = array_diff($ids, array_map(static fn ($t) => $t->getId(), $found));
            $violations = new ConstraintViolationList([new ConstraintViolation(
                sprintf('Unknown tag id(s): %s.', implode(', ', $unknown)),
                null, [], $input, 'tags', $input->tags,
            )]);

            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Unknown tag.', new ValidationFailedException($input, $violations));
        }

        foreach ($recipe->getTags()->toArray() as $current) {
            $recipe->removeTag($current);
        }
        foreach ($found as $tag) {
            $recipe->addTag($tag);
        }
    }
}
