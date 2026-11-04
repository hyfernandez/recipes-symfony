<?php

namespace App\Repository;

use App\Entity\Recipe;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * Newest recipes first, one page at a time, optionally filtered by tag.
     *
     * @return Paginator<Recipe>
     */
    public function paginate(int $page, int $perPage, ?Tag $tag = null): Paginator
    {
        // leftJoin + addSelect loads each recipe's tags in the same query (avoids N+1)
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.tags', 't')
            ->addSelect('t')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (null !== $tag) {
            $qb->andWhere(':tag MEMBER OF r.tags')->setParameter('tag', $tag);
        }

        // fetchJoinCollection: LIMIT must apply to recipes, not to the joined recipe+tag rows
        return new Paginator($qb, fetchJoinCollection: true);
    }
}
