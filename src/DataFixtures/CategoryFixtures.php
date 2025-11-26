<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Category;
use Symfony\Component\String\Slugger\SluggerInterface;

class CategoryFixtures extends Fixture
{
    public function __construct(
        private readonly SluggerInterface $slugger
    ) {

    }

    public function load(ObjectManager $manager): void
    {
        $categories = [
            [
                'name' => 'Skis',
                'description' => 'Découvrez notre gamme complète de skis alpins, de randonnée et de fond pour tous les niveaux.',
            ],
            [
                'name' => 'Snowboards',
                'description' => 'Boards pour débutants et experts, freestyle, freeride et all-mountain.',
            ],
            [
                'name' => 'Clothing',
                'description' => 'Vestes, pantalons et sous-vêtements techniques pour affronter le froid avec style.',
            ],
            [
                'name' => 'Boots',
                'description' => 'Chaussures de ski et snowboard adaptées à votre pratique et morphologie.',
            ],
            [
                'name' => 'Accessories',
                'description' => 'Casques, masques, gants, bonnets et tout l\'équipement nécessaire pour votre sécurité et confort.',
            ],
            [
                'name' => 'Protection',
                'description' => 'Protections dorsales, genouillères et équipements de sécurité pour pratiquer en toute sérénité.',
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description']);

            // Manually generate slug using the slugger for fixtures
            // This ensures the slug is set before persisting
            $slug = $this->slugger->slug($categoryData['name'])->lower()->toString();
            $category->setSlug($slug);

            $manager->persist($category);

            // Add reference using the generated slug
            $this->addReference($slug, $category);
        }
        $manager->flush();
    }
}