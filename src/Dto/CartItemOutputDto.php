<?php

namespace App\Dto;

class CartItemOutputDto
{
    public int $id;
    public ProductOutputDto $product;
    public int $quantity;
    public string $unitPrice;
    public string $totalPrice;
}
