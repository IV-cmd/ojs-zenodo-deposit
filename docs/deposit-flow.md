# Deposit flow

M3 connects an OJS publication to the Zenodo client through a database-backed
queue. The sequence is **publish → enqueue → create → upload → publish →
persist**. The publish hook never makes a Zenodo request: it ensures a pending
state row, dispatches a job, catches every `Throwable`, and returns without
blocking or failing publication.

## Execution

The plugin registers `Publication::publish` when OJS loads it as an enabled
generic plugin. The hook reads and validates the context's code-level settings,
creates or preserves the `zenodo_deposits` row, and queues a `DepositJob` with
the publication and context IDs. The job reloads the publication, submission,
context, metadata and files, maps the payload, and runs the pure
`DepositService`.

The development Compose stack has a separate worker:

```sh
php lib/pkp/tools/jobs.php work --sleep=3
```

The worker points its OJS bootstrap at the shared configuration volume before
starting the command.

The OJS startup wrapper sets `[queues] job_runner = Off` in the persistent
configuration file, so the web request only enqueues. The setting is applied
idempotently on each OJS start. In production, run a worker daemon or a
scheduler/cron worker rather than processing deposits in a web request.

## State and checkpoints

`zenodo_deposits` has one row per publication. It stores the submission and
context IDs, status, article and dataset deposition IDs and DOIs, attempt
count, redacted/truncated last error, and timestamps. The state machine is:

```text
pending → creating → depositing → deposited
    └─────────────── failures ─────────────→ failed
```

The service persists `creating` before requesting a deposition. Once Zenodo
returns an ID, it immediately saves that ID and `depositing` before retrieving,
uploading, or publishing. A retry resumes from stored IDs, skips files whose
filenames already appear in the deposition, and does not publish a deposition
that Zenodo already reports as submitted/done with a DOI. The article is
completed before a dataset deposition is created.

If a worker dies after Zenodo created a draft but before the ID was persisted,
the next attempt searches up to 100 recent drafts and matches the article's
`isAlternateIdentifier` marker (the article URL). Dataset recovery uses its
`isSupplementTo` marker. A missing marker is a permanent manual-review error;
the service does not create blindly. When a marker is present but no draft
matches it, the service proceeds with a new create after completing the search.
The persisted ID makes later attempts resume without another create.

`deposition_id`, `dataset_deposition_id`, `doi`, and `dataset_doi` are stored
in the plugin table only. Writing the DOI into OJS's DOI object is deferred to
M4. The article-to-dataset reverse backlink is also deferred.

## Retry and failure behavior

The job allows five attempts with a 120-second timeout and uses this backoff:
30, 60, 120, and 240 seconds. `ZenodoApiException` statuses 0 (transport
failure), 429, and 500 or higher are transient and are rethrown for Laravel to
retry. Other errors—including other HTTP 4xx responses, mapping errors, and
configuration errors—are permanent: the state becomes `failed` and the job is
failed without retry. After the final transient attempt, Laravel's `failed`
callback marks the state failed.

Each job attempt increments the stored attempt count. `last_error` contains
only a redacted message and is truncated to 2,000 characters. Logs include
publication, attempt, and known deposition IDs, never the Zenodo token.
Publication itself remains published if enqueueing or a later deposit fails.

The queue's database connection uses `jobs` and `failed_jobs`. OJS hard-codes
`retry_after=610` seconds. If a worker is killed while holding a reserved job,
the job is not eligible for another worker until the reservation becomes
stale—normally after 610 seconds—or an operator adjusts its reservation in
`jobs`. Do not set a worker timeout longer than `retry_after`.

The built-in OJS job runner is disabled in this development stack. OJS's
`job_runner` flushes the response before processing jobs, but the pinned Apache
image uses mod_php and has no `fastcgi_finish_request()`. The job would still
run in the same PHP request process after output is flushed, so a slow deposit
could tie up a web worker. Use a separate worker in production.

## Configuration and mock scenarios

There is no settings form yet; settings are read per OJS context. The defaults
are `zenodo_mode=mock`, `zenodo_base_url=https://sandbox.zenodo.org`, and an
empty `zenodo_token`. Mock-only development controls are
`zenodo_mock_scenario` (`success`, `outage`, or `validation_error`) and
`zenodo_mock_step_delay` (non-negative seconds before each mock operation).
Optional `zenodo_keywords` and `zenodo_communities` values are passed to the
mapper. M3 end-to-end checks use mock mode only; no live Zenodo calls are
needed.

## E2E verification status

Verified against the dev OJS 3.5.0.6 stack on 2026-11-12 (mock mode only; no
live Zenodo calls). Local evidence is kept under `~/notes/m3-evidence/` and is
not committed.

- Publish while Zenodo is down (scenario `outage`): the publish request
  returned HTTP 200 in 92–207 ms and the article stayed published; the worker
  logged attempts 1–4 spaced by the 30/60/120 s backoff, and the
  `zenodo_deposits` rows recorded `failed` after 5 attempts
  (`01-outage-*.log/txt`, `01-publish-timing.md`).
- Successful deposit (scenario `success`): two publications reached `deposited`
  with `deposition_id=12345` and `doi=10.5072/zenodo.12345`
  (`02-success-db.txt`, worker logs).
- Worker kill mid-job: a 20 s step delay was set and the worker was `kill -9`ed
  after the "created article deposition id=12345" log line. State was
  `depositing`/`deposition_id=12345`. Because a killed job's reservation only
  expires after `retry_after` (610 s, hardcoded in `PKPContainer`), the test
  moved `jobs.reserved_at`/`available_at` back by hand and restarted the
  worker. The job resumed on attempt 2 using the stored id — exactly one
  `created` log line across both runs — and reached `deposited`
  (`03-kill-*.log/txt`). In production the same recovery happens automatically,
  just up to 610 s later.
- Permanent failure (scenario `validation_error`): the publish returned 200,
  one attempt ran, `zenodo_deposits` shows `failed`/`attempts=1`, the job
  landed in `failed_jobs`, and no retry was observed after 60 s
  (`04-validation-*.log/txt`).

Bug found and fixed during verification: worker processes read stale plugin
settings through the DAO cache; the settings adapter now reloads context
settings per job.

## Known limits

- The development/runtime target is OJS 3.5.0.6; compatibility with OJS 3.6
  must be checked when that release is available.
- The OJS adapter includes published galleys as article files and dependent
  submission files as dataset files. Dataset genre selection is not configurable
  yet.
- OJS DOI-object updates, the article-to-dataset reverse link, settings UI,
  and human-facing deposit status UI are deferred.
- Zenodo sandbox behavior for upload response status/content type and DOI
  availability in the publish response is not live-verified. The client
  handles a publish response without a DOI by retrieving the deposition and
  retrying transiently if the DOI is still absent.
