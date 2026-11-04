# OJS Zenodo Deposit

An OJS generic plugin skeleton for Zenodo integration.

**Status: early development**

[![CI](https://github.com/IV-cmd/ojs-zenodo-deposit/actions/workflows/ci.yml/badge.svg)](https://github.com/IV-cmd/ojs-zenodo-deposit/actions/workflows/ci.yml)

This version registers an installable generic plugin and provides localized
metadata. It does not deposit content to Zenodo.

## OJS compatibility

The development environment uses the official
[`pkpofficial/ojs:3_5_0-6`](https://hub.docker.com/r/pkpofficial/ojs) image,
pinned to digest
`sha256:0c26ece9e6af0c34961edfd2a187c5dc175ef5432b3d16d7c072de5d62d2c3f9`.
It contains OJS 3.5.0.6 and PHP 8.3.

The eventual target is OJS 3.6. At the time of writing, OJS 3.6.0.0 has no
release (no git tag, tarball, or official image), though `pkp/ojs` main reports
version 3.6.0.0. The plugin is developed and tested against OJS 3.5.0.6 and
will be re-checked on OJS 3.6 once it is released.

## Development environment

Copy `.env.example` to `.env` to customize the local database passwords. The
example values are development-only defaults and must not be used for a
deployed environment.

```sh
cp .env.example .env
make up
```

The first run opens the OJS web installer at
[http://localhost:8080](http://localhost:8080). Select MySQLi as the database
driver and use `db` as the database host, database name `ojs`, user `ojs`, and
the password from `.env` (or the development default). The configured files
directory is `/var/www/files`. Complete the installer to create an
administrator. Then create a journal via **Administration → Hosted Journals**
and enable **Zenodo Deposit** via **Settings → Website → Plugins → Generic**.

The OJS configuration persists in a named volume across `make down`. Use
`docker compose down -v` to reset the configuration, files, public files, and
database volumes.

To register the plugin's version metadata after installing OJS:

```sh
make install-plugin
```

This runs `php lib/pkp/tools/installPluginVersion.php
plugins/generic/zenodoDeposit/version.xml` from the OJS root. The repository is
mounted at `plugins/generic/zenodoDeposit` in the OJS container.

Use `make logs`, `make ojs-cli`, and `make down` to inspect and manage the
development stack.

## Tests and static checks

The unit tests run without an OJS checkout. Test-only stand-ins for the PKP
plugin base class and translation helper are loaded by the test bootstrap.
They verify the skeleton's registration, metadata, loader convention, and
English locale keys. Checks requiring the OJS application bootstrap are
deferred to later work.

Run the checks locally with PHP 8.2 or newer and Composer:

```sh
composer install
make test
make lint
make analyse
```

GitHub Actions runs the same checks on PHP 8.2 and 8.3.

## Plugin compatibility metadata

OJS plugin `version.xml` does not define an OJS-compatibility field.
Compatibility information belongs in the plugin gallery's `plugins.xml`, not
in this plugin's version metadata.

## Authorship

Code written by Devin (AI coding agent) to my specifications and reviewed by me.
