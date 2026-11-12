# Learning Log

## M0

OJS is pinned to `pkpofficial/ojs:3_5_0-6` (OJS 3.5.0.6) at digest
`sha256:0c26ece9e6af0c34961edfd2a187c5dc175ef5432b3d16d7c072de5d62d2c3f9`.
OJS 3.6.0.0 has no release, so I first tried building `pkp/ojs` main at
`e7ce0377994f84b67990d1eaf20d16c63c1bbf69` from source. The build took about
10 minutes and produced an approximately 6.2 GB image, so I switched the dev
environment to the official 3.5.0.6 image pending a 3.6 release.
The first source build also hit Git's dubious-ownership check because the
webroot existed as `www-data`; making the empty clone destination root-owned
before cloning fixed it.

PKP startup uses `sed -i` on the configured `PKP_CONF` path. Pointing it at a
webroot symlink would replace the symlink, so `PKP_CONF` points to the config
file in its named volume instead.

The image's webroot is sticky and world-writable. The config symlink must be
owned by `www-data` so Apache can follow it under Linux's protected-symlink
rules.

Docker Hub returned HTTP 429 for anonymous image pulls. Pulling the public
images from `mirror.gcr.io` allowed the local build to continue.

## M1

The Zenodo deposit endpoints, authentication, resource fields, and error
shape were checked against `https://developers.zenodo.org` on 2026-11-03.
Live-sandbox verification remains open for the upload PUT success status and
required content type, and whether a publish 202 response already contains
`doi` and `doi_url`. The docs show validation errors as 400; the mock follows
the requested 422 behavior. Mock links use the reserved `.invalid` host.

## M2

The mapper's fields and Zenodo rules were verified against the OJS 3.5.0.6
schemas and Zenodo's documentation and license vocabulary on 2026-11-05.
Omitting `license` lets Zenodo apply its own default; the article DOI is not
duplicated in `related_identifiers`; the article-to-dataset back-link is
deferred until the deposit step.

## M3

The first list-deposition test edit targeted an outdated method name; the test
was inserted at the existing method instead. The initial state and service
checks also caught a state-copy error that cleared `last_error`, PHPCS line
length issues, and PHPStan types that were too narrow for runtime-validated
file references; those were fixed before continuing.

The inherited OJS `BaseJob::$backoff = 5` property takes precedence over a
`backoff()` method when Laravel builds the queue payload. Unsetting the
inherited property in the job constructor allows Laravel to use the configured
schedule. The worker also retained a stale plugin-settings file cache after
the setting was edited from the web container. The settings adapter now reads
the context's settings from the database for each job; the queued-job behavior
after this change still needs end-to-end verification.

OJS's `job_runner` flushes output but, in this Apache mod_php image, cannot use
`fastcgi_finish_request()`. It still processes jobs in the same PHP request
process, so the development stack disables it and uses a separate worker.
`retry_after` is 610 seconds, meaning a killed worker's reserved job must age
out or be made available before another worker can resume it.

## M4

## M5

## M6

## M7
