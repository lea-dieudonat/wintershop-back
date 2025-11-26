<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Tests\Factory\CategoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $categoryRepository;
    private string $path = '/category/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->categoryRepository = $this->manager->getRepository(Category::class);

        foreach ($this->categoryRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
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
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Category index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('categoryNameProvider')]
    public function testNew(string $name, string $expectedSlug): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

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
        $fixture = CategoryFactory::create('My Title', 'My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Category');
        self::assertSelectorTextContains('body', 'My Title');
    }

    public function testEdit(): void
    {
        $fixture = CategoryFactory::create('Value', 'Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'category[name]' => 'Something New',
            'category[description]' => 'Something New',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        $fixture = $this->categoryRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('something-new', $fixture[0]->getSlug());
        self::assertSame('Something New', $fixture[0]->getDescription());
    }

    public function testRemove(): void
    {
        $fixture = CategoryFactory::create('Value', 'Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSame(0, $this->categoryRepository->count([]));
    }
}
