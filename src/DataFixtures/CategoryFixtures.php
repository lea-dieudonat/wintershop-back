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
                'name' => 'Vêtements',
                'description' => 'Vestes, pantalons et sous-vêtements techniques pour affronter le froid avec style.',
            ],
            [
                'name' => 'Chaussures',
                'description' => 'Chaussures de ski et snowboard adaptées à votre pratique et morphologie.',
            ],
            [
                'name' => 'Accessoires',
                'description' => 'Casques, masques, gants, bonnets et tout l\'équipement nécessaire pour votre sécurité et confort.',
            ],
            [
                'name' => 'Protection',
                'description' => 'Protections dorsales, genouillères et équipements de sécurité pour pratiquer en toute sérénité.',
            ],
        ];

        foreach ($categories as $index => $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description']);
            $slug = strtolower($this->slugger->slug($categoryData['name']));
            $category->setSlug($slug);

            $manager->persist($category);

            // Optionnel : ajouter une référence pour réutiliser cette catégorie dans d'autres fixtures
            $this->addReference('category_' . $index, $category);
        }
        $manager->flush();
    }
}