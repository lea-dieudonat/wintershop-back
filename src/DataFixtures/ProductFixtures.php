<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Load products from JSON file
        $jsonPath = __DIR__ . '/Data/products.json';
        $jsonContent = file_get_contents($jsonPath);
        $products = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse products.json: ' . json_last_error_msg());
        }

        // Map category slugs to image files
        $categoryImages = [
            'skis' => 'ski.jpg',
            'snowboards' => 'snowboard.avif',
            'boots' => 'boots.jpg',
            'clothing' => 'clothing.jpg',
            'accessories' => 'accessories.jpg',
        ];

        foreach ($products as $productData) {
            $product = new Product();
            // Set default name/description (French as fallback for old data)
            $product->setName($productData['name']['fr']);
            $product->setDescription($productData['description']['fr']);
            $product->setPrice($productData['price']);
            $product->setStock($productData['stock']);
            $product->setIsActive($productData['isActive']);

            // Set image URL based on category (filename only, front-end will handle the path)
            $categorySlug = $productData['category'];
            if (isset($categoryImages[$categorySlug])) {
                $product->setImageUrl($categoryImages[$categorySlug]);
            }

            // Randomly feature ~20% of active products
            if ($productData['isActive'] && rand(1, 100) <= 20) {
                $product->setIsFeatured(true);
            }

            // Set category reference
            $category = $this->getReference($productData['category'], Category::class);
            $product->setCategory($category);

            // Create translations for each language
            foreach ($productData['name'] as $locale => $name) {
                $translation = new ProductTranslation();
                $translation->setLocale($locale);
                $translation->setName($name);
                $translation->setDescription($productData['description'][$locale]);
                $product->addTranslation($translation);
            }

            $manager->persist($product);

            // Créer une référence pour pouvoir l'utiliser dans d'autres fixtures
            $reference = 'product_' . strtolower(str_replace(' ', '_', $productData['name']['en']));
            $this->addReference($reference, $product);
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }
}
