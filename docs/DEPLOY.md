# LexLink — deployment

One VPS (Ubuntu 24.04), Docker Compose, behind Cloudflare
(`https://hackathon.john.uz`, orange cloud).

## Ish tartibi (CI/CD)

`main` himoyalangan: unga to'g'ridan-to'g'ri push qilib bo'lmaydi (adminlar uchun ham), force-push va o'chirish taqiqlangan.

1. `git switch -c feature/<nom>` — o'zgarishlar feature branch'da.
2. `git push -u origin feature/<nom>` → GitHub'da `main` ga **pull request**.
3. PR'da **Tests** ishga tushadi (Pint, `tsc`, build, PHPUnit). Yashil bo'lmaguncha va branch `main` bilan
   yangilanmaguncha merge tugmasi yopiq; barcha izohlar hal qilingan bo'lishi kerak.
4. Merge → `main` ga push → **CI/CD** qayta testlaydi va **Deploy to production** bosqichi aynan shu commit'ni
   serverga chiqaradi, so'ng `https://hackathon.john.uz/up` ni tekshiradi. Branch avtomatik o'chiriladi.

Deploy kaliti (GitHub `production` environment secret'lari, faqat `main` foydalana oladi) serverda faqat
`/opt/lexlink/bin/ci-deploy` ni ishga tushira oladi (`authorized_keys` forced command; shell, port-forwarding yo'q).
Server jurnali: `/opt/lexlink/deploy.log`. Server manzili repoda saqlanmaydi.

## Qo'lda amallar (TL;DR)

`deploy.sh` faqat maxsus holatlar uchun (masalan demo ma'lumotlarni qayta seed qilish). Server manzili va kalit
yo'li git'ga tushmaydigan `.deploy.env` faylida: `LEXLINK_HOST=user@host`, `LEXLINK_SSH_KEY=~/.ssh/<kalit>`.

```bash
./deploy.sh                # pull origin/main on server, build, migrate, restart, health-check
./deploy.sh --fresh-seed   # same + migrate:fresh --seed  (DESTRUCTIVE — asks you to type "wipe")
./deploy.sh --no-build     # only restart/migrate (e.g. after editing /opt/lexlink/.env)
./deploy.sh --logs         # tail logs afterwards
```

`deploy.sh` deploys **what is pushed to GitHub `main`**, not your working tree. Commit + push first.
Requires `.deploy.env` (see above); `LEXLINK_BRANCH` overrides the branch.
`LEXLINK_YES=1 ./deploy.sh --fresh-seed` skips the confirmation prompt.

## Layout on the server

| Path | What |
|---|---|
| `/opt/lexlink/src` | git checkout (read-only GitHub deploy key `~/.ssh/lexlink_deploy`, name `vps-hackathon-readonly`) |
| `/opt/lexlink/.env` | production env (root:33, chmod 640 — gid 33 = www-data in the containers; NOT in git). Created once from `.env.production.example` with random secrets |
| `/opt/lexlink/certs/origin.{crt,key}` | self-signed origin cert (CN/SAN hackathon.john.uz + IP), 10 years |

## Services (`docker-compose.prod.yml`, project `lexlink`)

| Service | Image | Role |
|---|---|---|
| `nginx` | `lexlink-nginx` (nginx 1.27 + `public/` baked in) | :80 and :443 (self-signed). Static files, FastCGI to `app:9000`, `/app/*` + `/apps/*` proxied to `reverb:8080` (WebSocket) |
| `app` | `lexlink-app` (PHP 8.4-fpm) | php-fpm |
| `horizon` | `lexlink-app` | `php artisan horizon` (queues on redis) |
| `reverb` | `lexlink-app` | `php artisan reverb:start --host=0.0.0.0 --port=8080` |
| `scheduler` | `lexlink-app` | `php artisan schedule:work` |
| `mysql` | `mysql:8.4` | volume `lexlink_mysql_data`, no host port |
| `redis` | `redis:7-alpine` | AOF on, volume `lexlink_redis_data`, no host port |

Volumes for app data: `storage_public` (served at `/storage`), `storage_private` (`storage/app/private`),
`storage_logs`. Everything else under `storage/` (incl. `storage/app/lex`) comes from the image and is
refreshed on every deploy.

Image build (`Dockerfile`): `base` (php-fpm + pdo_mysql redis gd intl bcmath pcntl zip opcache) →
`vendor` (`composer install --no-dev` + Faker added so seeders/factories work) → `assets` (php + Node 22,
`npm ci && npm run build`; the Wayfinder Vite plugin runs `php artisan wayfinder:generate` itself) →
`app` → `nginx`.
`VITE_REVERB_*` / `VITE_APP_NAME` are **build args** (baked into JS) taken from `/opt/lexlink/.env` —
changing them requires a rebuild (plain `./deploy.sh`).

Each PHP container's entrypoint runs `php artisan optimize` (config/route/view/event cache) then drops to
`www-data`. Release step in `deploy.sh`: `migrate --force` in a one-off container before the services
are recreated. `public/storage` symlink is baked into the image.

## Networking / Cloudflare

- Browser → Cloudflare (TLS) → origin. Origin answers on **both** :80 and :443, with no HTTP→HTTPS
  redirect, so Cloudflare SSL mode **Flexible** or **Full** both work. **Full (strict)** will NOT work
  (self-signed cert) unless you replace `/opt/lexlink/certs/origin.*` with a Cloudflare Origin CA cert.
  Recommended: **Full** + "Always Use HTTPS" on in Cloudflare.
- Laravel trusts all proxies (`bootstrap/app.php`), `APP_URL=https://hackathon.john.uz`, and
  `URL::forceScheme('https')` in production (`AppServiceProvider`).
- nginx restores the real client IP from `CF-Connecting-IP`.
- WebSockets: browser connects `wss://hackathon.john.uz:443/app/{REVERB_APP_KEY}`; Cloudflare passes
  WS on 443. Laravel → Reverb broadcasting goes over the docker network (`REVERB_HOST=reverb`,
  `REVERB_PORT=8080`, `REVERB_SCHEME=http`).
- Mock gov APIs are called internally: `MOCK_GOV_BASE_URL=http://nginx/mock-gov/v1`.
- ufw: 22 (limit), 80, 443. Note Docker publishes ports bypassing ufw anyway; only nginx publishes ports.

## Common ops (on the server)

```bash
cd /opt/lexlink/src
alias dc='docker compose -p lexlink -f docker-compose.prod.yml --env-file /opt/lexlink/.env'
dc ps
dc logs -f --tail=100 app horizon reverb
dc exec app php artisan tinker
dc exec app php artisan horizon:status
dc exec mysql sh -c 'mysql -ulexlink -p"$MYSQL_PASSWORD" lexlink'
dc exec redis redis-cli
```

Editing secrets (e.g. `GEMINI_API_KEY`): edit `/opt/lexlink/.env`, then `./deploy.sh --no-build`
(containers are force-recreated so config is re-cached). If `../creds.txt` (next to the repo locally)
contains a `GEMINI_API_KEY=` line and the server value is empty, `deploy.sh` fills it in (never printed).

## Gotchas

- `DB::prohibitDestructiveCommands()` is on in production, so `--fresh-seed` runs its one-off container
  with `APP_ENV=demo`. Don't run `migrate:fresh` by hand in a normal container — it will refuse.
- `php artisan optimize` caches config — `env()` calls outside `config/*.php` return null in production.
- Change a `VITE_*` value → rebuild needed (it's in the JS bundle), not just a restart.
- Direct `http://<VPS_IP>/` works for `/up`, but pages generate https URLs (forced scheme), so use
  the domain in the browser.
- Horizon dashboard (`/horizon`) is gated by `HorizonServiceProvider::gate()` in production.
- Logs: `storage/logs/laravel.log` (volume `storage_logs`) and container stderr (`dc logs app`).
