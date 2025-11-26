# Configuration Docker

## 🐳 Stack technique

L'environnement de développement est entièrement conteneurisé avec Docker Compose :

- **PHP 8.3 FPM** - Moteur PHP avec extensions requises
- **Nginx 1.27** - Serveur web
- **MySQL 8.0** - Base de données
- **Redis Alpine** - Cache
- **Adminer** - Interface d'administration de la base de données
- **MailHog** - Capture des emails en développement

## 📁 Structure

```
docker/
├── nginx/
│   ├── Dockerfile
│   └── default.conf       # Configuration Nginx
├── php/
│   └── Dockerfile         # Image PHP avec extensions
└── mysql/
    └── init.sql           # Script d'initialisation automatique
```

## 🔧 Configuration MySQL

### Script d'initialisation automatique

Le fichier `docker/mysql/init.sql` est exécuté automatiquement au premier démarrage du conteneur MySQL. Il :

1. **Crée les bases de données** :
   - `ecommerce` - Base de développement
   - `ecommerce_test` - Base de test

2. **Configure les permissions** :
   - L'utilisateur `symfony` peut gérer les deux bases
   - Évite les problèmes de droits d'accès

3. **Définit le charset** :
   - `utf8mb4` pour supporter les emojis et caractères spéciaux
   - `utf8mb4_unicode_ci` pour le tri correct en français

### Contenu du script

```sql
-- Permissions pour l'utilisateur symfony
GRANT ALL PRIVILEGES ON ecommerce.* TO 'symfony'@'%';
GRANT ALL PRIVILEGES ON ecommerce_test.* TO 'symfony'@'%';
FLUSH PRIVILEGES;

-- Création des bases
CREATE DATABASE IF NOT EXISTS ecommerce 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS ecommerce_test 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;
```

### Quand le script s'exécute

- ✅ Au premier `docker compose up`
- ✅ Après un `docker compose down -v` (suppression des volumes)
- ✅ Après un `make rebuild`
- ❌ PAS lors d'un simple restart des conteneurs

### Vérifier que le script est bien monté

```bash
docker compose exec database ls -la /docker-entrypoint-initdb.d/
```

Vous devriez voir `init.sql` dans la liste.

## 🔄 Volumes Docker

### Volumes persistants

```yaml
volumes:
  db_data:  # Données MySQL persistantes
```

Les données de la base sont sauvegardées dans un volume Docker et **survivent** aux redémarrages des conteneurs.

### Volumes montés

```yaml
volumes:
  - ./:/var/www:delegated                                    # Code source
  - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql  # Script init MySQL
```

## 🛠️ Commandes utiles

### Gestion des conteneurs

```bash
# Démarrer
make up

# Arrêter (garde les données)
make down

# Reconstruire (après modif Dockerfile)
make rebuild

# Voir les logs
make logs
make logs-php
make logs-nginx
make logs-db
```

### Accéder aux conteneurs

```bash
# Shell PHP
make shell

# Shell Nginx
make nginx-shell

# MySQL CLI
docker compose exec database mysql -u symfony -psymfony ecommerce
docker compose exec database mysql -u root -proot
```

### Réinitialiser complètement

```bash
# Supprimer TOUT (conteneurs + volumes)
make clean

# Puis reconstruire
make build
make up
```

⚠️ **Attention** : `make clean` supprime toutes les données de la base !

## 🔐 Credentials

### MySQL

**Utilisateur applicatif** :
- User : `symfony`
- Password : `symfony`
- Databases : `ecommerce`, `ecommerce_test`

**Utilisateur root** :
- User : `root`
- Password : `root`
- Accès : Toutes bases

### Adminer

- URL : http://localhost:8082
- Système : MySQL
- Serveur : `database` (nom du service Docker)
- Utilisateur : `symfony` ou `root`
- Mot de passe : `symfony` ou `root`

## 📊 Ports exposés

| Service | Port interne | Port externe | URL |
|---------|-------------|--------------|-----|
| Nginx | 80 | 8000 | http://localhost:8000 |
| MySQL | 3306 | 3306 | localhost:3306 |
| Redis | 6379 | 6379 | localhost:6379 |
| Adminer | 8080 | 8082 | http://localhost:8082 |
| MailHog UI | 8025 | 8025 | http://localhost:8025 |
| MailHog SMTP | 1025 | 1025 | localhost:1025 |

## 🐛 Troubleshooting

### Port déjà utilisé

**Erreur** : `Bind for 0.0.0.0:3306 failed: port is already allocated`

**Solution** : Un autre service utilise ce port (MySQL local, MAMP, etc.)

```bash
# Voir qui utilise le port
sudo lsof -i :3306

# Arrêter le service local ou changer le port dans compose.yml
ports:
  - "3307:3306"  # Au lieu de 3306:3306
```

### Le script init.sql ne s'exécute pas

**Causes possibles** :
1. Le volume MySQL existe déjà avec des données
2. Le fichier n'est pas monté correctement

**Solution** :
```bash
# Supprimer le volume MySQL
docker compose down -v

# Vérifier que le fichier existe
ls -la docker/mysql/init.sql

# Redémarrer
make up
```

### Permissions sur les fichiers

**Erreur** : `Permission denied` lors de l'écriture de cache/logs

**Solution** : Configurer les USER_ID et GROUP_ID dans `.env`

```bash
# Trouver votre UID/GID
id -u  # USER_ID
id -g  # GROUP_ID

# Dans .env
USER_ID=1000
GROUP_ID=1000
```

### Conteneur ne démarre pas

```bash
# Voir les logs détaillés
docker compose logs database
docker compose logs php

# Reconstruire l'image
make rebuild
```

## 🚀 Optimisations

### Cache Composer

Monter le cache Composer pour accélérer les installations :

```yaml
php:
  volumes:
    - ./:/var/www:delegated
    - ~/.composer:/root/.composer:cached
```

### OPcache en production

OPcache est déjà configuré dans l'image PHP. Pour la production, ajuster :

```ini
; docker/php/php.ini
opcache.validate_timestamps=0  ; Ne pas vérifier les fichiers à chaque requête
opcache.max_accelerated_files=20000
opcache.memory_consumption=256
```

## 📝 Bonnes pratiques

1. **Ne jamais commiter** :
   - `.env.local`
   - `.env.test.local`
   - `docker-compose.override.yml`

2. **Toujours utiliser** :
   - Les noms de services Docker (`database`, pas `localhost`)
   - Les variables d'environnement pour les credentials

3. **Backup régulier** en dev :
   ```bash
   docker compose exec database mysqldump -u root -proot ecommerce > backup.sql
   ```

## 🔗 Ressources

- [Docker Compose documentation](https://docs.docker.com/compose/)
- [MySQL Docker Image](https://hub.docker.com/_/mysql)
- [PHP Docker Image](https://hub.docker.com/_/php)