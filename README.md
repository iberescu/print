# RunMyPrint

A web-to-print e-commerce platform (Vistaprint-style) — Laravel + Inertia/Vue 3 + Tailwind, with an online fabric.js designer, AI-generated imagery & templates (Gemini), Stripe checkout, and product feeds for Google Shopping / RTB House.

> Status: **early build**. See [`ROADMAP.md`](./ROADMAP.md) for what's done vs. pending.

## Stack
| Layer | Tech |
|---|---|
| Backend | Laravel (PHP 8.3) |
| Frontend | Inertia.js + Vue 3 + Tailwind CSS + Vite |
| Designer | fabric.js |
| DB / cache / queue | MySQL 8 · Redis 7 |
| Payments | Stripe |
| AI | Google Gemini (image gen + text + vision scoring) |
| Crawler | Node + Playwright |
| Deploy | Docker → DigitalOcean |

## Local development (Docker — nothing else needed on host except Docker + Node)

```powershell
# 1. Build & start backend services (app, nginx, mysql, redis)
docker compose up -d --build

# 2. First-time Laravel setup (run inside the app container)
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# 3. Start the Vite dev server (frontend HMR)
docker compose --profile dev up node
```

App: http://localhost:8080  ·  Vite: http://localhost:5173

## Layout
```
backend/    Laravel app (API + Inertia/Vue frontend)
crawler/    Playwright crawler for competitive research (products, prices)
research/   Screenshots + analysis captured from reference sites
docker/     Dockerfiles + nginx config
```

## Secrets
All secrets live in `backend/.env` (gitignored). See `.env.example` for the required keys
(Gemini, Stripe, DigitalOcean). **Never commit real keys.**
