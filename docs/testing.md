# Testing

Two suites, two different jobs:

- **PHPUnit** — unit tests against a mocked Zenodo API. No OJS checkout needed.
- **Cypress** — end-to-end against the real OJS dev stack (`docker-compose.yml`),
  with the plugin in mock mode. Only covers what PHPUnit cannot: rendered HTML,
  the settings form, permissions, and the full publish → queue → badge path.

## PHPUnit

```sh
composer install
make test      # phpunit
make lint      # phpcs
make analyse   # phpstan
```

Coverage: `ZenodoClient` (every method, error mapping, status-0 transport
failure, token redaction), `DepositMapper` (mapping rules + boundary test),
`DepositService` (happy path, idempotent resume, crash-before/after-ID
checkpoint, 4xx permanent, dedup, dataset, manual-review, retry-from-failed),
`DepositJob` (tries/backoff, `failed()` state write, error redaction),
`PublishHookHandler` (enqueue-only, dispatch failure never blocks publish),
`DepositSettings`/`SettingsValidator`/`ZenodoDepositSettingsForm` (round-trip,
validation, token secrecy).

## Cypress

```sh
./cypress/setup-env.sh          # boots compose stack; installs OJS on a fresh volume
cd cypress && npm ci
npx cypress run --browser electron
```

`setup-env.sh` is idempotent: on a fresh database it runs the CLI installer,
then creates the journal via the contexts API (with an admin `apiKey` JWT sent
as `?apiToken=` — Apache strips the `Authorization` header under mod_php),
enrolls the admin as journal manager, and writes the plugin's `versions` /
`plugin_settings` rows. The seeded journal is `zdj` at
`http://localhost:8080/index.php/zdj`, admin/`adminadmin`.

Specs:

- `publish-deposit.cy.js` — publish via REST API → job queued → `deposited` →
  badge + `zenodoDeposit` API prop show the sandbox DOI.
- `zenodo-down.cy.js` — `zenodo_mock_scenario=outage` → publish still returns
  200 (outage delays, never blocks) → state `failed` after 5 attempts → mock
  restored → article-page retry link → `deposited`. Backoff waiting is skipped
  by expiring the job reservation, not by changing retry logic.
- `settings.cy.js` — anonymous is denied the manage route; a manager saves
  settings and the token is write-only (DB has it, rendered form shows
  "configured" and never the value).

Each spec seeds its own state via `docker compose exec db` in `before()`
(plugin settings, `zenodo_deposits` rows). Submissions/issues are created
through the API or SQL — deterministic, no UI clicks where they aren't needed.

## Seeded-state assumptions

Journal `zdj` (context 1), admin/`adminadmin`, default user groups (manager 2,
author 14), section 1, an issue created on demand by `cy.ensureIssue()`.

## Known gap

No test does a real round-trip to `sandbox.zenodo.org` — that needs a live
token and network. Everything else is fully exercised.
