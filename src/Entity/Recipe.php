<?php

namespace App\Entity;

use App\Enum\Difficulty;
use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recipe:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['recipe:read'])]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Groups(['recipe:read'])]
    private string $description;

    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(1440, message: 'The preparation time cannot exceed 24 hours (1440 minutes).')]
    #[Groups(['recipe:read'])]
    private int $prepTimeMinutes;

    #[ORM\Column(enumType: Difficulty::class)]
    #[Groups(['recipe:read'])]
    private Difficulty $difficulty;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\Column]
    #[Groups(['recipe:read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Who owns the recipe; RecipeVoter allows editing and deleting only to this user.
     * Nullable + SET NULL so recipes created before this field existed (or whose
     * author is deleted) stay valid, but nobody can edit them.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author = null;

    /**
     * Owning side of the relation: Doctrine creates the recipe_tag join table from this mapping.
     *
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'recipes')]
    private Collection $tags;

    public function __construct(
        string $title,
        string $description,
        int $prepTimeMinutes,
        Difficulty $difficulty,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->prepTimeMinutes = $prepTimeMinutes;
        $this->difficulty = $difficulty;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrepTimeMinutes(): int
    {
        return $this->prepTimeMinutes;
    }

    public function setPrepTimeMinutes(int $prepTimeMinutes): static
    {
        $this->prepTimeMinutes = $prepTimeMinutes;

        return $this;
    }

    public function getDifficulty(): Difficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(Difficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    /**
     * Public URL of the photo for the API; the internal filename is not exposed.
     */
    #[Groups(['recipe:read'])]
    public function getImageUrl(): ?string
    {
        return null === $this->imageFilename ? null : '/uploads/'.$this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * The tags as a plain list for the API. A Doctrine collection keeps its keys after add/remove
     * (e.g. 1, 2 instead of 0, 1), and the serializer turns such an array into a JSON object.
     *
     * @return list<Tag>
     */
    #[Groups(['recipe:read'])]
    #[SerializedName('tags')]
    public function getTagList(): array
    {
        return array_values($this->tags->toArray());
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
