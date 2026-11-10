<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositMapper;
use APP\plugins\generic\zenodoDeposit\classes\DepositMappingException;
use APP\plugins\generic\zenodoDeposit\classes\LicenseMap;
use PHPUnit\Framework\TestCase;
use PKP\author\Author;
use ZenodoDeposit\Tests\Support\ArticleFixtures;

class DepositMapperTest extends TestCase
{
    public function testMapsHappyPathToExactArticleMetadata(): void
    {
        $fixture = ArticleFixtures::create();
        $result = $this->mapFixture($fixture);

        self::assertSame([
            'upload_type' => 'publication',
            'publication_type' => 'article',
            'title' => 'The Example Article',
            'description' => 'A brief description & details.',
            'creators' => [
                [
                    'name' => 'Lovelace, Ada',
                    'affiliation' => 'Analytical Engine Institute',
                    'orcid' => '0000-0002-1694-233X',
                ],
                ['name' => 'Hopper, Grace'],
            ],
            'publication_date' => '2024-02-29',
            'doi' => '10.5555/example.article',
            'related_identifiers' => [
                ['identifier' => '1234-5678', 'relation' => 'isPartOf'],
                ['identifier' => '8765-4321', 'relation' => 'isPartOf'],
                [
                    'identifier' => 'https://journal.example.test/article/view/42',
                    'relation' => 'isAlternateIdentifier',
                ],
            ],
            'license' => 'cc-by-4.0',
            'keywords' => ['computing', 'history'],
            'communities' => [['identifier' => 'open-research']],
            'access_right' => 'open',
        ], $result['article']['metadata']);
        self::assertSame([], $result['article']['files']);
        self::assertArrayNotHasKey('dataset', $result);
        self::assertSame([], $result['warnings']);
    }

    public function testMissingDoiRequestsPrereservationAndWarns(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('doiObject', null);

        $result = $this->mapFixture($fixture);
        $metadata = $result['article']['metadata'];

        self::assertTrue($metadata['prereserve_doi']);
        self::assertArrayNotHasKey('doi', $metadata);
        self::assertContains('Zenodo will mint a DOI; write-back is M4.', $result['warnings']);
    }

    public function testExistingDoiIsIncludedWithoutPrereservation(): void
    {
        $result = $this->mapFixture(ArticleFixtures::create());
        $metadata = $result['article']['metadata'];

        self::assertSame('10.5555/example.article', $metadata['doi']);
        self::assertArrayNotHasKey('prereserve_doi', $metadata);
    }

    public function testMissingAbstractFallsBackToTitle(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('abstract', []);

        $result = $this->mapFixture($fixture);

        self::assertSame('The Example Article', $result['article']['metadata']['description']);
    }

    public function testHtmlAbstractIsStrippedDecodedAndWhitespaceCollapsed(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('abstract', [
            'en_US' => '<p> Hello <em>world</em> &amp; friends. </p>',
        ]);

        $result = $this->mapFixture($fixture);

        self::assertSame('Hello world & friends.', $result['article']['metadata']['description']);
    }

    public function testNoAuthorsThrowsMappingException(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('authors', []);

        $exception = $this->captureMappingException(fn (): array => $this->mapFixture($fixture));

        self::assertSame(
            'The article has no authors to map as Zenodo creators.',
            $exception->getMessage()
        );
    }

    public function testAuthorWithoutNamesThrowsMappingException(): void
    {
        $fixture = ArticleFixtures::create();
        $author = new Author();
        $author->setData('seq', 1);
        $fixture['publication']->setData('authors', [$author]);

        $exception = $this->captureMappingException(fn (): array => $this->mapFixture($fixture));

        self::assertSame(
            'An article author has no name in the available locales.',
            $exception->getMessage()
        );
    }

    public function testOrcidUrlIsReducedToBareIdentifier(): void
    {
        $result = $this->mapFixture(ArticleFixtures::create());

        self::assertSame(
            '0000-0002-1694-233X',
            $result['article']['metadata']['creators'][0]['orcid']
        );
    }

