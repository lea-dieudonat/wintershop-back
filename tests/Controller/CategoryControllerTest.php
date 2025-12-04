<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Constant\Route;

final class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->manager = static::getContainer()->get('doctrine')->getManager();

        // Ensure there is an admin user with ROLE_ADMIN and log them in
        $existingUser = $this->manager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@test.com']);
        if ($existingUser) {
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

        $this->categoryRepository = $this->manager->getRepository(Category::class);

        // Cleanup handled by DamaDoctrineTestBundle (transactional tests); manual purges removed.
    }

    private function generateUrl(string $route, array $parameters = []): string
    {
        return $this->client->getContainer()->get('router')->generate($route, $parameters);
    }

    public static function categoryNameProvider(): array
    {
        return [
            'simple name' => ['Testing', 'testing'],
            'name with spaces' => ['My Category', 'my-category'],
            'name with special chars' => ['Vêtements & Accessoires', 'vetements-accessoires'],
            'name with numbers' => ['Category 123', 'category-123'],
            'name with hyphens' => ['Pre-owned Items', 'pre-owned-items'],
        ];
    }

    public function testIndex(): void
    {
        CategoryFactory::createOne(['name' => 'Test Category 1']);
        CategoryFactory::createOne(['name' => 'Test Category 2']);

        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->generateUrl(Route::CATEGORY->value));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Category index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
        self::assertSelectorTextContains('body', 'Test Category 1');
        self::assertSelectorTextContains('body', 'Test Category 2');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('categoryNameProvider')]
    public function testNew(string $name, string $expectedSlug): void
    {
        $this->client->request('GET', $this->generateUrl(Route::CATEGORY_NEW->value));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'category[name]' => $name,
            'category[description]' => 'Testing description',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSame(1, $this->categoryRepository->count([]));

        $category = $this->categoryRepository->findAll()[0];
        self::assertSame($name, $category->getName());
        self::assertSame($expectedSlug, $category->getSlug());
    }

    public function testShow(): void
    {
        $fixture = CategoryFactory::createOne([
            'name' => 'My Title',
            'description' => 'My Title',
        ]);

        $this->client->request('GET', $this->generateUrl(Route::CATEGORY_SHOW->value, ['id' => $fixture->getId()]));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Category');
        self::assertSelectorTextContains('body', 'My Title');
    }

    public function testEdit(): void
    {
        $fixture = CategoryFactory::createOne([
            'name' => 'Old Title',
            'description' => 'Old Title',
        ]);

        $originalUpdatedAt = $fixture->getUpdatedAt();

        // Small delay to ensure updatedAt changes
        sleep(1);

        $this->client->request('GET', $this->generateUrl(Route::CATEGORY_EDIT->value, ['id' => $fixture->getId()]));

        $this->client->submitForm('Update', [
            'category[name]' => 'Something New',
            'category[description]' => 'Something New',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $updated = $this->categoryRepository->findAll();

        self::assertSame('Something New', $updated[0]->getName());
        self::assertSame('something-new', $updated[0]->getSlug());
        self::assertSame('Something New', $updated[0]->getDescription());

        // Verify updatedAt was set
        self::assertNotNull($updated[0]->getUpdatedAt());
        self::assertNotEquals($originalUpdatedAt, $updated[0]->getUpdatedAt());
    }

    public function testRemove(): void
    {
        $fixture = CategoryFactory::createOne([
            'name' => 'Value',
            'description' => 'Value',
        ]);

        $this->client->request('GET', $this->generateUrl(Route::CATEGORY_SHOW->value, ['id' => $fixture->getId()]));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSame(0, $this->categoryRepository->count([]));
    }
}
