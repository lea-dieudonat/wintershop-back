<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Order;
use App\Constant\Route;
use App\Entity\Product;
use App\Entity\Category;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: Route::ADMIN->value)]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator
    ) {}

    public function index(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->generateUrl();

        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Winter Sports Shop - Admin')
            ->setFaviconPath('favicon.ico')
            ->setLocales(['en', 'fr']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Catalog');
        yield MenuItem::linkToCrud('Products', 'fa fa-box', Product::class);
        yield MenuItem::linkToCrud('Categories', 'fa fa-tags', Category::class);

        yield MenuItem::section('Orders');
        yield MenuItem::linkToCrud('Orders', 'fa fa-shopping-cart', Order::class);
        yield MenuItem::linkToCrud('Refund Requests', 'fa fa-exclamation-circle', Order::class)
            ->setController(RefundRequestCrudController::class);

        yield MenuItem::section('Users');
        yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);

        yield MenuItem::section('Settings');
        yield MenuItem::linkToRoute('Back to the shop', 'fa fa-store', Route::HOME->value);
        yield MenuItem::linkToLogout('Logout', 'fa fa-sign-out-alt');
    }
}
