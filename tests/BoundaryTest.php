<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfig;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BoundaryTest extends TestCase
{
    public function testClassesRemainIndependentOfOjsAndProductionDefaults(): void
    {
        $classesPath = dirname(__DIR__) . '/classes';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($classesPath, FilesystemIterator::SKIP_DOTS)
        );
        $namespacePrefix = 'APP\\plugins\\generic\\zenodoDeposit\\';
        $mapperImports = [
            'APP\\publication\\Publication',
            'APP\\submission\\Submission',
            'PKP\\author\\Author',
            'PKP\\context\\Context',
        ];

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString('__(', $source);
            self::assertStringNotContainsString("https://zenodo.org'", $source);
            self::assertStringNotContainsString('error_log', $source);

            foreach (['Application', 'PKPRequest', 'Repo', 'DAORegistry', 'Locale'] as $forbiddenName) {
                self::assertDoesNotMatchRegularExpression(
                    '/(?<![A-Za-z0-9_])' . preg_quote($forbiddenName, '/') . '(?![A-Za-z0-9_])/',
                    $source
                );
            }

            preg_match_all('/^\s*use\s+((?:APP|PKP)\\\\[^;]+);/m', $source, $matches);
            $fileImports = $matches[1];

            foreach ($fileImports as $import) {
                if (in_array($import, $mapperImports, true)) {
                    self::assertSame('DepositMapper.php', $file->getFilename());
                    continue;
                }

                self::assertStringStartsWith($namespacePrefix, $import);
            }

            $sourceWithoutImports = preg_replace('/^\s*use\s+[^;]+;\s*$/m', '', $source);
            self::assertIsString($sourceWithoutImports);
            self::assertStringNotContainsString('PKP\\', $sourceWithoutImports);
        }

        self::assertSame('mock', ZenodoConfig::DEFAULT_MODE);
        self::assertSame('sandbox.zenodo.org', parse_url(ZenodoConfig::DEFAULT_BASE_URL, PHP_URL_HOST));
    }
}
