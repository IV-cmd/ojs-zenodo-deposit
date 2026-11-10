# Article-to-Zenodo mapping

`DepositMapper::map()` is a pure transformation from an OJS
`Submission`, `Publication`, `Context`, and caller-supplied options to
Zenodo article and optional dataset payloads. It does not access OJS
repositories, files, requests, settings, or services, and it performs no HTTP
requests. The caller resolves article URLs, keywords, communities, and file
references before calling the mapper.

The return value always contains an `article` payload and a `warnings` list.
It contains a `dataset` payload only when at least one supplied file is marked
as a dataset. Each payload has `metadata` for `ZenodoClient::createDeposition()`
and a `files` list of the supplied file references.

## Field mapping and missing-field policy

| OJS field or input | Zenodo field | Mapping and missing-field policy |
| --- | --- | --- |
| `Context.primaryLocale`, then `Publication.locale` | Locale selection | Read multilingual values with `getData()` and try the journal primary locale first, then the publication locale. |
| `Submission` | No direct metadata field | Accepted as part of the mapper signature; the mapper does not read its data or resolve its galleys/files. The caller supplies file references through options. |
| `Publication.title` | `title` | Strip HTML tags. If absent in both locales, throw `DepositMappingException`; the title is required. |
| `Publication.abstract` | `description` | Use the same locale fallback; strip HTML, decode HTML entities, collapse whitespace, and trim. If absent or empty, use the mapped title so the description is never empty. |
| `Publication.authors`, ordered by `Author.seq` | `creators` | Sort authors by sequence. No authors throws `DepositMappingException`. |
| `Author.familyName`, `Author.givenName` | `creators[].name` | Format as `Family, Given`; if family name is missing, use given name only. If both are missing in the available locales, throw `DepositMappingException`. |
| `Author.affiliations[].name` | `creators[].affiliation` | Use locale fallback for each affiliation name, join available names with `; `, and omit the field if none are available. |
| `Author.orcid` | `creators[].orcid` | Remove the `https://orcid.org/` prefix and include only a value matching the bare ORCID pattern. Omit invalid non-empty values and add a warning. |
| Constant | `upload_type`, `publication_type` | Article metadata uses `publication` and `article`, respectively. |
| `Publication.datePublished` | `publication_date` | Use the first 10 characters only when they form a valid `YYYY-MM-DD` date. Otherwise omit it and warn that Zenodo will default to today. |
| `Publication.doiObject.doi` | `doi` or `prereserve_doi` | If an article DOI exists, set `doi` and do not request a new one. If absent, set `prereserve_doi: true` and warn that Zenodo will mint a DOI; write-back is deferred to M4. |
| `Context.onlineIssn`, then `Context.printIssn` | `related_identifiers[]` | Add each non-empty ISSN as `{identifier, relation: isPartOf}`, in online-then-print order. Omit the field if there are no identifiers. |
| Passed-in `options.articleUrl` | `related_identifiers[]` | Add a non-empty URL as `{identifier, relation: isAlternateIdentifier}`. The caller constructs the URL; the mapper does not use a request or dispatcher. The article DOI is not duplicated here. |
| `Publication.licenseUrl` | `license` | Map recognized Creative Commons URLs to Zenodo vocabulary IDs. Supported IDs are `cc-by-4.0`, `cc-by-sa-4.0`, `cc-by-nd-4.0`, `cc-by-nc-4.0`, `cc-by-nc-sa-4.0`, `cc-by-nc-nd-4.0`, and `cc0-1.0`. URL matching accepts HTTP or HTTPS, optional `www.`, trailing slashes, and `legalcode` or `deed.xx` suffixes. Omit an unknown or missing license and warn that Zenodo applies its own default (`cc-by` for publications or `cc-zero` for datasets). |
| Passed-in `options.keywords` | `keywords` | Use the supplied strings only; publication keywords are not read. Omit the field when no non-empty strings are supplied. |
| Passed-in `options.communities` | `communities` | Convert each supplied identifier to `{identifier}`. Omit the field when no non-empty strings are supplied. |
| Constant | `access_right` | Always `open`. |
| Passed-in `options.files` | Payload `files` | The caller supplies references with `submissionFileId`, `name`, `path`, and `isDataset`; the mapper uses `isDataset` to select the payload and returns only the three file-reference fields. It does not resolve galleys or file genres. |

## Dataset payload

When `options.files` contains any reference with `isDataset: true`, the mapper
returns a separate dataset payload. It uses `upload_type: dataset`, omits
`publication_type`, and appends ` (dataset)` to the article title. Description,
creators, publication date, license, keywords, communities, and access right
follow the article mapping. The dataset always requests its own DOI with
`prereserve_doi: true`.

The dataset's `related_identifiers` contains an `isSupplementTo` relation to the
article DOI, or to `options.articleUrl` when the article has no DOI. If neither
is available, the related identifier is omitted and a warning is returned.
The reverse article-to-dataset link (`isSupplementedBy`) is deferred until the
deposit step (M3).

## Warnings

The mapper returns these warnings instead of logging or invoking OJS helpers:

- `Invalid ORCID omitted for creator "<creator name>".`
- `Publication date was omitted because datePublished is missing or invalid; Zenodo will default to today.`
- `Zenodo will mint a DOI; write-back is M4.`
- `License is missing or unrecognized; Zenodo will apply its own default (cc-by for publications or cc-zero for datasets).`
- `Dataset not linked to article; link after article deposit (M3).`

Missing title, no authors, or an author with no available name are mapping
errors and throw `DepositMappingException`, rather than producing a warning.

## Not mapped yet

- Embargoes
- Funding and grants
- Contributors
- Journal title, volume, issue, and pages
- References
- Keywords stored on the OJS publication
- The reverse article-to-dataset back-link
- Subjects
