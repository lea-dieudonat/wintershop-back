# Guide des tests

## 🎯 Philosophie de test

Ce projet utilise une approche pragmatique du testing :
- **Tests fonctionnels** pour les comportements utilisateur critiques
- **Tests unitaires** pour la logique métier complexe
- **Base de données de test** isolée pour garantir la reproductibilité

## 🗄️ Base de données de test

### Pourquoi une base séparée ?

1. **Isolation** : Les tests ne modifient jamais vos données de développement
2. **Reproductibilité** : Chaque test part d'un état connu et prévisible
3. **Parallélisation** : Possibilité de lancer plusieurs suites en parallèle
4. **Sécurité** : Aucun risque de corrompre des données importantes

### Configuration

Symfony utilise automatiquement une base de test grâce au suffixe `_test` :
- Base de dev : `ecommerce`
- Base de test : `ecommerce_test`

Cette configuration se trouve dans `config/packages/doctrine.yaml` :

```yaml
when@test:
    doctrine:
        dbal:
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'
```

### Auto-configuration MySQL

Les deux bases de données (`ecommerce` et `ecommerce_test`) sont créées automatiquement au démarrage de Docker grâce au script `docker/mysql/init.sql` qui :
- Crée les bases avec le bon charset (utf8mb4)
- Configure les permissions pour l'utilisateur `symfony`
- Évite les problèmes de droits d'accès

Lors d'un `make rebuild`, tout est reconfiguré automatiquement !

## 🚀 Setup initial

### Première fois uniquement

Les bases de données sont déjà créées automatiquement par Docker. Il suffit d'appliquer le schéma et charger les fixtures :

```bash
# 1. Appliquer le schéma (migrations)
make console cmd="doctrine:migrations:migrate --env=test --no-interaction"

# 2. Charger les fixtures
make console cmd="doctrine:fixtures:load --env=test --no-interaction"
```

Ou avec les commandes Makefile (recommandé) :

```bash
make test-db-migrate
make test-db-fixtures
```

> **Note** : Si vous rencontrez des erreurs de permissions, vérifiez que le fichier `docker/mysql/init.sql` est bien monté dans le conteneur MySQL et faites un `make rebuild`.

## 🧪 Lancer les tests

### Tous les tests

```bash
make test
```

### Par type de test

```bash
# Tests unitaires uniquement
php bin/phpunit --testsuite=Unit

# Tests fonctionnels uniquement
php bin/phpunit --testsuite=Functional
```

### Un fichier de test spécifique

```bash
php bin/phpunit tests/Controller/CategoryControllerTest.php
```

### Une méthode de test spécifique

```bash
php bin/phpunit --filter testIndex tests/Controller/CategoryControllerTest.php
```

## 🔄 Réinitialiser la base de test

Parfois, après avoir modifié le schéma ou les fixtures, il faut repartir de zéro :

```bash
# Méthode 1 : Via Makefile (recommandé)
make test-db-reset

# Méthode 2 : Manuellement
php bin/console doctrine:database:drop --env=test --force
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/console doctrine:fixtures:load --env=test --no-interaction
```

## 📝 Écrire des tests

### Structure des tests

```
tests/
├── Unit/              # Tests unitaires (logique métier pure)
│   ├── Entity/
│   └── Service/
├── Functional/        # Tests fonctionnels (HTTP, BDD)
│   └── Controller/
└── bootstrap.php
```

### Exemple : Test fonctionnel

```php
namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/category/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Category index');
    }
}
```

### Exemple : Test unitaire

```php
namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testPriceIsStoredAsString(): void
    {
        $product = new Product();
        $product->setPrice('19.99');

        $this->assertIsString($product->getPrice());
        $this->assertEquals('19.99', $product->getPrice());
    }
}
```

## 🎭 Fixtures de test

### Fixtures dédiées aux tests

Vous pouvez créer des fixtures spécifiques pour les tests, plus légères que celles de développement :

```php
namespace App\DataFixtures\Test;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

class CategoryTestFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['test']; // Groupe 'test'
    }

    public function load(ObjectManager $manager): void
    {
        // Fixtures minimales pour les tests
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');
        
        $manager->persist($category);
        $manager->flush();
    }
}
```

Charger uniquement le groupe 'test' :

```bash
php bin/console doctrine:fixtures:load --env=test --group=test --no-interaction
```

## 🐛 Debugging des tests

### Voir les requêtes SQL

