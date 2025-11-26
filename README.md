# E-commerce Symfony

Projet e-commerce développé avec Symfony 7.3 pour les sports d'hiver et équipements de montagne.

## 🚀 Installation rapide

### Prérequis
- Docker & Docker Compose
- Make

### Première installation

```bash
# 1. Cloner le projet
git clone <votre-repo>
cd ecommerce-symfony

# 2. Lancer l'environnement Docker
make build
make up

# 3. Installer les dépendances
make composer-install

# 4. Créer et configurer les bases de données
# Note : Les bases ecommerce et ecommerce_test sont créées automatiquement
# avec les bonnes permissions grâce au script d'initialisation MySQL
make db-migrate

# 5. Charger les données de démonstration
make fixtures
```

### Accès aux services

- **Application** : http://localhost:8000
- **Adminer** (base de données) : http://localhost:8082
  - Système : MySQL
  - Serveur : database
  - Utilisateur : symfony
  - Mot de passe : symfony
  - Base : ecommerce
- **MailHog** (emails de test) : http://localhost:8025

## 🧪 Tests

### Configuration initiale (automatique)

Les bases de données de développement et de test (`ecommerce` et `ecommerce_test`) sont créées automatiquement au démarrage de Docker grâce au script `docker/mysql/init.sql`. Les permissions sont déjà configurées.

Il suffit d'appliquer les migrations et charger les fixtures :

```bash
# Appliquer le schéma sur la base de test
make test-db-migrate

# Charger les données de test
make test-db-fixtures
```

### Lancer les tests

```bash
# Tous les tests
make test

# Réinitialiser la base de test
make test-db-reset
```

📖 **Guide complet** : Voir [docs/testing.md](docs/testing.md)

## 📚 Documentation

- [Docker](docs/docker.md) - Configuration de l'environnement Docker
- [Testing](docs/testing.md) - Guide complet des tests
- [Database](docs/database.md) - Schéma et règles métier
- [Entities](docs/entities.md) - Documentation des entités

## 🛠️ Commandes utiles

```bash
# Développement
make up              # Démarrer les conteneurs
make down            # Arrêter les conteneurs
make logs            # Voir les logs
make shell           # Accéder au conteneur PHP

# Base de données
make db-create       # Créer la base
make db-migrate      # Appliquer les migrations
make db-reset        # Réinitialiser la base
make fixtures        # Charger les fixtures

# Cache
make cache-clear     # Vider le cache

# Voir toutes les commandes
make help
```

## 🏗️ Architecture

- **Symfony 7.3** - Framework PHP
- **Doctrine ORM** - Gestion de la base de données
- **MySQL 8.0** - Base de données
- **Docker** - Environnement de développement
- **PHPUnit** - Tests

## 📖 Pour aller plus loin

Voir la documentation détaillée dans le dossier `docs/`.