#!/bin/bash
set -e

echo "🚀 Starting WinterShop Backend (Simplified)..."

# Debug: Show DATABASE_URL (hide password)
echo "🔍 DATABASE_URL is set: ${DATABASE_URL:0:20}..." 

# Wait for database to be ready
echo "⏳ Waiting for database..."

# Extract MySQL connection info from DATABASE_URL
# Format: mysql://user:pass@host:port/dbname
if [ -z "$DATABASE_URL" ]; then
    echo "❌ DATABASE_URL is not set!"
    exit 1
fi

echo "🔍 DATABASE_URL: ${DATABASE_URL:0:30}..."

# Parse DATABASE_URL
DB_HOST=$(echo $DATABASE_URL | sed -n 's#.*@\([^:]*\).*#\1#p')
DB_PORT=$(echo $DATABASE_URL | sed -n 's#.*:\([0-9]*\)/.*#\1#p')
DB_USER=$(echo $DATABASE_URL | sed -n 's#.*://\([^:]*\):.*#\1#p')
DB_PASS=$(echo $DATABASE_URL | sed -n 's#.*://[^:]*:\([^@]*\)@.*#\1#p')
DB_NAME=$(echo $DATABASE_URL | sed -n 's#.*/\([^?]*\).*#\1#p')

echo "🔍 Parsed connection:"
echo "   Host: $DB_HOST"
echo "   Port: $DB_PORT"
echo "   User: $DB_USER"
echo "   Database: $DB_NAME"

max_attempts=30
attempt=0

until mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1" "$DB_NAME" > /dev/null 2>&1 || [ $attempt -eq $max_attempts ]; do
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
