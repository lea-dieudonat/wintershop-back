# Third-Party Dependencies

Complete documentation of external services and libraries used in the WinterShop project, including their purpose, usage, and integration points.

## Backend Services

### Stripe (Payment Processing)

**Package**: `stripe/stripe-php` v19.2+

**Purpose**: Payment processing and checkout session management for the e-commerce platform.

**Why**: Industry-standard payment processor with robust security, PCI compliance, and extensive API features.

**Where Used**:

-   [src/Service/StripePaymentService.php](../src/Service/StripePaymentService.php) - Main integration service
-   [src/Controller/Api/StripeWebhookController.php](../src/Controller/Api/StripeWebhookController.php) - Webhook handling for payment events
-   [src/Controller/Api/CheckoutController.php](../src/Controller/Api/CheckoutController.php) - Checkout flow integration

**Key Integration Points**:

1. **Checkout Session Creation**

    - Creates payment intent via Stripe Checkout
    - Converts cart items to Stripe line items
    - Handles currency conversion (EUR, stored in cents)
    - Supports dynamic shipping costs

2. **Webhook Handling**

    - Listens to `checkout.session.completed` events
    - Updates order status to `paid`
    - Decrements product stock
    - Signature verification for security

3. **Amount Conversion**
    - Frontend: decimal format (EUR) e.g., "19.99"
    - Stripe: integer cents e.g., 1099
    - Conversion methods: `convertToStripeAmount()`, `convertFromStripeAmount()`

**Configuration**:

```yaml
# .env (or .env.local for secrets)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# config/services.yaml
parameters:
    stripe.public_key: "%env(STRIPE_PUBLIC_KEY)%"

services:
    Stripe\StripeClient:
        arguments:
            - "%env(STRIPE_SECRET_KEY)%"
```

**API Docs**: https://stripe.com/docs/api

---

## Backend Frameworks & Bundles

### Symfony Framework 7.3

**Purpose**: Core PHP web framework for the backend API.

**Why**:

-   Flexible, modular architecture
-   Excellent routing and dependency injection
-   Strong security features
-   Large, mature ecosystem

**Key Components Used**:

-   `symfony/framework-bundle` - Core framework
-   `symfony/console` - CLI commands
-   `symfony/serializer` - JSON serialization
-   `symfony/validator` - Input validation
-   `symfony/security-bundle` - Authentication & authorization
-   `symfony/routing` - URL routing

---

### API Platform 4.2

**Packages**:

-   `api-platform/symfony`
-   `api-platform/doctrine-orm`

**Purpose**: Rapid REST/GraphQL API development with automatic CRUD operations, filtering, and pagination.

**Why**:

-   Automatic OpenAPI documentation
-   Built-in filtering, sorting, pagination
-   Easy nested resource handling
-   Security integration
-   Content negotiation

**Where Used**:

-   Cart management API
-   Product catalog browsing
-   Order listing and detail
-   Address management
-   User profile endpoints

**Example**:

```php
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ],
    filters: [SearchFilter::class, RangeFilter::class],
)]
class Product { ... }
```

**Features**:

-   Automatic OpenAPI schema generation
-   Doctrine ORM integration
-   State providers/processors for custom logic
-   DTO transformation
-   Security context awareness

---

### Doctrine ORM & Doctrine Bundle

**Packages**:

-   `doctrine/orm` v3.5+
-   `doctrine/doctrine-bundle`
-   `doctrine/doctrine-migrations-bundle`

**Purpose**: Object-relational mapping (ORM) and database schema management.

**Why**:

-   Abstraction layer over raw SQL
-   Type-safe queries with DQL
-   Automatic migrations
-   Relationship management (OneToMany, ManyToOne, etc.)

**Where Used**: Throughout the application for all database operations

**Key Features**:

-   Entity definitions with attributes
-   Lifecycle callbacks (`#[ORM\PrePersist]`, etc.)
-   Cascade operations (delete, persist)
-   Migrations with versioning
-   Fixtures for test data

---

### EasyAdmin 4

**Package**: `easycorp/easyadmin-bundle` v4.27+

**Purpose**: Admin CRUD interface for managing products, categories, orders, and users.

**Why**:

-   Zero-configuration admin panels
-   Customizable fields and actions
-   Built-in security
-   Minimal code required

**Where Used**:

-   Admin dashboard at `/admin`
-   Product management
-   Order management with status transitions
-   User administration
-   Category management

---

## Security & Authentication

### Lexik JWT Authentication Bundle

**Package**: `lexik/jwt-authentication-bundle` v3.1+

**Purpose**: JWT (JSON Web Token) authentication for stateless API requests.

**Why**:

-   Stateless authentication (no sessions)
-   Secure token exchange
-   Per-request verification
-   Mobile/SPA compatible

**Configuration**:

-   Private/public key pair in `config/jwt/`
-   Token generation on login
-   Token validation on protected routes

