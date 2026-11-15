# Configuration

All settings are per-journal (context-scoped). A journal manager opens them via
**Settings → Website → Plugins → Generic → Zenodo Deposit → Settings**. Only
journal managers and site admins can view or save the form; OJS gates the
`manage()` route itself.

## Settings

| Field | Stored setting | Default | Effect |
| --- | --- | --- | --- |
| Enable Zenodo deposits for this journal | `zenodo_enabled` | Off | Master switch. When off, the publish hook returns immediately and nothing is queued. |
| Use Zenodo sandbox | `zenodo_sandbox` | On | Selects the deposit endpoint. On save the plugin derives `zenodo_mode` and `zenodo_base_url` from this toggle: `sandbox`/`https://sandbox.zenodo.org` when on, `live`/`https://zenodo.org` when off. |
| Deposit attached datasets as a linked Zenodo record | `zenodo_deposit_datasets` | On | When a submission has dataset files, a second `upload_type: dataset` deposition is created and cross-linked. |
| Zenodo API token | `zenodo_token` | Empty | Write-only. See "Token handling". |

The form also shows the sandbox warning when **Enable** and **Use sandbox** are
both on, so a misconfigured journal is visible instead of silently depositing
test records.

## Token handling

Create a token in your Zenodo account under
**Applications → Personal access tokens** with the `deposit:write` and
`deposit:actions` scopes. Sandbox tokens come from
[sandbox.zenodo.org](https://sandbox.zenodo.org/account/settings/applications/tokens/new/)
and are separate from production tokens.

- The token field is a password input that is never populated: the form shows
  "API token: configured / not configured" instead of the value.
- Saving with an empty token field keeps the stored token; saving a new value
  replaces it.
- The token never appears in rendered HTML, log output, or API responses. The
  `zenodoDeposit` API property carries deposit status only — no credentials.
- `enabled` requires a token (stored or newly entered): validation fails with
  "An API token is required when deposits are enabled."

## Deposit status for editors

Every publication's article page carries a **Zenodo deposit** block showing the
current state (`pending`, `depositing`, `deposited`, `failed`) and, once
deposited, the Zenodo DOI as a link to the record on the sandbox or production
host according to the sandbox toggle. Journal managers also see the last error
and, on `failed`, a **Retry deposit** link.

The same state is exposed on the publication API as the `zenodoDeposit`
property, mirrored from the `zenodo_deposits` table, which remains the source
of truth.

Retry re-queues the same M3 job through the idempotent path: a stored
deposition ID is reused, so retrying never creates a second deposition.

## Development-only settings

`zenodo_mode` may also be set to `mock` directly in `plugin_settings` for the
local stack (see `docs/deposit-flow.md`), together with `zenodo_mock_scenario`
and `zenodo_mock_step_delay`. Saving the settings form overwrites `zenodo_mode`
with the value derived from the sandbox toggle — re-set it to `mock` in the
database after using the form in a mock environment.
