#!/bin/bash
set -e

echo "🚀 Starting WinterShop Backend..."

# Wait for database to be ready
echo "⏳ Waiting for database..."

if [ -z "$DATABASE_URL" ]; then
    echo "❌ DATABASE_URL is not set!"
    exit 1
fi

max_attempts=30
attempt=0

until php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1 || [ $attempt -eq $max_attempts ]; do
    attempt=$((attempt + 1))
    echo "Waiting for database... (attempt $attempt/$max_attempts)"
    sleep 3
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
php bin/console cache:clear --no-warmup --env=prod
php bin/console cache:warmup --env=prod
echo "✅ Cache ready!"

# Run database migrations
echo "📊 Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
echo "✅ Migrations completed!"

# Load fixtures only if LOAD_FIXTURES env var is set to "true"
if [ "$LOAD_FIXTURES" = "true" ]; then
    echo "🌱 Loading fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction --env=prod
    echo "✅ Fixtures loaded!"
else
    echo "ℹ️  Skipping fixtures (set LOAD_FIXTURES=true to load)"
fi

echo "✅ Backend is ready!"
echo "🔌 Starting PHP server on port ${PORT:-8080}..."

# Start PHP built-in server
exec php -S 0.0.0.0:${PORT:-8080} -t public/
