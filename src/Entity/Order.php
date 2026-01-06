<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\OrderStatus;
use App\State\OrderProvider;
use ApiPlatform\Metadata\Get;
use App\State\OrderProcessor;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use App\Dto\Order\OrderOutputDto;
use App\Repository\OrderRepository;
use ApiPlatform\Metadata\ApiResource;
use App\Dto\Order\OrderCancelInputDto;
use ApiPlatform\Metadata\GetCollection;
use App\Dto\Order\OrderDetailOutputDto;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/orders',
            security: "is_granted('ROLE_USER')",
            output: OrderOutputDto::class,
            provider: OrderProvider::class
        ),
        new Get(
            uriTemplate: '/orders/{id}',
            security: "is_granted('ROLE_USER')",
            output: OrderDetailOutputDto::class,
            provider: OrderProvider::class
        ),
        new Patch(
            uriTemplate: '/orders/{id}/cancel',
            security: "is_granted('ROLE_USER')",
            input: OrderCancelInputDto::class,
            output: OrderDetailOutputDto::class,
            processor: OrderProcessor::class,
            inputFormats: ['json' => ['application/json']],
        )
    ],
)]
#[ORM\Table(name: '`order`')]
#[UniqueEntity('email')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Numéro unique de commande généré automatiquement.
     * Format: ORD-YYYYMMDD-XXXXX (ex: ORD-20241125-00042).
     * Affiché au client et utilisé pour le support.
     */
    #[ORM\Column(length: 50, unique: true, options: ['comment' => 'Numéro unique de commande'])]
    private ?string $orderNumber = null;

    /**
     * État de la commande dans le workflow.
     * Suit un cycle défini : pending → paid → shipped → delivered.
     * Peut être cancelled à tout moment avant expédition.
     */
    #[ORM\Column(
        type: 'string',
        length: 50,
        enumType: OrderStatus::class,
        options: ['comment' => 'Statut de la commande']
    )]
    private OrderStatus $status = OrderStatus::PENDING;

    /**
     * Montant total de la commande TTC.
     * Calculé automatiquement : somme des OrderItem.totalPrice.
     * Figé au moment de la validation (historisation).
     * 
     * @var string Montant en decimal (ex: "149.99")
     */
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['comment' => 'Montant total TTC en EUR']
    )]
    private string $totalAmount = '0.00';

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Address $shippingAddress = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Address $billingAddress = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(
        targetEntity: OrderItem::class,
        mappedBy: 'parentOrder',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $items;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deliveredAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function markAsPaid(): self
    {
        return $this->setStatus(OrderStatus::PAID);
    }

    public function markAsShipped(): self
    {
        return $this->setStatus(OrderStatus::SHIPPED);
    }

    public function markAsDelivered(): self
    {
        return $this->setStatus(OrderStatus::DELIVERED);
    }

    public function cancel(): self
    {
        if (!$this->status->isCancellable()) {
            throw new \RuntimeException('Cette commande ne peut plus être annulée');
        }
        return $this->setStatus(OrderStatus::CANCELLED);
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?Address $shippingAddress): static
    {
        $this->shippingAddress = $shippingAddress;

        return $this;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?Address $billingAddress): static
    {
        $this->billingAddress = $billingAddress;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setParentOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getParentOrder() === $this) {
                $item->setParentOrder(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function generateOrderNumber(): void
    {
        if ($this->orderNumber === null) {
            // Format: ORD-20241128-XXXXX
            $this->orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        }
    }

    public function isCancellable(): bool
    {
        $statusIsCancellable = $this->status->isCancellable();

        if (!$statusIsCancellable) {
            return false;
        }

        // ex: annulation possible dans les 24h
        $now = new DateTimeImmutable();
        $cancellationDeadline = $this->createdAt->modify('+1 day');

        return $now <= $cancellationDeadline;
    }

    public function canRequestRefund(): bool
    {
        if (!$this->status->canRequestRefund()) {
            return false;
        }

        // Si livrée, vérifier si dans le délai de rétractation (14 jours)
        if ($this->status === OrderStatus::DELIVERED) {
            $now = new DateTimeImmutable();
            $refundDeadline = $this->deliveredAt?->modify('+14 days');

            return $now <= $refundDeadline;
        }

        return true;
    }

    public function getDeliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?DateTimeImmutable $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }
}
