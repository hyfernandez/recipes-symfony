<?php

namespace App\Controller\Admin;

use App\Entity\Recipe;
use App\Enum\Difficulty;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Service\ImageUploader;
use App\Service\InvalidImageException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// The whole /admin prefix requires ROLE_ADMIN (access_control in security.yaml).
#[Route('/admin/recipes')]
class RecipeAdminController extends AbstractController
{
    #[Route('', name: 'admin_recipe_index', methods: ['GET'])]
    public function index(RecipeRepository $recipes): Response
    {
        return $this->render('admin/recipe/index.html.twig', [
            'recipes' => $recipes->findAllWithTags(),
        ]);
    }

    #[Route('/new', name: 'admin_recipe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ImageUploader $uploader): Response
    {
        $recipe = new Recipe('', '', 30, Difficulty::Easy);
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->storeImage($form, $recipe, $uploader)) {
            $em->persist($recipe);   // new entity: Doctrine must start tracking it
            $em->flush();            // INSERT (and recipe_tag rows)

            $this->addFlash('success', sprintf('Recipe "%s" created.', $recipe->getTitle()));

            return $this->redirectToRoute('admin_recipe_index');
        }

        return $this->render('admin/recipe/form.html.twig', [
            'form' => $form,
            'title' => 'New recipe',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_recipe_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Recipe $recipe, Request $request, EntityManagerInterface $em, ImageUploader $uploader): Response
    {
        $oldImage = $recipe->getImageFilename();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->storeImage($form, $recipe, $uploader)) {
            $em->flush();            // no persist(): the entity is already managed, Doctrine detects the changes

            // Only after a successful flush: if the UPDATE fails, the old photo must stay.
            if (null !== $oldImage && $oldImage !== $recipe->getImageFilename()) {
                $uploader->delete($oldImage);
            }

            $this->addFlash('success', sprintf('Recipe "%s" updated.', $recipe->getTitle()));

            return $this->redirectToRoute('admin_recipe_index');
        }

        return $this->render('admin/recipe/form.html.twig', [
            'form' => $form,
            'title' => 'Edit recipe',
            'recipe' => $recipe,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_recipe_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Recipe $recipe, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-recipe-'.$recipe->getId(), (string) $request->request->get('_token'))) {
            $title = $recipe->getTitle();
            $em->remove($recipe);
            $em->flush();

            $this->addFlash('success', sprintf('Recipe "%s" deleted.', $title));
        } else {
            $this->addFlash('error', 'Invalid security token. The recipe was not deleted.');
        }

        return $this->redirectToRoute('admin_recipe_index');
    }

    /**
     * Moves the uploaded photo (if any) and stores its filename on the recipe.
     * Returns false, with the error attached to the field, when the file is rejected.
     */
    private function storeImage(FormInterface $form, Recipe $recipe, ImageUploader $uploader): bool
    {
        $file = $form->get('imageFile')->getData();
        if (null === $file) {
            return true;
        }

        try {
            $recipe->setImageFilename($uploader->upload($file));
        } catch (InvalidImageException $e) {
            $form->get('imageFile')->addError(new FormError($e->getMessage()));

            return false;
        }

        return true;
    }
}
