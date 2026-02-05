<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\Collection;
use App\Exception\OrderNotRefundableException;
use App\Exception\OrderNotCancellableException;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
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

    // Stripe payment tracking
    #[ORM\Column(length: 255, nullable: true, options: ['comment' => 'ID de session Stripe Checkout'])]
    #[Groups(['order:detail'])]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 255, nullable: true, options: ['comment' => 'ID PaymentIntent Stripe'])]
    #[Groups(['order:detail'])]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Date de paiement effectif'])]
    #[Groups(['order:list', 'order:detail'])]
    private ?DateTimeImmutable $paidAt = null;

    // Shipping information
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['comment' => 'Frais de livraison TTC en EUR']
    )]
    #[Groups(['order:list', 'order:detail'])]
    private string $shippingCost = '0.00';

    #[ORM\Column(
        type: 'string',
        length: 50,
        enumType: ShippingMethod::class,
        options: ['comment' => 'Mode de livraison choisi']
    )]
    #[Groups(['order:detail'])]
    private ShippingMethod $shippingMethod = ShippingMethod::STANDARD;

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

    public function canRequestCancellation(): bool
    {
        try {
            $this->assertCanRequestCancellation();
            return true;
        } catch (OrderNotCancellableException) {
            return false;
        }
    }

    #[Groups(['order:list', 'order:detail'])]
    #[SerializedName('canRequestCancellation')]
    public function getCanRequestCancellation(): bool
    {
        return $this->canRequestCancellation();
    }

    public function assertCanRequestCancellation(): void
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
    public function canRequestRefund(): bool
    {
        try {
            $this->assertCanRequestRefund();
            return true;
        } catch (OrderNotRefundableException) {
            return false;
        }
    }

    #[Groups(['order:list', 'order:detail'])]
    #[SerializedName('canRequestRefund')]
    public function getCanRequestRefund(): bool
    {
        return $this->canRequestRefund();
    }

    public function assertCanRequestRefund(): void
    {
        if ($this->refundRequestedAt !== null) {
            throw OrderNotRefundableException::alreadyRequested();
        }

        if (!$this->status->canRequestRefund()) {
            throw OrderNotRefundableException::notDelivered();
        }

        $refundDeadline = $this->deliveredAt?->modify('+14 days');
        if ($refundDeadline === null || new DateTimeImmutable() > $refundDeadline) {
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

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getShippingCost(): string
    {
        return $this->shippingCost;
    }

    public function setShippingCost(string $shippingCost): static
    {
        $this->shippingCost = $shippingCost;
        return $this;
    }

    public function getShippingMethod(): ShippingMethod
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethod $shippingMethod): static
    {
        $this->shippingMethod = $shippingMethod;
        return $this;
    }
}
