<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shop')]
class ShopController extends AbstractController
{
    #[Route('/', name: 'app_shop_index')]
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
    
    #[Route('/category/{slug}', name: 'app_shop_category')]
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
    
    #[Route('/product/{id}', name: 'app_shop_product')]
    public function show(int $id, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);
        
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Produit non trouvé');
        }
        
        return $this->render('shop/show.html.twig', [
            'product' => $product,
        ]);
    }
}