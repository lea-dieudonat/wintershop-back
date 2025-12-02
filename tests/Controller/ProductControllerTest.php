<?php

namespace App\Tests\Controller;

use App\Entity\Product;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Factory\ProductFactory;
use App\Constant\Route;

final class ProductControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->manager = static::getContainer()->get('doctrine')->getManager();

        // Ensure there is an admin user with ROLE_ADMIN and log them in
        $existingUser = $this->manager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@test.com']);
        if ($existingUser) {
            // make sure the existing user has ROLE_ADMIN
            if (!in_array('ROLE_ADMIN', $existingUser->getRoles(), true)) {
                $existingUser->setRoles(array_unique(array_merge($existingUser->getRoles(), ['ROLE_ADMIN'])));
                $this->manager->persist($existingUser);
                $this->manager->flush();
            }
            $this->client->loginUser($existingUser);
        } else {
            $adminUser = UserFactory::createOne([
                'email' => 'admin@test.com',
                'roles' => ['ROLE_ADMIN'],
            ])->_real();
            $this->client->loginUser($adminUser);
        }
        $this->productRepository = $this->manager->getRepository(Product::class);

        // Clean up related entities to avoid foreign key constraint errors
        // 1. Remove cart items (they reference both cart and product)
        foreach ($this->manager->getRepository(CartItem::class)->findAll() as $cartItem) {
            $this->manager->remove($cartItem);
        }

        // 2. Remove carts
        foreach ($this->manager->getRepository(Cart::class)->findAll() as $cart) {
            $this->manager->remove($cart);
        }

        // 3. Remove products
        foreach ($this->productRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    private function generateUrl(string $route, array $parameters = []): string
    {
        return $this->client->getContainer()->get('router')->generate($route, $parameters);
    }

    public function testIndex(): void
    {
        // Create some test products
        ProductFactory::createOne(['name' => 'Test Product 1', 'price' => '10.99']);
        ProductFactory::createOne(['name' => 'Test Product 2', 'price' => '20.99']);

        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->generateUrl(Route::PRODUCT->value));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Product index');

        // Verify products are displayed
        self::assertSelectorTextContains('body', 'Test Product 1');
        self::assertSelectorTextContains('body', 'Test Product 2');
    }

    public function testNew(): void
    {
        // ProductFactory will handle category creation automatically
        $productData = ProductFactory::new()->withoutPersisting()->create();

        $this->client->request('GET', $this->generateUrl(Route::PRODUCT_NEW->value));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'product[name]' => $productData->getName(),
            'product[description]' => $productData->getDescription(),
            'product[price]' => $productData->getPrice(),
            'product[stock]' => $productData->getStock(),
            'product[imageUrl]' => $productData->getImageUrl(),
            'product[isActive]' => $productData->isActive(),
            'product[category]' => $productData->getCategory()->getId(),
        ]);

        self::assertResponseRedirects($this->generateUrl(Route::PRODUCT->value));

        self::assertSame(1, $this->productRepository->count([]));
        self::assertSame($productData->getName(), $this->productRepository->findAll()[0]->getName());
    }

    public function testShow(): void
    {
        $fixture = ProductFactory::createOne([
            'name' => 'Sample Product',
            'price' => '19.99',
            'stock' => 10,
        ]);

        $this->client->request('GET', $this->generateUrl(Route::PRODUCT_SHOW->value, ['id' => $fixture->getId()]));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Product');

        // Use assertions to check that the properties are properly displayed.
        self::assertSelectorTextContains('body', 'Sample Product');
        self::assertSelectorTextContains('body', '19.99');
        self::assertSelectorTextContains('body', '10');
    }

    public function testEdit(): void
    {
        $fixture = ProductFactory::createOne([
            'name' => 'Original Product',
            'price' => '10.00',
            'stock' => 5,
        ]);
        $newCategory = CategoryFactory::createOne(['name' => 'New Category']);

        $originalUpdatedAt = $fixture->getUpdatedAt();

        // Small delay to ensure updatedAt changes
        sleep(1);

        $this->client->request('GET', $this->generateUrl(Route::PRODUCT_EDIT->value, ['id' => $fixture->getId()]));

        $this->client->submitForm('Update', [
            'product[name]' => 'Updated Product',
            'product[description]' => 'Updated description',
            'product[price]' => '49.99',
            'product[stock]' => '25',
            'product[imageUrl]' => 'https://example.com/updated.jpg',
            'product[isActive]' => true,
            'product[category]' => $newCategory->getId(),
        ]);

        self::assertResponseRedirects($this->generateUrl(Route::PRODUCT->value));

        $updated = $this->productRepository->findAll();

        self::assertSame('Updated Product', $updated[0]->getName());
        self::assertSame('Updated description', $updated[0]->getDescription());
        self::assertSame('49.99', $updated[0]->getPrice());
        self::assertSame(25, $updated[0]->getStock());
        self::assertSame('https://example.com/updated.jpg', $updated[0]->getImageUrl());
        self::assertTrue($updated[0]->isActive());
        self::assertSame($newCategory->getId(), $updated[0]->getCategory()->getId());

        // Verify updatedAt was set
        self::assertNotNull($updated[0]->getUpdatedAt());
        self::assertNotEquals($originalUpdatedAt, $updated[0]->getUpdatedAt());
    }

    public function testRemove(): void
    {
        $fixture = ProductFactory::createOne([
            'name' => 'Product to Delete',
            'price' => '15.00',
            'stock' => 5,
        ]);

        $this->client->request('GET', $this->generateUrl(Route::PRODUCT_SHOW->value, ['id' => $fixture->getId()]));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects($this->generateUrl(Route::PRODUCT->value));
        self::assertSame(0, $this->productRepository->count([]));
    }
}
