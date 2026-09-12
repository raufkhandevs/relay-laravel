# Relay backend

Laravel 13 API, web client and websocket server for Relay, a support desk.
Customers raise tickets and chat with an agent. Agents work the queue.

This repo is one of four. The parent, [raufkhandevs/relay](https://github.com/raufkhandevs/relay),
holds the design and the decision records. A React Native app and an Electron desktop console
consume this API and are built next.

## What is here

One Laravel app with two front doors into the same domain logic.

- **Web**, at `/tickets`. Inertia and React 19, authenticated by session cookie. Customers read
  and reply here.
- **API**, under `/api`. JSON, authenticated by Sanctum bearer token. This is what the mobile and
  desktop clients will use.
- **Websockets**, over Laravel Reverb. Messages appear in every open client without a refresh.

Both front doors run through the same `TicketPolicy`, and so does websocket channel
authorisation. The rule is written once.

## Progress

```
API, auth, websockets     ████████████████████  done
Inertia web client        ████████████████████  done
Reply and attachments     ████████████████████  done
Typing and presence       ░░░░░░░░░░░░░░░░░░░░  next
Ticket creation           ░░░░░░░░░░░░░░░░░░░░
Push delivery to Apple    ░░░░░░░░░░░░░░░░░░░░  blocked
```

84 tests. Serves three clients: this web client, an iOS app and a desktop console.

**Blocked, not forgotten.** Push notifications are built server side and stubbed at the final hop,
because Apple will not issue APNs credentials to a free account. Decision 0007 spells out exactly
which leg is untested.

## Running it

Requires PHP 8.5, Node 22 and Docker.

```bash
docker compose up -d          # postgres
composer install && npm install
cp .env.example .env && php artisan key:generate
cp .env.testing.example .env.testing
php artisan migrate --seed
composer dev
```

`composer dev` runs four processes at once: the web server, the queue worker, Reverb and Vite.
The app is at http://localhost:8000.

Seeded accounts, both with password `password`:

| Email              | Role     | Sees                 |
| ------------------ | -------- | -------------------- |
| `priya@relay.test` | customer | her own tickets only |
| `agent@relay.test` | agent    | every ticket         |

Sign in as Priya, open the ticket, and post a message as the agent from another window or over the
API. It arrives without a reload.

## Tests

```bash
php artisan test        # Pest, against a Postgres test database
composer ci:check       # what CI runs: format, lint, types, tests
```

There is no end-to-end browser suite. This is a learning project and the live message path is
verified by hand.

## Things that will catch you out

- **Realtime silently does nothing if Reverb is not running.** It is one of the four processes in
  `composer dev`. Starting the app with `php artisan serve` alone gives you no websocket server.
- **`BROADCAST_CONNECTION` must be `reverb`.** On `null` or `log`, channel authorisation is a no-op
  and messages go nowhere.
- **The leading dot in `listen('.message.created')` is required.** Without it Echo expects a fully
  qualified PHP class name and matches nothing, with no error.
- **Postgres, not SQLite**, including under test. The app runs a web process, a queue worker and a
  websocket server concurrently, and SQLite's single writer model produces lock contention that no
  deployed Laravel app has.
- **`npm run check` formats YAML and Markdown too**, not just TypeScript. Hand written config files
  fail CI on formatting alone. `npm run check:fix` resolves it.

## Decisions

The reasoning behind the parts that look unusual lives in the parent repo under `docs/decisions`.
The ones worth reading before changing anything here: one backend rather than three, Sanctum with
two auth modes, the denormalised `last_message_at`, and why push is stubbed locally.

## Design

Palette, type and the status-edge device are shared with the other two clients and defined in
the parent repo's `docs/decisions/0009-one-design-system-two-densities.md`. The customer surfaces
run the system roomy; the agent console runs it compact.