    public function testInvalidOrcidIsOmittedAndWarned(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->getData('authors')[1]->setData('orcid', 'not-an-orcid');

        $result = $this->mapFixture($fixture);

        self::assertArrayNotHasKey('orcid', $result['article']['metadata']['creators'][0]);
        self::assertContains(
            'Invalid ORCID omitted for creator "Lovelace, Ada".',
            $result['warnings']
        );
    }

    public function testUnknownLicenseIsOmittedAndWarned(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('licenseUrl', 'https://licenses.example.invalid/unknown');

        $result = $this->mapFixture($fixture);

        self::assertArrayNotHasKey('license', $result['article']['metadata']);
        self::assertContains(
            'License is missing or unrecognized; Zenodo will apply its own default '
                . '(cc-by for publications or cc-zero for datasets).',
            $result['warnings']
        );
    }

    public function testLicenseMapNormalizesVerifiedCreativeCommonsUrls(): void
    {
        $licenses = [
            'http://creativecommons.org/licenses/by/4.0/' => 'cc-by-4.0',
            'https://www.creativecommons.org/licenses/by-sa/4.0' => 'cc-by-sa-4.0',
            'https://creativecommons.org/licenses/by-nd/4.0/legalcode' => 'cc-by-nd-4.0',
            'https://creativecommons.org/licenses/by-nc/4.0/deed.en' => 'cc-by-nc-4.0',
            'https://creativecommons.org/licenses/by-nc-sa/4.0/deed.en-us/' => 'cc-by-nc-sa-4.0',
            'https://creativecommons.org/licenses/by-nc-nd/4.0/legalcode/' => 'cc-by-nc-nd-4.0',
            'https://www.creativecommons.org/publicdomain/zero/1.0/deed.en' => 'cc0-1.0',
        ];

        foreach ($licenses as $url => $expectedId) {
            self::assertSame($expectedId, LicenseMap::toZenodoId($url));
        }
    }

    public function testDatasetPayloadSeparatesFilesAndLinksToArticleDoi(): void
    {
        $fixture = ArticleFixtures::create();
        $articleFile = [
            'submissionFileId' => 11,
            'name' => 'article.pdf',
            'path' => '/files/article.pdf',
            'isDataset' => false,
        ];
        $datasetFile = [
            'submissionFileId' => 12,
            'name' => 'data.csv',
            'path' => '/files/data.csv',
            'isDataset' => true,
        ];
        $fixture['options']['files'] = [$articleFile, $datasetFile];

        $result = $this->mapFixture($fixture);
        $articleMetadata = $result['article']['metadata'];
        $datasetMetadata = $result['dataset']['metadata'];

        self::assertSame([$articleFile], $result['article']['files']);
        self::assertSame([$datasetFile], $result['dataset']['files']);
        self::assertSame('publication', $articleMetadata['upload_type']);
        self::assertSame('article', $articleMetadata['publication_type']);
        self::assertSame('dataset', $datasetMetadata['upload_type']);
        self::assertArrayNotHasKey('publication_type', $datasetMetadata);
        self::assertSame('The Example Article (dataset)', $datasetMetadata['title']);
        self::assertSame(true, $datasetMetadata['prereserve_doi']);
        self::assertArrayNotHasKey('doi', $datasetMetadata);
        self::assertSame([
            ['identifier' => '10.5555/example.article', 'relation' => 'isSupplementTo'],
        ], $datasetMetadata['related_identifiers']);
        self::assertSame($articleMetadata['creators'], $datasetMetadata['creators']);
        self::assertSame($articleMetadata['publication_date'], $datasetMetadata['publication_date']);
        self::assertSame($articleMetadata['license'], $datasetMetadata['license']);
        self::assertSame($articleMetadata['keywords'], $datasetMetadata['keywords']);
        self::assertSame($articleMetadata['communities'], $datasetMetadata['communities']);
        self::assertSame('open', $datasetMetadata['access_right']);
    }

