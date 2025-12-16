<?php

namespace App\Dto;

class ProductOutputDto
{
    public int $id;
    public string $name;
    public string $price;
    public ?string $image = null;
    public int $stock;
}
