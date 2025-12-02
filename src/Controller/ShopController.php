<?php

namespace App\Controller;

use App\Constant\Route;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;

#[RouteAttribute('/shop')]
class ShopController extends AbstractController
{
    #[RouteAttribute('/', name: Route::SHOP->value)]
    public function index(ProductRepository $productRepository): Response
    {
        // Tous les produits actifs
        $products = $productRepository->findBy(
            ['isActive' => true],
            ['name' => 'ASC']
        );

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'currentCategory' => null,
        ]);
    }

    #[RouteAttribute('/category/{slug}', name: Route::SHOP_CATEGORY->value)]
    public function category(
        string $slug,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        // Trouver la catégorie par son slug
        $category = $categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        // Produits actifs de cette catégorie
        $products = $productRepository->findBy(
            ['category' => $category, 'isActive' => true],
            ['name' => 'ASC']
        );

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'currentCategory' => $category,
        ]);
    }

    #[RouteAttribute('/product/{slug}', name: Route::SHOP_PRODUCT->value)]
    public function show(string $slug, ProductRepository $productRepository): Response
    {
        $product = $productRepository->findOneBy(['slug' => $slug]);

        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Produit non trouvé');
        }
        
        return $this->render('shop/show.html.twig', [
            'product' => $product,
        ]);
    }
}