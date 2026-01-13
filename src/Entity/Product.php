<?php

namespace App\Entity;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use InvalidArgumentException;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductRepository;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\Collection;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use Doctrine\Common\Collections\ArrayCollection;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    paginationEnabled: true,
    paginationItemsPerPage: 10,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'description' => 'partial',
    'category' => 'exact',
    'category.slug' => 'exact',
])]
#[ApiFilter(RangeFilter::class, properties: ['price', 'stock'])]
#[ApiFilter(OrderFilter::class, properties: [
    'price',
    'name',
    'stock',
    'id'
], arguments: ['orderParameterName' => 'order'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['product:read', 'product:write', 'order:detail'])]
    private string $name;

    /**
     * Identifiant URL-friendly du produit.
     * Format: {nom-produit}-{id} pour garantir l'unicité.
     * Exemple: "t-shirt-coton-bio-123"
     */
    #[ORM\Column(length: 255, unique: true, options: ['comment' => 'Slug unique du produit'])]
    #[Groups(['product:read', 'product:write'])]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $description = null;

    /**
     * Prix unitaire du produit en euros.
     * Stocké en decimal pour éviter les erreurs d'arrondi.
     * Utilisez toujours le type string en PHP pour les calculs financiers.
     * 
     * @var string Prix en format decimal (ex: "19.99")
     */
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['comment' => 'Prix en EUR (decimal 10,2)']
    )]
    #[Assert\NotBlank(message: 'Le prix est obligatoire')]
    #[Assert\Positive(message: 'Le prix doit être positif')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Le prix doit être au format décimal avec maximum deux chiffres après la virgule'
    )]
    #[Groups(['product:read', 'product:write'])]
    private ?string $price = null;

    /**
     * Quantité disponible en stock.
     * Doit être mis à jour à chaque commande validée.
     * Une valeur de 0 signifie "rupture de stock".
     */
    #[ORM\Column(options: ['default' => 0, 'comment' => 'Quantité en stock'])]
    #[Assert\NotBlank(message: 'Le stock est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif')]
    #[Assert\Type(
        type: 'integer',
        message: 'Le stock doit être un nombre entier'
    )]
    #[Groups(['product:read', 'product:write'])]
    private int $stock = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:read', 'product:write', 'order:detail'])]
    private ?string $imageUrl = null;

    /**
     * Indique si le produit est visible sur le site.
     * Permet de désactiver un produit sans le supprimer.
     * Les produits inactifs n'apparaissent pas dans le catalogue.
     */
    #[ORM\Column(options: ['default' => true, 'comment' => 'Produit visible sur le site'])]
    #[Groups(['product:read', 'product:write'])]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product:read', 'product:write'])]
    private ?Category $category = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'product')]
    private Collection $orderItems;

    /**
     * @var Collection<int, CartItem>
     */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'product')]
    private Collection $cartItems;

    /**
     * @var Collection<int, ProductTranslation>
     */
    #[ORM\OneToMany(targetEntity: ProductTranslation::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['product:read'])]
    private Collection $translations;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->cartItems = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Génère automatiquement le slug avant la première sauvegarde.
     * Format: {nom-produit}-{timestamp} pour garantir l'unicité.
     */
    #[ORM\PrePersist]
    public function generateSlug(): void
    {
        if (empty($this->slug)) {
            $slugger = new AsciiSlugger();
            $baseSlug = $slugger->slug(strtolower($this->name));
            // Ajoute un timestamp pour garantir l'unicité avant d'avoir l'ID
            $this->slug = $baseSlug . '-' . time() . '-' . bin2hex(random_bytes(4));
        }
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setProduct($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            // set the owning side to null (unless already changed)
            if ($orderItem->getProduct() === $this) {
                $orderItem->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    public function addCartItem(CartItem $cartItem): static
    {
        if (!$this->cartItems->contains($cartItem)) {
            $this->cartItems->add($cartItem);
            $cartItem->setProduct($this);
        }

        return $this;
    }

    public function removeCartItem(CartItem $cartItem): static
    {
        if ($this->cartItems->removeElement($cartItem)) {
            // set the owning side to null (unless already changed)
            if ($cartItem->getProduct() === $this) {
                $cartItem->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * Remet en stock une quantité de produits (lors d'une annulation de commande).
     * @param int $quantity
     * @throws InvalidArgumentException
     */
    public function restoreStock(int $quantity): static
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La quantité à restaurer doit être positive.');
        }
        $this->stock += $quantity;
        return $this;
    }

    /**
     * @return Collection<int, ProductTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ProductTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setProduct($this);
        }

        return $this;
    }

    public function removeTranslation(ProductTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getProduct() === $this) {
                $translation->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * Get translation by locale, or return default (French) if not found.
     */
    public function getTranslationByLocale(string $locale): ?ProductTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        // Fallback to French if locale not found
        if ($locale !== 'fr') {
            return $this->getTranslationByLocale('fr');
        }

        return null;
    }
}
