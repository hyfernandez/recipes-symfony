<?php

namespace App\EventListener;

use App\Entity\Recipe;
use App\Service\ImageUploader;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Deletes the photo file when a recipe is removed, whoever removes it (controller, console, fixtures...).
 *
 * The file is not deleted in postRemove: that event fires while the transaction is still open,
 * so a later failure would roll back the DELETE and leave a recipe pointing to a file that no longer
 * exists. Filenames are collected in postRemove and deleted in postFlush, once the commit succeeded.
 *
 * It is a listener, not an entity listener (#[AsEntityListener]), because those only support
 * lifecycle events of one entity and there is no postFlush for them.
 */
#[AsDoctrineListener(event: Events::preFlush)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class RecipeImageCleanupListener
{
    /** @var list<string> */
    private array $pendingFiles = [];

    public function __construct(private readonly ImageUploader $uploader)
    {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        // A previous flush that threw never reached postFlush: forget its leftovers,
        // otherwise a long-lived process would delete files of recipes that still exist.
        $this->pendingFiles = [];
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Recipe && null !== $entity->getImageFilename()) {
            $this->pendingFiles[] = $entity->getImageFilename();
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        foreach ($this->pendingFiles as $filename) {
            $this->uploader->delete($filename);
        }

        $this->pendingFiles = [];
    }
}
