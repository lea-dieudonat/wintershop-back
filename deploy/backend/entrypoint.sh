#!/bin/bash
set -e

echo "🚀 Starting WinterShop Backend..."

# Wait for database to be ready
echo "⏳ Waiting for database..."
max_attempts=30
attempt=0
until php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1 || [ $attempt -eq $max_attempts ]; do
    attempt=$((attempt + 1))
    echo "Waiting for database... (attempt $attempt/$max_attempts)"
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "❌ Database connection failed after $max_attempts attempts"
    exit 1
fi

echo "✅ Database is ready!"

# Generate JWT keys if they don't exist
if [ ! -f config/jwt/private.pem ]; then
    echo "🔑 Generating JWT keys..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists
    echo "✅ JWT keys generated!"
fi

# Clear and warm up cache
echo "🗑️  Clearing cache..."
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
echo "✅ Cache ready!"

# Run database migrations
echo "📊 Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo "✅ Migrations completed!"

# Load fixtures only if LOAD_FIXTURES env var is set to "true"
if [ "$LOAD_FIXTURES" = "true" ]; then
    echo "🌱 Loading fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction
    echo "✅ Fixtures loaded!"
else
    echo "ℹ️  Skipping fixtures (set LOAD_FIXTURES=true to load)"
fi

# Set proper permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/var
chmod -R 775 /var/www/var

echo "✅ Backend is ready!"

# Replace PORT placeholder in nginx config with Railway's dynamic PORT
echo "🔌 Configuring port ${PORT:-8000}..."
sed -i "s/\${PORT}/${PORT:-8000}/g" /etc/nginx/http.d/default.conf

echo "🎉 Starting services..."

# Start supervisord (manages PHP-FPM and Nginx)
exec /usr/bin/supervisord -c /etc/supervisord.conf
