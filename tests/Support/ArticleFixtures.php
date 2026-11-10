<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests\Support;

use APP\publication\Publication;
use APP\submission\Submission;
use PKP\author\Author;
use PKP\affiliation\Affiliation;
use PKP\context\Context;
use PKP\doi\Doi;

final class ArticleFixtures
{
    /**
     * @return array{
     *     submission: Submission,
     *     publication: Publication,
     *     journal: Context,
     *     options: array<string, mixed>
     * }
     */
    public static function create(): array
    {
        $submission = new Submission();
        $publication = new Publication();
        $journal = new Context();

        $journal->setData('primaryLocale', 'en_US');
        $journal->setData('onlineIssn', '1234-5678');
        $journal->setData('printIssn', '8765-4321');

        $publication->setData('locale', 'fr_CA');
        $publication->setData('title', [
            'en_US' => '<p>The Example Article</p>',
            'fr_CA' => 'Article de démonstration',
        ]);
        $publication->setData('abstract', [
            'en_US' => '<p>A <strong>brief</strong> description &amp; details.</p>',
        ]);
        $publication->setData('datePublished', '2024-02-29T10:30:00Z');
        $publication->setData('licenseUrl', 'https://creativecommons.org/licenses/by/4.0/');

        $doi = new Doi();
        $doi->setData('doi', '10.5555/example.article');
        $publication->setData('doiObject', $doi);

        $adaAffiliation = new Affiliation();
        $adaAffiliation->setData('name', ['en_US' => 'Analytical Engine Institute']);

        $ada = new Author();
        $ada->setData('givenName', ['en_US' => 'Ada']);
        $ada->setData('familyName', ['en_US' => 'Lovelace']);
        $ada->setData('orcid', 'https://orcid.org/0000-0002-1694-233X');
        $ada->setData('affiliations', [$adaAffiliation]);
        $ada->setData('seq', 1);

        $grace = new Author();
        $grace->setData('givenName', ['en_US' => 'Grace']);
        $grace->setData('familyName', ['en_US' => 'Hopper']);
        $grace->setData('seq', 2);
        $publication->setData('authors', [$grace, $ada]);

        return [
            'submission' => $submission,
            'publication' => $publication,
            'journal' => $journal,
            'options' => [
                'articleUrl' => 'https://journal.example.test/article/view/42',
                'keywords' => ['computing', 'history'],
                'communities' => ['open-research'],
            ],
        ];
    }
}
