<?php

namespace App\Dto;

use DateTimeInterface;

class CartOutputDto
{
    public int $id;
    /** @var CartItemOutputDto[] */
    public array $items = [];
    public int $totalItems;
    public string $subtotal;
    public DateTimeInterface $createdAt;
    public ?DateTimeInterface $updatedAt = null;
}
