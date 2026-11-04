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

## M3

## M4

## M5

## M6

## M7
