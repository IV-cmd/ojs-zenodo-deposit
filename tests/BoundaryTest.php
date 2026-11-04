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

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString('use PKP\\', $source);
            self::assertStringNotContainsString('use \\PKP\\', $source);
            self::assertStringNotContainsString('\\PKP\\', $source);
            self::assertStringNotContainsString('__(', $source);
            self::assertStringNotContainsString("https://zenodo.org'", $source);

            preg_match_all('/^\s*use\s+(APP\\\\[^;]+);/m', $source, $matches);

            foreach ($matches[1] as $import) {
                self::assertStringStartsWith($namespacePrefix, $import);
            }
        }

        self::assertSame('mock', ZenodoConfig::DEFAULT_MODE);
        self::assertSame('sandbox.zenodo.org', parse_url(ZenodoConfig::DEFAULT_BASE_URL, PHP_URL_HOST));
    }
}
