#!/bin/bash
# RunMyPrint routine deploy (run ON the droplet): pull main, rebuild the frontend,
# apply migrations, refresh the catalogue from the committed bundles, restart.
# Purge the Cloudflare cache AFTER this finishes (fixed-URL assets change).
#
#   ssh root@<droplet> 'bash /root/print/deploy/light-deploy.sh'          # code-only
#   ssh root@<droplet> 'FRESH=1 bash /root/print/deploy/light-deploy.sh'  # rebuild catalogue
set -euo pipefail
cd /root/print

echo "=== pull ==="
git fetch --depth 1 origin main
git reset --hard origin/main
git log --oneline -1

echo "=== app image (picks up Dockerfile changes, cached otherwise) ==="
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build app

echo "=== frontend build (old hashed assets are kept) ==="
docker run --rm -v /root/print/backend:/app -w /app node:22-alpine \
    sh -c "npm install --no-audit --no-fund && npm run build" 2>&1 | tail -2

echo "=== migrate ==="
docker compose exec -T app php artisan migrate --force < /dev/null

if [ "${FRESH:-0}" = "1" ]; then
    echo "=== catalogue: fresh import from committed bundles ==="
    docker compose exec -T app php artisan catalog:import --fresh < /dev/null
    docker compose exec -T app php artisan db:seed --class=EmbroiderySeeder --force < /dev/null
    docker compose exec -T app php artisan db:seed --class=AccessorySeeder --force < /dev/null
    docker compose exec -T app php artisan products:seo < /dev/null
    docker compose exec -T app php artisan templates:import < /dev/null
fi

echo "=== clear + restart ==="
docker compose exec -T app php artisan optimize:clear < /dev/null
docker compose -f docker-compose.yml -f docker-compose.prod.yml restart web

echo "DEPLOY_DONE — now purge the Cloudflare cache."
