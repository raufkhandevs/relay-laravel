# Relay backend

Laravel 13 API, Inertia web client and Reverb websocket server for Relay, a support desk.
Design documents and decision records live in the parent repository, `raufkhandevs/relay`, under `docs/`.

Two more clients consume this API: a React Native app and an Electron desktop console.
Changing a response shape changes three clients.

## Conventions specific to this repo

- API responses are `spatie/laravel-data` objects in `app/Data`, never raw arrays or Eloquent resources.
  They generate `resources/js/types/generated.d.ts`.
  Run `composer types` after changing one, or `GeneratedTypesTest` fails.
- `TicketPolicy` is the only place ticket authorisation is expressed.
  `routes/channels.php` calls it rather than repeating the rule.
  Never write the rule twice: a websocket channel is an authorisation surface, and an HTTP test will not catch a leak there.
- `tickets.last_message_at` is written by `MessageObserver` and by nothing else.
- Message history uses `cursorPaginate`, ticket lists use `paginate`.
  Offset paging is wrong for a live conversation because rows shift while you scroll.
- Writes a client may retry require an idempotency key, and the handler is atomic: it catches the unique violation and returns the original message rather than 500.
- Paginated responses have no `meta` wrapper.
  `next_cursor` and `total` are top level.
- Postgres, not SQLite, including under test.
  Never add a database key to `phpunit.xml` to work around a failure.
- `phpunit.xml` sets `BROADCAST_CONNECTION=reverb` deliberately.
  On `null`, channel authorisation is a no-op and the tests cannot detect a leak.

## Running it

See `.claude/skills/run-app`.

## Never

- Commit `.env` or `.env.testing`.
- Run `git push`. Rauf pushes, always. Commit locally and hand him the command.
- Hand edit `resources/js/types/generated.d.ts`.
- Add a phpstan baseline or `ignoreErrors` entry. Fix the type instead.
- Weaken an assertion or skip a test to get green.
