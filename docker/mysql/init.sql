-- Donner les permissions nécessaires
GRANT ALL PRIVILEGES ON ecommerce.* TO 'symfony'@'%';
GRANT ALL PRIVILEGES ON ecommerce_test.* TO 'symfony'@'%';
FLUSH PRIVILEGES;

-- Créer les bases
CREATE DATABASE IF NOT EXISTS ecommerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ecommerce_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;