**Protected Routes** (require `ROLE_USER`):

-   Cart operations (`/api/cart*`)
-   Order endpoints (`/api/orders*`)
-   Address management (`/api/addresses*`)

---

## Data & Testing

### Zenstruck Foundry

**Package**: `zenstruck/foundry` v2.8+

**Purpose**: Factories for generating test fixtures and test data.

**Why**:

-   Fluent API for creating test entities
-   Reduces test boilerplate
-   Ensures data consistency

**Usage**:

```php
$user = UserFactory::createOne(['email' => 'test@example.com']);
$products = ProductFactory::createMany(10);
```

---

### Doctrine Fixtures Bundle

**Package**: `doctrine/doctrine-fixtures-bundle` v4.3+

**Purpose**: Load seed data into the database for development/testing.

**Where Used**: `src/DataFixtures/` - Test data population

**Commands**:

```bash
make fixtures              # Load fixtures
make test-db-fixtures     # Load test fixtures
```

---

### DAMA Doctrine Test Bundle

**Package**: `dama/doctrine-test-bundle` v8.4+

**Purpose**: Database isolation in tests - each test runs in a transaction that's rolled back.

**Why**:

-   Tests don't interfere with each other
-   No need for complex cleanup
-   Faster test execution

---

## API Tools

### Nelmio CORS Bundle

**Package**: `nelmio/cors-bundle` v2.6+

**Purpose**: Handle Cross-Origin Resource Sharing (CORS) for the React frontend.

**Configuration**:

```yaml
# .env
CORS_ALLOW_ORIGIN=http://localhost:5173
```

**Why**: Allows frontend on different port/domain to make requests to backend API.

---

## Frontend Dependencies

### TanStack Query (React Query)

**Purpose**: Server state management for API data fetching, caching, and synchronization.

**Why**:

-   Automatic caching and deduplication
-   Background refetching
-   Stale-while-revalidate pattern
-   Infinite queries for pagination
-   Reduces boilerplate vs raw fetch/async

**Example Use Cases**:

-   Fetching product catalog
-   Loading user cart
-   Paginating orders
-   Real-time updates

---

## Development Tools

### PHPStan

**Package**: `phpstan/phpdoc-parser` v2.3+

**Purpose**: Static analysis and type checking for PHP code.

---

### PHPUnit

**Package**: `phpunit/phpunit` v12.4+

**Purpose**: Unit and functional testing framework.

**Usage**:

```bash
make test              # Run all tests
make test-unit         # Unit tests only
make test-functional   # Functional tests only
```

---

### Symfony Maker Bundle

**Package**: `symfony/maker-bundle` v1.65+

**Purpose**: Scaffolding for generating entities, controllers, forms, etc.

**Commands**:

```bash
make:entity      # Generate entity
make:controller  # Generate controller
make:form        # Generate form class
make:factory     # Generate test factory
```

---

## Utility Libraries

### Symfony String Slugger

**Package**: `symfony/string` (via `Symfony\Component\String\Slugger\AsciiSlugger`)

**Purpose**: Generate URL-friendly slugs from product/category names.

**Example**:

-   "T-Shirt Coton Bio" → "t-shirt-coton-bio"

**Where Used**:

-   Product slug generation (`Product::generateSlug()`)
-   Category slug generation (`Category::generateSlug()`)

---

### BCMath Functions

**Purpose**: High-precision decimal arithmetic for financial calculations.

**Why**: Avoid floating-point precision errors with money.

**Usage**:

```php
$total = bcadd('10.00', '20.50', 2);      // "30.50"
$price = bcmul('19.99', '3', 2);          // "59.97"
$stripeAmount = bcmul('19.99', '100', 0); // 1999 (cents)
```

**Where Used**:

-   Price calculations (cart, orders)
-   Stock management
-   Stripe amount conversion

---

## Summary Table

| Package                      | Version    | Purpose        | Security              | Config        |
| ---------------------------- | ---------- | -------------- | --------------------- | ------------- |
| **stripe/stripe-php**        | ^19.2      | Payments       | ✅ Keys in .env.local | `STRIPE_*`    |
| **api-platform/symfony**     | ^4.2       | REST API       | ✅ Built-in           | routes.yaml   |
| **doctrine/orm**             | ^3.5       | ORM/Database   | ✅ PDO                | doctrine.yaml |
| **symfony/framework-bundle** | 7.3.\*     | Core Framework | ✅ Security bundle    | security.yaml |
| **lexik/jwt-authentication** | ^3.1       | JWT Auth       | ✅ Tokens required    | config/jwt/   |
| **easycorp/easyadmin**       | ^4.27      | Admin Panel    | ✅ Role-based         | /admin        |
| **nelmio/cors-bundle**       | ^2.6       | CORS Headers   | ⚠️ Limited by env     | .env          |
| **zenstruck/foundry**        | ^2.8 (dev) | Test Factories | N/A                   | tests/        |
