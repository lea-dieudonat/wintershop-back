<?php

namespace App\Tests\Factory;

use App\Entity\Category;

class CategoryFactory
{
    public static function create(
        string $name = 'Test Category',
        ?string $description = null
    ): Category {
        $category = new Category();
        $category->setName($name);
        $category->setDescription($description ?? 'Test description');

        return $category;
    }

    public static function createWithCustomData(array $data): Category
    {
        return self::create(
            name: $data['name'] ?? 'Test Category',
            description: $data['description'] ?? null
        );
    }
}
