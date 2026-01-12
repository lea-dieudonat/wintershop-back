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
        $products = [
            // Skis
            [
                'name' => [
                    'fr' => 'Ski Alpin Pro X5',
                    'en' => 'Alpine Ski Pro X5',
                    'es' => 'Esquí Alpino Pro X5',
                ],
                'description' => [
                    'fr' => 'Ski haute performance pour skieur confirmé. Construction en bois et fibre de carbone.',
                    'en' => 'High-performance ski for advanced skiers. Wood and carbon fiber construction.',
                    'es' => 'Esquí de alto rendimiento para esquiadores avanzados. Construcción de madera y fibra de carbono.',
                ],
                'price' => '599.99',
                'stock' => 15,
                'category' => 'skis',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Ski Freestyle Freestyle Master',
                    'en' => 'Freestyle Ski Master',
                    'es' => 'Esquí Freestyle Master',
                ],
                'description' => [
                    'fr' => 'Parfait pour le park et les figures. Twin tip pour atterrir en switch.',
                    'en' => 'Perfect for park and tricks. Twin tip for switch landings.',
                    'es' => 'Perfecto para el parque y trucos. Doble espátula para aterrizar en switch.',
                ],
                'price' => '449.99',
                'stock' => 8,
                'category' => 'skis',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Ski Débutant EasyGlide',
                    'en' => 'Beginner Ski EasyGlide',
                    'es' => 'Esquí Principiante EasyGlide',
                ],
                'description' => [
                    'fr' => 'Ski souple et tolérant, idéal pour apprendre les bases du ski alpin.',
                    'en' => 'Soft and forgiving ski, ideal for learning alpine skiing basics.',
                    'es' => 'Esquí suave y tolerante, ideal para aprender los fundamentos del esquí alpino.',
                ],
                'price' => '299.99',
                'stock' => 25,
                'category' => 'skis',
                'isActive' => true,
            ],

            // Snowboards
            [
                'name' => [
                    'fr' => 'Snowboard All-Mountain Rider',
                    'en' => 'All-Mountain Snowboard Rider',
                    'es' => 'Tabla de Snowboard All-Mountain Rider',
                ],
                'description' => [
                    'fr' => 'Planche polyvalente pour tous types de terrains. Cambre hybride.',
                    'en' => 'Versatile board for all terrain types. Hybrid camber.',
                    'es' => 'Tabla versátil para todo tipo de terrenos. Camber híbrido.',
                ],
                'price' => '479.99',
                'stock' => 12,
                'category' => 'snowboards',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Snowboard Powder Cloud 9',
                    'en' => 'Powder Snowboard Cloud 9',
                    'es' => 'Tabla de Nieve Polvo Cloud 9',
                ],
                'description' => [
                    'fr' => 'Spécialement conçue pour la poudreuse. Large spatule avant pour flotter.',
                    'en' => 'Specially designed for powder. Wide front nose for floating.',
                    'es' => 'Especialmente diseñada para nieve polvo. Espátula delantera ancha para flotar.',
                ],
                'price' => '549.99',
                'stock' => 6,
                'category' => 'snowboards',
                'isActive' => true,
            ],

            // Chaussures
            [
                'name' => [
                    'fr' => 'Chaussures de Ski Performance Pro',
                    'en' => 'Performance Ski Boots Pro',
                    'es' => 'Botas de Esquí Performance Pro',
                ],
                'description' => [
                    'fr' => 'Chaussures rigides pour une transmission optimale. Flex 130.',
                    'en' => 'Stiff boots for optimal power transmission. Flex 130.',
                    'es' => 'Botas rígidas para transmisión óptima de potencia. Flex 130.',
                ],
                'price' => '399.99',
                'stock' => 20,
                'category' => 'boots',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Chaussures de Snowboard Comfort Plus',
                    'en' => 'Snowboard Boots Comfort Plus',
                    'es' => 'Botas de Snowboard Comfort Plus',
                ],
                'description' => [
                    'fr' => 'Confort maximum avec système BOA. Thermoformable.',
                    'en' => 'Maximum comfort with BOA system. Heat moldable.',
                    'es' => 'Máximo confort con sistema BOA. Termoformable.',
                ],
                'price' => '349.99',
                'stock' => 18,
                'category' => 'boots',
                'isActive' => true,
            ],

            // Vêtements
            [
                'name' => [
                    'fr' => 'Veste de Ski Imperméable ArcticShield',
                    'en' => 'Waterproof Ski Jacket ArcticShield',
                    'es' => 'Chaqueta de Esquí Impermeable ArcticShield',
                ],
                'description' => [
                    'fr' => 'Veste 3 couches, imperméabilité 20000mm. Respirante et isolante.',
                    'en' => '3-layer jacket, 20000mm waterproof. Breathable and insulated.',
                    'es' => 'Chaqueta de 3 capas, impermeabilidad 20000mm. Transpirable y aislante.',
                ],
                'price' => '279.99',
                'stock' => 30,
                'category' => 'clothing',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Pantalon de Ski TechWarm',
                    'en' => 'Ski Pants TechWarm',
                    'es' => 'Pantalón de Esquí TechWarm',
                ],
                'description' => [
                    'fr' => 'Pantalon chaud et imperméable. Guêtres pare-neige intégrées.',
                    'en' => 'Warm and waterproof pants. Integrated snow gaiters.',
                    'es' => 'Pantalón cálido e impermeable. Polainas integradas.',
                ],
                'price' => '189.99',
                'stock' => 35,
                'category' => 'clothing',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Sous-vêtement Thermique MerinoTech',
                    'en' => 'Thermal Base Layer MerinoTech',
                    'es' => 'Ropa Interior Térmica MerinoTech',
                ],
                'description' => [
                    'fr' => 'Ensemble haut et bas en laine mérinos. Régulation thermique naturelle.',
                    'en' => 'Top and bottom set in merino wool. Natural thermal regulation.',
                    'es' => 'Conjunto superior e inferior en lana merino. Regulación térmica natural.',
                ],
                'price' => '89.99',
                'stock' => 50,
                'category' => 'clothing',
                'isActive' => true,
            ],

            // Accessoires
            [
                'name' => [
                    'fr' => 'Masque de Ski UV Protection Max',
                    'en' => 'Ski Goggles UV Protection Max',
                    'es' => 'Gafas de Esquí Protección UV Max',
                ],
                'description' => [
                    'fr' => 'Double écran anti-buée. Protection UV 400. Écran interchangeable.',
                    'en' => 'Double anti-fog lens. UV 400 protection. Interchangeable lens.',
                    'es' => 'Doble lente antivaho. Protección UV 400. Lente intercambiable.',
                ],
                'price' => '129.99',
                'stock' => 40,
                'category' => 'accessories',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Casque de Ski SafetyFirst Pro',
                    'en' => 'Ski Helmet SafetyFirst Pro',
                    'es' => 'Casco de Esquí SafetyFirst Pro',
                ],
                'description' => [
                    'fr' => 'Casque certifié CE EN 1077. Système de ventilation réglable.',
                    'en' => 'CE EN 1077 certified helmet. Adjustable ventilation system.',
                    'es' => 'Casco certificado CE EN 1077. Sistema de ventilación ajustable.',
                ],
                'price' => '99.99',
                'stock' => 45,
                'category' => 'accessories',
                'isActive' => true,
            ],
            [
                'name' => [
                    'fr' => 'Gants de Ski Chauffants HeatTech',
                    'en' => 'Heated Ski Gloves HeatTech',
                    'es' => 'Guantes de Esquí Calefactables HeatTech',
                ],
                'description' => [
                    'fr' => 'Gants chauffants avec batterie rechargeable. 3 niveaux de température.',
                    'en' => 'Heated gloves with rechargeable battery. 3 temperature levels.',
                    'es' => 'Guantes calefactables con batería recargable. 3 niveles de temperatura.',
                ],
                'price' => '149.99',
                'stock' => 22,
                'category' => 'accessories',
                'isActive' => true,
            ],

            // Produit en rupture de stock
            [
                'name' => [
                    'fr' => 'Bâtons de Ski Carbone Elite',
                    'en' => 'Carbon Ski Poles Elite',
                    'es' => 'Bastones de Esquí Carbono Elite',
                ],
                'description' => [
                    'fr' => 'Bâtons ultra-légers en carbone 100%. Pour la compétition.',
                    'en' => 'Ultra-light 100% carbon poles. For competition.',
                    'es' => 'Bastones ultraligeros de carbono 100%. Para competición.',
                ],
                'price' => '79.99',
                'stock' => 0,
                'category' => 'accessories',
                'isActive' => true,
            ],

            // Produit inactif (ancien modèle)
            [
                'name' => [
                    'fr' => 'Ski Ancien Modèle Classic 2020',
                    'en' => 'Old Model Ski Classic 2020',
                    'es' => 'Modelo Antiguo Esquí Classic 2020',
                ],
                'description' => [
                    'fr' => 'Ancien modèle, plus disponible. Remplacé par Pro X5.',
                    'en' => 'Old model, no longer available. Replaced by Pro X5.',
                    'es' => 'Modelo antiguo, ya no disponible. Reemplazado por Pro X5.',
                ],
                'price' => '399.99',
                'stock' => 0,
                'category' => 'skis',
                'isActive' => false,
            ],
        ];

        foreach ($products as $productData) {
            $product = new Product();
            // Set default name/description (French as fallback for old data)
            $product->setName($productData['name']['fr']);
            $product->setDescription($productData['description']['fr']);
            $product->setPrice($productData['price']);
            $product->setStock($productData['stock']);
            $product->setIsActive($productData['isActive']);

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
