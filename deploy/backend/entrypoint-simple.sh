#!/bin/bash
set -e

echo "🚀 Starting WinterShop Backend (Simplified)..."

# Debug: Show DATABASE_URL (hide password)
echo "🔍 DATABASE_URL is set: ${DATABASE_URL:0:20}..." 

# Wait for database to be ready
echo "⏳ Waiting for database..."

if [ -z "$DATABASE_URL" ]; then
    echo "❌ DATABASE_URL is not set!"
    exit 1
fi

echo "🔍 DATABASE_URL configured (${DATABASE_URL:0:20}...)"

max_attempts=60
attempt=0

# Use Symfony's DBAL to test connection (it handles the DATABASE_URL correctly)
echo "🔍 Testing database connection with Symfony DBAL..."
until php bin/console dbal:run-sql "SELECT 1" 2>&1 | tee /tmp/db-test.log || [ $attempt -eq $max_attempts ]; do
    attempt=$((attempt + 1))
    echo "Waiting for database... (attempt $attempt/$max_attempts)"
    if [ $attempt -eq 5 ] || [ $attempt -eq 15 ] || [ $attempt -eq 30 ]; then
        echo "⚠️  Still trying... Last error:"
        tail -3 /tmp/db-test.log || echo "(no error log)"
    fi
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
echo "🔌 PORT env var: ${PORT:-not set, using 8000}"
echo "🔌 Starting PHP server on port ${PORT:-8000}..."

# Start PHP built-in server
# Railway will use the PORT env var
exec php -S 0.0.0.0:${PORT:-8000} -t public/
