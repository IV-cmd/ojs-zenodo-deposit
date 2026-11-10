<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use APP\publication\Publication;
use APP\submission\Submission;
use PKP\author\Author;
use PKP\context\Context;

final class DepositMapper
{
    /**
     * @param array<string, mixed> $options
     * @return array{
     *     article: array{metadata: array<string, mixed>, files: array<mixed>},
     *     warnings: list<string>
     * }
     */
    public function map(
        Submission $submission,
        Publication $publication,
        Context $journal,
        array $options = []
    ): array {
        $primaryLocale = $journal->getData('primaryLocale');
        $publicationLocale = $publication->getData('locale');
        $primaryLocale = is_string($primaryLocale) ? $primaryLocale : '';
        $publicationLocale = is_string($publicationLocale) ? $publicationLocale : '';

        $title = $this->localizedValue(
            $publication->getData('title'),
            $primaryLocale,
            $publicationLocale
        );
        $title = $title === null ? null : trim(strip_tags($title));

        if ($title === null || $title === '') {
            throw new DepositMappingException('The article has no title in the available locales.');
        }

        $abstract = $this->localizedValue(
            $publication->getData('abstract'),
            $primaryLocale,
            $publicationLocale
        );
        $description = $this->description($abstract, $title);
        $warnings = [];
        $creators = $this->creators(
            $publication,
            $primaryLocale,
            $publicationLocale,
            $warnings
        );
        $metadata = [
            'upload_type' => 'publication',
            'publication_type' => 'article',
            'title' => $title,
            'description' => $description,
            'creators' => $creators,
        ];

        $publicationDate = $this->publicationDate($publication->getData('datePublished'));

        if ($publicationDate !== null) {
            $metadata['publication_date'] = $publicationDate;
        } else {
            $warnings[] = 'Publication date was omitted because datePublished is missing or invalid; '
                . 'Zenodo will default to today.';
        }

        $doi = $this->doi($publication);

        if ($doi === null) {
            $metadata['prereserve_doi'] = true;
            $warnings[] = 'Zenodo will mint a DOI; write-back is M4.';
        } else {
            $metadata['doi'] = $doi;
        }

        $relatedIdentifiers = $this->articleRelatedIdentifiers($journal, $options);

        if ($relatedIdentifiers !== []) {
            $metadata['related_identifiers'] = $relatedIdentifiers;
        }

        $license = LicenseMap::toZenodoId($this->stringData($publication, 'licenseUrl'));

        if ($license !== null) {
            $metadata['license'] = $license;
        } else {
            $warnings[] = 'License is missing or unrecognized; Zenodo will apply its own default '
                . '(cc-by for publications or cc-zero for datasets).';
        }

        $keywords = $this->stringOptions($options, 'keywords');

        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        $communities = $this->communities($options);

        if ($communities !== []) {
            $metadata['communities'] = $communities;
        }

        $metadata['access_right'] = 'open';

        return [
            'article' => [
                'metadata' => $metadata,
                'files' => $this->files($options),
            ],
            'warnings' => $warnings,
        ];
    }

    private function localizedValue(mixed $value, string $primaryLocale, string $fallbackLocale): ?string
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : trim($value);
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (array_unique([$primaryLocale, $fallbackLocale]) as $locale) {
            $localized = $value[$locale] ?? null;

            if (is_string($localized) && trim($localized) !== '') {
                return trim($localized);
            }
        }