    public function testDatasetUsesArticleUrlWhenDoiIsMissing(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('doiObject', null);
        $fixture['options']['files'] = [[
            'submissionFileId' => 12,
            'name' => 'data.csv',
            'path' => '/files/data.csv',
            'isDataset' => true,
        ]];

        $result = $this->mapFixture($fixture);

        self::assertSame([
            [
                'identifier' => 'https://journal.example.test/article/view/42',
                'relation' => 'isSupplementTo',
            ],
        ], $result['dataset']['metadata']['related_identifiers']);
    }

    public function testDatasetWithoutArticleDoiOrUrlWarnsThatLinkIsDeferred(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('doiObject', null);
        unset($fixture['options']['articleUrl']);
        $fixture['options']['files'] = [[
            'submissionFileId' => 12,
            'name' => 'data.csv',
            'path' => '/files/data.csv',
            'isDataset' => true,
        ]];

        $result = $this->mapFixture($fixture);

        self::assertArrayNotHasKey('related_identifiers', $result['dataset']['metadata']);
        self::assertContains(
            'Dataset not linked to article; link after article deposit (M3).',
            $result['warnings']
        );
    }

    public function testTitleUsesPrimaryLocaleWhenMultipleValuesExist(): void
    {
        $result = $this->mapFixture(ArticleFixtures::create());

        self::assertSame('The Example Article', $result['article']['metadata']['title']);
    }

    public function testTitleFallsBackToPublicationLocale(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('title', ['fr_CA' => 'Titre de repli']);

        $result = $this->mapFixture($fixture);

        self::assertSame('Titre de repli', $result['article']['metadata']['title']);
    }

    public function testMissingTitleThrowsMappingException(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('title', ['en_US' => ' ', 'fr_CA' => '']);

        $exception = $this->captureMappingException(fn (): array => $this->mapFixture($fixture));

        self::assertSame(
            'The article has no title in the available locales.',
            $exception->getMessage()
        );
    }

    public function testMissingOrInvalidPublicationDateIsOmittedAndWarned(): void
    {
        foreach ([null, '2024-02-30'] as $date) {
            $fixture = ArticleFixtures::create();
            $fixture['publication']->setData('datePublished', $date);

            $result = $this->mapFixture($fixture);

            self::assertArrayNotHasKey('publication_date', $result['article']['metadata']);
            self::assertContains(
                'Publication date was omitted because datePublished is missing or invalid; '
                    . 'Zenodo will default to today.',
                $result['warnings']
            );
        }
    }

    public function testKeywordsAndCommunitiesComeOnlyFromOptions(): void
    {
        $fixture = ArticleFixtures::create();
        $fixture['publication']->setData('keywords', ['en_US' => ['publication-keyword']]);
        unset($fixture['options']['keywords'], $fixture['options']['communities']);

        $result = $this->mapFixture($fixture);

        self::assertArrayNotHasKey('keywords', $result['article']['metadata']);
        self::assertArrayNotHasKey('communities', $result['article']['metadata']);
    }

    public function testJournalIssnsAreMappedAsPartOfIdentifiers(): void
    {
        $fixture = ArticleFixtures::create();
        unset($fixture['options']['articleUrl']);

        $result = $this->mapFixture($fixture);

        self::assertSame([
            ['identifier' => '1234-5678', 'relation' => 'isPartOf'],
            ['identifier' => '8765-4321', 'relation' => 'isPartOf'],
        ], $result['article']['metadata']['related_identifiers']);
    }

    /**
     * @param array{
     *     submission: \APP\submission\Submission,
     *     publication: \APP\publication\Publication,
     *     journal: \PKP\context\Context,
     *     options: array<string, mixed>
     * } $fixture
     * @return array<string, mixed>
     */
    private function mapFixture(array $fixture): array
    {
        return (new DepositMapper())->map(
            $fixture['submission'],
            $fixture['publication'],
            $fixture['journal'],
            $fixture['options']
        );
    }

    /**
     * @param callable(): array<string, mixed> $operation
     */
    private function captureMappingException(callable $operation): DepositMappingException
    {
        try {
            $operation();
        } catch (DepositMappingException $exception) {
            return $exception;
        }

        self::fail('Expected a DepositMappingException.');
    }
}
