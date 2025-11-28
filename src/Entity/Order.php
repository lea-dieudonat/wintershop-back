<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Enum\OrderStatus;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
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
    private string $totalAmount;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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
}
