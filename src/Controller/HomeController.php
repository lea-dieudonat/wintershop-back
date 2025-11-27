<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        // Récupérer toutes les catégories
        $categories = $categoryRepository->findAll();
        
        // Récupérer les produits en vedette (actifs, triés par date)
        $featuredProducts = $productRepository->findBy(
            ['isActive' => true],
            ['createdAt' => 'DESC'],
            8 // Limiter à 8 produits
        );
        
        return $this->render('home/index.html.twig', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }
}