```php
// Dans votre test
$client = static::createClient();
$client->enableProfiler(); // Active le profiler

$crawler = $client->request('GET', '/category/');

// Afficher les requêtes SQL
$profile = $client->getProfile();
$queries = $profile->getCollector('db')->getQueries();
dump($queries);
```

### Voir le contenu HTML

```php
$crawler = $client->request('GET', '/category/');
dump($crawler->html()); // Affiche le HTML complet
```

### Voir les erreurs Doctrine

Si vos tests échouent avec des erreurs de base de données :

```bash
# Vérifier l'état du schéma
php bin/console doctrine:schema:validate --env=test

# Mettre à jour si besoin
php bin/console doctrine:schema:update --env=test --force
```

## 📊 Couverture de code

### Générer un rapport de couverture

```bash
# Format HTML (à ouvrir dans le navigateur)
XDEBUG_MODE=coverage php bin/phpunit --coverage-html coverage/

# Format texte dans le terminal
XDEBUG_MODE=coverage php bin/phpunit --coverage-text
```

Le rapport HTML sera dans `coverage/index.html`.

## ✅ Bonnes pratiques

### 1. Tests indépendants
Chaque test doit pouvoir s'exécuter indépendamment des autres.

```php
// ❌ Mauvais : dépend de l'ordre d'exécution
public function testCreateCategory() { /* ... */ }
public function testEditCategory() { /* suppose que create a été exécuté */ }

// ✅ Bon : chaque test crée ses propres données
public function testCreateCategory() { /* ... */ }
public function testEditCategory() {
    $category = new Category();
    // ... setup complet
}
```

### 2. Nommage clair

```php
// ❌ Mauvais
public function testCategory() { /* ... */ }

// ✅ Bon
public function testCategoryCanBeCreatedWithValidData() { /* ... */ }
public function testCategorySlugIsGeneratedAutomatically() { /* ... */ }
```

### 3. Arrange-Act-Assert

```php
public function testProductPriceCalculation(): void
{
    // Arrange : Préparer les données
    $product = new Product();
    $product->setPrice('10.00');
    
    // Act : Exécuter l'action
    $total = $product->calculateTotalWithTax(0.20);
    
    // Assert : Vérifier le résultat
    $this->assertEquals('12.00', $total);
}
```

### 4. Tester les cas limites

```php
public function testProductWithZeroStock(): void { /* ... */ }
public function testProductWithNegativePrice(): void { /* ... */ }
public function testProductWithVeryLongName(): void { /* ... */ }
```

## 🚨 Erreurs courantes

### "Access denied to database 'ecommerce_test'"

➡️ **Solution** : Le script d'initialisation MySQL ne s'est pas exécuté correctement.

```bash
# Vérifier que le fichier init.sql est bien monté
docker compose exec database ls -la /docker-entrypoint-initdb.d/

# Si absent, vérifier compose.yml et faire un rebuild
make rebuild
```

Sinon, créer manuellement les permissions :
```bash
docker compose exec database mysql -u root -proot -e "
GRANT ALL PRIVILEGES ON ecommerce_test.* TO 'symfony'@'%';
FLUSH PRIVILEGES;"
```

### "Table doesn't exist"

➡️ Le schéma n'est pas à jour : `make test-db-migrate`

### "Tests fail randomly"

➡️ Les tests ne sont pas isolés. Vérifier que chaque test recharge les fixtures ou utilise des transactions.

### "Foreign key constraint fails"

➡️ L'ordre de chargement des fixtures est incorrect. Utiliser `DependentFixtureInterface`.

## 📖 Ressources

- [Documentation PHPUnit](https://phpunit.de/documentation.html)
- [Symfony Testing](https://symfony.com/doc/current/testing.html)
- [Doctrine Fixtures](https://symfony.com/bundles/DoctrineFixturesBundle/current/index.html)

## 🎓 Pour aller plus loin

### Tests d'intégration avec base de données

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testFindActiveProducts(): void
    {
        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['isActive' => true]);
        
        $this->assertCount(5, $products);
    }
}
```

### Tests API (pour plus tard avec API Platform)

```php
public function testGetProductCollection(): void
{
    $client = static::createClient();
    $client->request('GET', '/api/products');

    $this->assertResponseIsSuccessful();
    $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    $this->assertJsonContains([
        '@context' => '/api/contexts/Product',
        '@type' => 'hydra:Collection',
    ]);
}
```