        return null;
    }

    private function description(?string $abstract, string $title): string
    {
        if ($abstract === null) {
            return $title;
        }

        $description = html_entity_decode(
            strip_tags($abstract),
            ENT_QUOTES | ENT_HTML5
        );
        $description = preg_replace('/\s+/u', ' ', $description);
        $description = trim($description ?? '');

        return $description === '' ? $title : $description;
    }

    /**
     * @return list<array{name: string, affiliation?: string, orcid?: string}>
     * @param list<string> $warnings
     */
    private function creators(
        Publication $publication,
        string $primaryLocale,
        string $publicationLocale,
        array &$warnings
    ): array {
        $authors = $publication->getData('authors');
        $validAuthors = [];

        if (is_iterable($authors)) {
            foreach ($authors as $author) {
                if ($author instanceof Author) {
                    $validAuthors[] = $author;
                }
            }
        }

        usort($validAuthors, static function (Author $left, Author $right): int {
            $leftSequence = $left->getData('seq');
            $rightSequence = $right->getData('seq');
            $leftSequence = is_numeric($leftSequence) ? (float) $leftSequence : 0.0;
            $rightSequence = is_numeric($rightSequence) ? (float) $rightSequence : 0.0;

            return $leftSequence <=> $rightSequence;
        });

        $creators = [];

        foreach ($validAuthors as $author) {
            $givenName = $this->localizedValue(
                $author->getData('givenName'),
                $primaryLocale,
                $publicationLocale
            );
            $familyName = $this->localizedValue(
                $author->getData('familyName'),
                $primaryLocale,
                $publicationLocale
            );
            $name = $this->creatorName($givenName, $familyName);

            if ($name === null) {
                throw new DepositMappingException('An article author has no name in the available locales.');
            }

            $creator = ['name' => $name];
            $affiliation = $this->affiliation($author, $primaryLocale, $publicationLocale);

            if ($affiliation !== null) {
                $creator['affiliation'] = $affiliation;
            }

            $rawOrcid = $author->getData('orcid');
            $orcid = $this->orcid($rawOrcid);

            if ($orcid !== null) {
                $creator['orcid'] = $orcid;
            } elseif (is_string($rawOrcid) && trim($rawOrcid) !== '') {
                $warnings[] = 'Invalid ORCID omitted for creator "' . $name . '".';
            }

            $creators[] = $creator;
        }

        if ($creators === []) {
            throw new DepositMappingException('The article has no authors to map as Zenodo creators.');
        }

        return $creators;
    }

    private function creatorName(?string $givenName, ?string $familyName): ?string
    {
        if ($familyName !== null && $givenName !== null) {
            return $familyName . ', ' . $givenName;
        }

        return $familyName ?? $givenName;
    }

    private function affiliation(Author $author, string $primaryLocale, string $publicationLocale): ?string
    {
        $affiliations = $author->getData('affiliations');

        if (!is_iterable($affiliations)) {
            return null;
        }

        $names = [];

        foreach ($affiliations as $affiliation) {
            if (!is_object($affiliation) || !method_exists($affiliation, 'getData')) {
                continue;
            }

            $name = $this->localizedValue(
                $affiliation->getData('name'),
                $primaryLocale,
                $publicationLocale
            );

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names === [] ? null : implode('; ', $names);
    }

    private function orcid(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $orcid = preg_replace('~^https?://orcid\.org/~i', '', trim($value));
        $orcid = rtrim($orcid ?? '', '/');

        return preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/i', $orcid) === 1
            ? $orcid
            : null;
    }

    private function publicationDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $date = substr($value, 0, 10);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : null;
    }

    private function doi(Publication $publication): ?string
    {
        $doiObject = $publication->getData('doiObject');

        if (!is_object($doiObject) || !method_exists($doiObject, 'getData')) {
            return null;
        }

        $doi = $doiObject->getData('doi');

        return is_string($doi) && trim($doi) !== '' ? trim($doi) : null;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array{identifier: string, relation: string}>
     */
    private function articleRelatedIdentifiers(Context $journal, array $options): array
    {
        $identifiers = [];

        foreach (['onlineIssn', 'printIssn'] as $field) {
            $issn = $this->stringData($journal, $field);

            if ($issn !== null) {
                $identifiers[] = ['identifier' => $issn, 'relation' => 'isPartOf'];
            }
        }

        $articleUrl = $options['articleUrl'] ?? null;

        if (is_string($articleUrl) && trim($articleUrl) !== '') {
            $identifiers[] = [
                'identifier' => trim($articleUrl),
                'relation' => 'isAlternateIdentifier',
            ];
        }

        return $identifiers;
    }

    private function stringData(object $object, string $key): ?string
    {
        $value = $object->getData($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function stringOptions(array $options, string $key): array
    {
        $values = $options[$key] ?? null;

        if (!is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array{identifier: string}>
     */
    private function communities(array $options): array
    {
        $communities = [];

        foreach ($this->stringOptions($options, 'communities') as $community) {
            $communities[] = ['identifier' => $community];
        }

        return $communities;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<mixed>
     */
    private function files(array $options): array
    {
        $files = $options['files'] ?? null;

        return is_array($files) ? array_values($files) : [];
    }
}
