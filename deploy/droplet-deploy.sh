#!/bin/bash
# RunMyPrint droplet deploy: clone, configure, build, migrate, seed, serve on :80.
cd /root
rm -rf print
git clone --depth 1 https://github.com/iberescu/print
cd print

# Secrets come from the deploy environment (never committed). Example:
#   GEMINI_API_KEY=... APP_KEY=base64:... bash deploy/droplet-deploy.sh
cat > backend/.env <<ENVEOF
APP_NAME=RunMyPrint
APP_ENV=production
APP_KEY=${APP_KEY:-}
APP_DEBUG=false
APP_URL=${APP_URL:-https://runmyprint.com}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=runmyprint
DB_USERNAME=rmp
DB_PASSWORD=${DB_PASSWORD:-rmp}
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
MAIL_MAILER=log
FREE_SHIPPING_THRESHOLD=50
GEMINI_API_KEY=${GEMINI_API_KEY:-}
REPLICATE_API_TOKEN=${REPLICATE_API_TOKEN:-}
GEMINI_IMAGE_MODEL=gemini-3-pro-image
GEMINI_IMAGE_MODEL_FAST=gemini-3.1-flash-image
GEMINI_TEXT_MODEL=gemini-3.5-flash
GEMINI_VISION_MODEL=gemini-3.5-flash
STRIPE_KEY=${STRIPE_KEY:-}
STRIPE_SECRET=${STRIPE_SECRET:-}
STRIPE_WEBHOOK_SECRET=${STRIPE_WEBHOOK_SECRET:-}
ENVEOF

echo "=== build + up ==="
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

echo "=== wait for db ==="
for i in $(seq 1 40); do docker compose exec -T db mysqladmin ping -h localhost -uroot -proot >/dev/null 2>&1 && { echo "db ready"; break; }; sleep 3; done

echo "=== composer install ==="
docker compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction

echo "=== app key (generate if not provided) ==="
docker compose exec -T app sh -c "grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force"

echo "=== frontend build ==="
docker run --rm -v /root/print/backend:/app -w /app node:22-alpine sh -c "npm install && npm run build"

echo "=== migrate + catalogue (from committed bundles — no API calls needed) ==="
for i in $(seq 1 6); do docker compose exec -T app php artisan migrate --force && break; sleep 4; done
docker compose exec -T app php artisan catalog:import --fresh
docker compose exec -T app php artisan db:seed --class=EmbroiderySeeder --force
docker compose exec -T app php artisan db:seed --class=AccessorySeeder --force
docker compose exec -T app php artisan products:seo
docker compose exec -T app php artisan templates:import
docker compose exec -T app php artisan storage:link
docker compose exec -T app php artisan optimize:clear

echo "=== restart web ==="
docker compose -f docker-compose.yml -f docker-compose.prod.yml restart web

# Hero/category images aren't committed — generate once (products/logo ARE committed).
echo "=== generate hero+category images (background) ==="
nohup docker compose exec -T app php artisan images:generate --only=hero > /root/images.log 2>&1 &

echo "DEPLOY_DONE — point DNS/Cloudflare here, set APP_URL=https://<domain>, purge CF."
