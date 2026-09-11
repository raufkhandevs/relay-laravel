---
name: run-app
description: Start the Relay Laravel backend with Postgres, the queue worker, Reverb and Vite, and diagnose why realtime is not working locally. Use whenever this app needs running, serving or restarting, when chat messages are not arriving, or when a test suite cannot reach the database.
---

# Running the Relay backend

Docker is not started automatically. Bring it up first, wait for health, then start the app.

```bash
docker compose up -d
until [ "$(docker inspect -f '{{.State.Health.Status}}' relay-postgres)" = "healthy" ]; do sleep 1; done
composer dev
```

`composer dev` runs four processes at once: `serve`, `queue:work`, `reverb:start` and `vite`.
The app is at http://localhost:8000.

Seeded accounts, both with password `password`:

- `agent@relay.test`, an agent, sees every ticket
- `priya@relay.test`, a customer, sees only her own

Reset the database with `php artisan migrate:fresh --seed`.

## When realtime is not working

Check these four before reading any code. Almost every local realtime failure is one of them.

1. **Is the Reverb process actually running.** It is one of the four in `composer dev`. If you started the app with `php artisan serve` alone, there is no websocket server at all.
2. **Is `BROADCAST_CONNECTION=reverb`.** On the `null` or `log` driver, channel authorisation is a complete no-op and messages go nowhere. This is also set in `phpunit.xml` for tests, deliberately: on `null` the channel authorisation tests cannot detect a leak.
3. **Does the browser console show a failed `POST /api/broadcasting/auth`.** That endpoint is channel authorisation, and `TicketPolicy` decides it. A 403 there means the policy refused, which may be correct.
4. **Does the client `listen()` call start with a dot**: `.message.created`, not `message.created`. Without the leading dot, Echo expects a fully qualified PHP class name and silently matches nothing. No error, no message, nothing in the console.

## Tests

```bash
php artisan test                                    # Pest, against the relay_test Postgres database
./vendor/bin/pint --test                            # formatting
./vendor/bin/phpstan analyse --memory-limit=1G      # the default 128M crashes
npm run check                                       # Vite Plus: formats and lints TS, YAML, JSON, MD
npm run types:check                                 # tsc --noEmit
npm run test:e2e                                    # Playwright, needs composer dev already running
```

`composer ci:check` runs what CI runs. Use it before pushing.

## Things that will catch you out

- `npm run check` formats YAML and Markdown, not just TypeScript. Hand written `docker-compose.yml` or workflow files fail CI on formatting alone. `npm run check:fix` resolves it.
- `.env` and `.env.testing` are gitignored. A fresh clone copies `.env.example` and `.env.testing.example`.
- Larastan needs `--memory-limit=1G`. At the default it dies partway with an unhelpful error.
