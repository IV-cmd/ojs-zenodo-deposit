<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\ojs;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\zenodoDeposit\classes\DepositMapper;
use APP\plugins\generic\zenodoDeposit\classes\PermanentDepositException;
use PKP\core\PKPApplication;
use PKP\file\PrivateFileManager;
use PKP\submissionFile\SubmissionFile;
use RuntimeException;

final class ArticleLoader
{
    /**
     * @param array<string, mixed> $settings
     * @return array{
     *     mappedPayload: array<string, mixed>,
     *     fileReader: \Closure(array{submissionFileId: int, name: string, path: string}): string
     * }
     */
    public function load(int $publicationId, int $contextId, array $settings): array
    {
        $publication = Repo::publication()->get($publicationId);

        if ($publication === null) {
            throw new PermanentDepositException('Published OJS publication could not be loaded.');
        }

        $submissionId = $publication->getData('submissionId');
        $submissionId = $this->positiveInteger($submissionId);

        if ($submissionId === null) {
            throw new PermanentDepositException('Published OJS publication has no valid submission id.');
        }

        $submission = Repo::submission()->get($submissionId);
        $context = Application::getContextDAO()->getById($contextId);

        if ($submission === null || $context === null) {
            throw new PermanentDepositException('Published OJS submission or journal could not be loaded.');
        }

        $contextPath = $context->getData('urlPath');

        if (!is_string($contextPath) || $contextPath === '') {
            throw new PermanentDepositException('OJS journal has no URL path.');
        }

        $request = Application::get()->getRequest();
        $articleUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $contextPath,
            'article',
            'view',
            [$submissionId],
            urlLocaleForPage: ''
        );
        $files = $this->fileReferences($publicationId, $submissionId);
        $mapperOptions = [
            'articleUrl' => $articleUrl,
            'keywords' => $this->stringList($settings['zenodo_keywords'] ?? null),
            'communities' => $this->stringList($settings['zenodo_communities'] ?? null),
            'files' => $files,
        ];
        $mappedPayload = (new DepositMapper())->map($submission, $publication, $context, $mapperOptions);

        return [
            'mappedPayload' => $mappedPayload,
            'fileReader' => fn (array $file): string => $this->readFile($file['path']),
        ];
    }

    /**
     * @return list<array{submissionFileId: int, name: string, path: string, isDataset: bool}>
     */
    private function fileReferences(int $publicationId, int $submissionId): array
    {
        $files = [];
        $seen = [];
        $galleys = Repo::galley()->getCollector()->filterByPublicationIds([$publicationId])->getMany();

        foreach ($galleys as $galley) {
            $fileId = $galley->getData('submissionFileId');
            $fileId = $this->positiveInteger($fileId);

            if ($fileId === null) {
                continue;
            }

            $submissionFile = Repo::submissionFile()->get($fileId, $submissionId);

            if ($submissionFile instanceof SubmissionFile) {
                $this->appendFileReference($submissionFile, false, $files, $seen);
            }
        }

        $dependentFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
            ->includeDependentFiles()
            ->getMany();

        foreach ($dependentFiles as $submissionFile) {
            $this->appendFileReference($submissionFile, true, $files, $seen);
        }

        return $files;
    }

    /**
     * @param list<array{submissionFileId: int, name: string, path: string, isDataset: bool}> $files
     * @param array<int, true> $seen
     */
    private function appendFileReference(
        SubmissionFile $submissionFile,
        bool $isDataset,
        array &$files,
        array &$seen
    ): void {
        $submissionFileId = $submissionFile->getId();
        $submissionFileId = $this->positiveInteger($submissionFileId);
        $path = $submissionFile->getData('path');

        if ($submissionFileId === null || !is_string($path) || $path === '' || isset($seen[$submissionFileId])) {
            return;
        }

        $name = $this->fileName($submissionFile->getData('name'), $path);
        $files[] = [
            'submissionFileId' => $submissionFileId,
            'name' => $name,
            'path' => $path,
            'isDataset' => $isDataset,
        ];
        $seen[$submissionFileId] = true;
    }

    private function fileName(mixed $name, string $path): string
    {
        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        if (is_array($name)) {
            foreach ($name as $localizedName) {
                if (is_string($localizedName) && trim($localizedName) !== '') {
                    return trim($localizedName);
                }
            }
        }

        return basename(str_replace('\\', '/', $path));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $values[] = trim($item);
            }
        }

        return $values;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    private function readFile(string $path): string
    {
        $basePath = realpath((new PrivateFileManager())->getBasePath());

        if ($basePath === false) {
            throw new PermanentDepositException('OJS private file directory could not be resolved.');
        }

        $candidatePath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : $basePath . DIRECTORY_SEPARATOR . $path;
        $filePath = realpath($candidatePath);

        if (
            $filePath === false
            || !str_starts_with($filePath, rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        ) {
            throw new PermanentDepositException(
                'OJS submission file path is missing or outside the private file directory.'
            );
        }

        $contents = file_get_contents($filePath);

        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read OJS submission file.');
        }

        return $contents;
    }
}
