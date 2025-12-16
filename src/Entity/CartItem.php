<?php

namespace App\Entity;

use App\Dto\CartOutputDto;
use App\Dto\CartItemInputDto;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\State\CartItemProcessor;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\CartItemRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/cart/items',
            output: CartOutputDto::class,
            security: "is_granted('ROLE_USER')",
        ),
        new Patch(
            uriTemplate: '/cart/items/{id}',
            output: CartOutputDto::class,
            security: "is_granted('ROLE_USER')",
            inputFormats: ['json' => ['application/json']],
        ),
        new Delete(
            uriTemplate: '/cart/items/{id}',
            output: CartOutputDto::class,
            security: "is_granted('ROLE_USER')",
        ),
    ],
    input: CartItemInputDto::class,
    processor: CartItemProcessor::class,
)]
#[ORM\HasLifecycleCallbacks]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Quantité du produit dans le panier.
     * Minimum: 1 (sinon l'item doit être supprimé).
     * Maximum: limité par le stock disponible.
     */
    #[ORM\Column(options: ['default' => 1, 'comment' => 'Quantité (min: 1)'])]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'La quantité doit être au moins de 1.')]
    private int $quantity = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'cartItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cart $cart = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $unitPrice;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    // méthode helper pour calculer le total d'une ligne
    public function getTotalPrice(): string
    {
        return bcmul($this->unitPrice, (string) $this->quantity, 2);
    }
}
