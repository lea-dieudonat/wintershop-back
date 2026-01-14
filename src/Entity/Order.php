<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\OrderStatus;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrderRepository;
use App\State\OrderRefundProcessor;
use ApiPlatform\Metadata\ApiResource;
use App\Dto\Order\OrderCancelInputDto;
use App\Dto\Order\OrderRefundInputDto;
use ApiPlatform\Metadata\GetCollection;
use App\Dto\Order\OrderDetailOutputDto;
use App\State\OrderCancellationProcessor;
use Doctrine\Common\Collections\Collection;
use App\Exception\OrderNotRefundableException;
use App\Exception\OrderNotCancellableException;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/orders',
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['order:list']]
        ),
        new Get(
            uriTemplate: '/orders/{id}',
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['order:detail']]
        ),
        new Patch(
            uriTemplate: '/orders/{id}/cancel',
            security: "is_granted('ROLE_USER')",
            input: OrderCancelInputDto::class,
            output: OrderDetailOutputDto::class,
            processor: OrderCancellationProcessor::class,
            inputFormats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/orders/{id}/refund',
            security: "is_granted('ROLE_USER')",
            input: OrderRefundInputDto::class,
            processor: OrderRefundProcessor::class,
            //inputFormats: ['json' => ['application/json']],
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
    #[Groups(['order:list', 'order:detail'])]
    private ?int $id = null;

    /**
     * Numéro unique de commande généré automatiquement.
     * Format: ORD-YYYYMMDD-XXXXX (ex: ORD-20241125-00042).
     * Affiché au client et utilisé pour le support.
     */
    #[ORM\Column(length: 50, unique: true, options: ['comment' => 'Numéro unique de commande'])]
    #[Groups(['order:list', 'order:detail'])]
    private ?string $reference = null;

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
    #[Groups(['order:list', 'order:detail'])]
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
    #[Groups(['order:list', 'order:detail'])]
    private string $totalAmount = '0.00';

    #[ORM\Column]
    #[Groups(['order:list', 'order:detail'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:detail'])]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:detail'])]
    private ?Address $shippingAddress = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:detail'])]
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
    #[Groups(['order:list', 'order:detail'])]
    private Collection $items;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:detail'])]
    private ?DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['order:detail'])]
    private ?string $refundReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['order:detail'])]
    private ?DateTimeImmutable $refundRequestedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function markAsPaid(): static
    {
        return $this->setStatus(OrderStatus::PAID);
    }

    public function markAsShipped(): static
    {
        return $this->setStatus(OrderStatus::SHIPPED);
    }

    public function markAsDelivered(): static
    {
        return $this->setStatus(OrderStatus::DELIVERED);
    }

    public function cancel(): static
    {
        $this->canRequestCancellation();
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

    #[Groups(['order:list', 'order:detail'])]
    public function getItemCount(): int
    {
        return $this->items->count();
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
    public function generateReference(): void
    {
        if ($this->reference === null) {
            // Format: ORD-20241128-XXXXX
            $this->reference = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
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

    public function canRequestCancellation(): void
    {
        if (!$this->status->isCancellable()) {
            throw OrderNotCancellableException::invalidStatus();
        }

        $now = new DateTimeImmutable();
        $cancellationDeadline = $this->createdAt->modify('+1 day');

        if ($now > $cancellationDeadline) {
            throw OrderNotCancellableException::deadlineExpired();
        }
    }

    public function canRequestRefund(): void
    {
        if ($this->refundRequestedAt !== null) {
            throw OrderNotRefundableException::alreadyRequested();
        }

        //if ($this->status !== OrderStatus::DELIVERED) {
        if (!$this->status->canRequestRefund()) {
            throw OrderNotRefundableException::notDelivered();
        }

        $now = new DateTimeImmutable();
        $refundDeadline = $this->deliveredAt?->modify('+14 days');

        if ($refundDeadline === null || $now > $refundDeadline) {
            throw OrderNotRefundableException::deadlineExpired();
        }
    }

    public function getDeliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getRefundReason(): ?string
    {
        return $this->refundReason;
    }

    public function setRefundReason(?string $refundReason): static
    {
        $this->refundReason = $refundReason;

        return $this;
    }

    public function getRefundRequestedAt(): ?DateTimeImmutable
    {
        return $this->refundRequestedAt;
    }

    public function setRefundRequestedAt(?DateTimeImmutable $refundRequestedAt): static
    {
        $this->refundRequestedAt = $refundRequestedAt;

        return $this;
    }
}
