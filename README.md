# ❄️ WinterShop - Backend API

> API REST robuste pour une application e-commerce de sports d'hiver, développée avec Symfony 7.3 et API Platform.

[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![API Platform](https://img.shields.io/badge/API_Platform-4.2-38A3A5)](https://api-platform.com/)

## 📋 À propos

WinterShop Backend est l'API REST du projet WinterShop, une application e-commerce full-stack développée comme **projet portfolio**. Cette API fournit tous les endpoints nécessaires pour gérer un catalogue de produits, l'authentification utilisateur, les paniers d'achat, les commandes et les paiements via Stripe.

**🔗 Frontend Repository:** [wintershop-front](https://github.com/lea-dieudonat/wintershop-front)

## ✨ Fonctionnalités API

### 🔐 Authentification & Utilisateurs

- Authentification JWT stateless
- Inscription / Connexion
- Gestion du profil utilisateur
- CRUD complet des adresses de livraison
- Changement de mot de passe (endpoint dédié)

### 🛍️ Catalogue

- Liste paginée des produits avec filtres
- Détails produit complets
- Catégorisation des produits
- Gestion du stock
- Images produits

### 🛒 Panier

- Panier persistant côté serveur
- Ajout/modification/suppression d'articles
- Calcul automatique des totaux
- Association au user authentifié

### 📦 Commandes

- Création de commande depuis le panier
- Workflow de checkout complet
- Intégration Stripe pour le paiement
- Statuts de commande (pending, paid, cancelled, refunded)
- Historique des commandes utilisateur
- Demandes d'annulation et de remboursement
- Webhooks Stripe pour mise à jour automatique

### 💳 Paiement

- Intégration Stripe Checkout
- Création de sessions de paiement
- Gestion des webhooks (`checkout.session.completed`)
- Mise à jour automatique des stocks après paiement
- Gestion des montants en centimes

### 📊 Administration

- Interface EasyAdmin pour gestion back-office
- Gestion des produits, catégories, commandes
- Suivi des commandes et remboursements

## 🛠️ Stack Technique

### Core

- **Symfony 7.3** - Framework PHP moderne
- **PHP 8.2** - Langage avec typage strict
- **MySQL 8.0** - Base de données relationnelle
- **Doctrine ORM** - Mapping objet-relationnel

### API

- **API Platform 4.2** - Framework API REST/GraphQL
- **JWT Authentication** - Authentification stateless (Lexik JWT)
- **CORS Bundle** - Gestion CORS pour SPA

### Services Externes

- **Stripe PHP SDK** - Paiement sécurisé
- **MailHog** - Tests d'emails en développement

### DevOps & Qualité

- **Docker** - Environnement de développement
- **PHPUnit** - Tests fonctionnels
- **Zenstruck Foundry** - Factories pour fixtures et tests
- **Make** - Automation des commandes

### Sécurité

- **BCMath** - Calculs monétaires précis (pas de floats)
- **Validation Symfony** - Validation des données
- **Security Bundle** - Gestion des permissions et rôles

## 🚀 Installation

### Prérequis

- Docker & Docker Compose
- Make (optionnel mais recommandé)
- Git

### Installation locale

```bash
# 1. Cloner le repository
git clone https://github.com/lea-dieudonat/wintershop-back.git
cd wintershop-back

# 2. Lancer l'environnement Docker
make build
make up

# 3. Installer les dépendances Composer
make composer-install

# 4. Générer les clés JWT
make jwt-generate

# 5. Créer et migrer la base de données
make db-migrate

# 6. Charger les données de démonstration
make fixtures
```

### Variables d'environnement

Configurer le fichier `.env.local` :

```env
# Database
DATABASE_URL=mysql://symfony:symfony@database:3306/ecommerce?serverVersion=8.0

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase_ici

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Frontend URL (pour CORS)
APP_FRONTEND_URL=http://localhost:5173
```

### Accès aux services

- **API** : http://localhost:8000/api
- **Documentation API** : http://localhost:8000/api/docs
- **Admin** : http://localhost:8000/admin
- **Adminer** (BDD) : http://localhost:8082
- **MailHog** (Emails) : http://localhost:8025

## 📁 Structure du Projet

```
src/
├── Controller/         # Controllers API et Admin
│   ├── Admin/         # Dashboard EasyAdmin
│   └── Api/           # Endpoints API custom
├── Dto/               # Data Transfer Objects
│   ├── Address/
│   ├── Cart/
│   ├── Checkout/
│   └── Order/
├── Entity/            # Entités Doctrine
├── Mapper/            # Mappers Entity ↔ DTO
├── Repository/        # Repositories Doctrine
├── Service/           # Services métier
│   ├── CartService
│   ├── CheckoutService
│   └── StripePaymentService
├── State/             # StateProviders et StateProcessors (API Platform)
└── Validator/         # Contraintes de validation custom

tests/
├── Functional/        # Tests fonctionnels API
│   ├── Auth/
│   ├── Cart/
│   ├── Checkout/
│   └── Order/
└── bootstrap.php
```

## 🗄️ Modèle de Données

### Entités Principales

- **User** : Utilisateurs avec authentification
- **Product** : Produits du catalogue
- **Category** : Catégories de produits
- **Cart** / **CartItem** : Panier d'achat
- **Order** / **OrderItem** : Commandes
- **Address** : Adresses de livraison

**Relations clés :**

- User 1→N Addresses (une adresse par défaut)
- User 1→1 Cart
- Cart 1→N CartItems
- User 1→N Orders
- Order 1→N OrderItems

📖 **Schéma détaillé** : Voir [docs/database](docs/database/)

## 🛠️ Commandes Make Disponibles

### Développement

```bash
make up              # Démarrer les conteneurs
make down            # Arrêter les conteneurs
make logs            # Voir les logs
make shell           # Accéder au shell PHP
make ps              # Statut des conteneurs
```

### Base de données

```bash
make db-create       # Créer la base
make db-migrate      # Appliquer les migrations
make db-reset        # Réinitialiser (drop + create + migrate)
make fixtures        # Charger les fixtures
make migration       # Créer une nouvelle migration
```

### Tests

```bash
make test            # Lancer tous les tests
make test-db-reset   # Réinitialiser la base de test
make test-coverage   # Coverage HTML
```

### Qualité

```bash
make cache-clear     # Vider le cache
make composer-install # Installer les dépendances
make jwt-generate    # Générer les clés JWT
```

### Aide

```bash
make help            # Afficher toutes les commandes
```

## 🧪 Tests

### Architecture de Tests

Le projet utilise des **tests fonctionnels** pour valider l'ensemble des workflows API :

- **Auth** : Authentification, inscription, JWT
- **Cart** : CRUD panier et articles
- **Checkout** : Workflow complet de commande
- **Order** : Gestion des commandes, annulation, remboursement
- **Address** : CRUD adresses utilisateur

### Lancer les tests

```bash
# Tous les tests
make test

# Tests avec coverage
make test-coverage

# Réinitialiser la BDD de test
make test-db-reset
```

### Philosophie de Test

- **Isolation** : Chaque test est indépendant
- **Base de données dédiée** : `ecommerce_test`
- **Fixtures** : Données de test avec Foundry
- **Assertions** : Validation des status HTTP, réponses JSON, état BDD

📖 **Guide complet** : Voir [docs/testing.md](docs/testing.md)

## 🔒 Sécurité

### Authentification JWT

- Tokens signés avec clés RSA
- Expiration configurable
- Refresh tokens (à implémenter)

### Validation

- Validation Symfony sur tous les inputs
- DTO pour typage strict des requêtes
- Sanitization des données

### Permissions

- Routes protégées par rôles (`ROLE_USER`, `ROLE_ADMIN`)
- Vérification propriétaire pour ressources sensibles
- Security voters pour logique complexe

## 📊 API Endpoints Principaux

### Authentification

```
POST   /api/login              # Connexion (retourne JWT)
GET    /api/me                 # Infos user connecté
```

### Produits

```
GET    /api/products           # Liste des produits
GET    /api/products/{id}      # Détail produit
GET    /api/categories         # Liste des catégories
```

### Panier

```
GET    /api/cart               # Récupérer le panier
POST   /api/cart/items         # Ajouter au panier
PATCH  /api/cart/items/{id}    # Modifier quantité
DELETE /api/cart/items/{id}    # Retirer du panier
```

### Commandes

```
GET    /api/orders             # Historique des commandes
GET    /api/orders/{id}        # Détail commande
POST   /api/orders/{id}/cancel # Demander annulation
POST   /api/orders/{id}/refund # Demander remboursement
```

### Checkout

```
POST   /api/checkout           # Créer session Stripe
GET    /api/checkout/success   # Callback succès
GET    /api/checkout/cancel    # Callback annulation
```

### Adresses

```
GET    /api/addresses          # Liste des adresses
POST   /api/addresses          # Créer une adresse
PATCH  /api/addresses/{id}     # Modifier une adresse
DELETE /api/addresses/{id}     # Supprimer une adresse
```

### Webhooks

```
POST   /api/stripe/webhook     # Webhook Stripe (public)
```

📖 **Documentation interactive** : http://localhost:8000/api/docs

## 🎯 Architecture & Patterns

### Clean Architecture

- Séparation des couches (Controller, Service, Repository)
- DTOs pour découplage API ↔ Entités
- State Providers/Processors pour API Platform

### Domain-Driven Design

- Logique métier dans les Services
- Repositories pour l'accès aux données
- Value Objects pour concepts métier

### API Platform

- Mapping automatique Entity ↔ DTO
- Validation déclarative
- Serialization groups
- Pagination automatique

📖 **Documentation détaillée** : Voir [docs/](docs/)

## 🚀 Déploiement

### Build de production

```bash
# Optimiser l'autoloader
composer install --no-dev --optimize-autoloader

# Vider et warmer le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### Prêt pour

- **Railway** / **Render** (gratuit)
- **AWS EC2** / **DigitalOcean**
- **Heroku**
- **Docker Swarm** / **Kubernetes**

## 📖 Documentation Complète

- **[Docker](docs/docker.md)** - Configuration Docker complète
- **[Testing](docs/testing.md)** - Guide de tests et philosophie
- **[Database](docs/database/)** - Schéma et règles métier
- **[Entities](docs/entities.md)** - Documentation des entités
- **[Third Parties](docs/third-parties.md)** - Dépendances externes

## 🎓 Objectifs d'Apprentissage

Ce projet m'a permis de :

- ✅ Maîtriser Symfony 7 et ses best practices
- ✅ Construire une API REST professionnelle avec API Platform
- ✅ Implémenter une authentification JWT robuste
- ✅ Intégrer Stripe pour les paiements
- ✅ Écrire des tests fonctionnels complets
- ✅ Gérer des calculs financiers précis (BCMath)
- ✅ Utiliser Docker pour le développement
- ✅ Appliquer les principes de Clean Architecture

## 🤝 Contribution

Ce projet étant un portfolio personnel, les contributions ne sont pas acceptées. Cependant, n'hésitez pas à :

- ⭐ Star le projet si vous le trouvez intéressant
- 🐛 Ouvrir une issue pour signaler un bug
- 💡 Partager vos idées d'amélioration

## 📝 License

Ce projet est sous licence MIT - voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

⭐ **Si ce projet vous a plu, n'hésitez pas à lui donner une étoile !**
