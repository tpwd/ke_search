<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tpwd\KeSearch\Indexer\Types\File;

class FileIndexerPathTest extends TestCase
{
    private array $pathsToCleanup = [];
    private string $basePath;

    protected function tearDown(): void
    {
        foreach (array_reverse($this->pathsToCleanup) as $path) {
            $this->removePath($path);
        }
    }

    #[Test]
    public function directoriesInsidePublicPathAreResolvedToCanonicalAbsolutePaths(): void
    {
        $this->basePath = rtrim(sys_get_temp_dir(), '/') . '/ke_search_public_' . uniqid('', true);
        mkdir($this->basePath, 0777, true);
        $this->pathsToCleanup[] = $this->basePath;

        $insideDirectory = $this->basePath . '/typo3temp/ke_search_inside_' . uniqid('', true);
        mkdir($insideDirectory, 0777, true);
        $this->pathsToCleanup[] = $insideDirectory;
        $this->pathsToCleanup[] = dirname($insideDirectory);

        $directoryInput = 'typo3temp/' . basename($insideDirectory);

        $indexer = $this->createFileIndexerWithoutConstructor();
        $resolvedDirectories = $indexer->getAbsoluteDirectoryPath([$directoryInput, $directoryInput . '/'], $this->basePath);

        self::assertSame([rtrim((string)realpath($insideDirectory), '/') . '/'], $resolvedDirectories);
        self::assertSame([], $indexer->getErrors());
    }

    #[Test]
    public function traversalDirectoriesOutsidePublicPathAreRejected(): void
    {
        $this->basePath = rtrim(sys_get_temp_dir(), '/') . '/ke_search_public_' . uniqid('', true);
        mkdir($this->basePath, 0777, true);
        $this->pathsToCleanup[] = $this->basePath;

        $outsideDirectory = dirname($this->basePath) . '/ke_search_outside_' . uniqid('', true);
        mkdir($outsideDirectory, 0777, true);
        $this->pathsToCleanup[] = $outsideDirectory;

        $directoryInput = '../' . basename($outsideDirectory);

        $indexer = $this->createFileIndexerWithoutConstructor();
        $resolvedDirectories = $indexer->getAbsoluteDirectoryPath([$directoryInput], $this->basePath);

        self::assertSame([], $resolvedDirectories);
        self::assertNotEmpty($indexer->getErrors());
    }

    #[Test]
    public function symlinkDirectoriesOutsidePublicPathAreRejected(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Symlink test is not reliable on Windows environments.');
        }

        $this->basePath = rtrim(sys_get_temp_dir(), '/') . '/ke_search_public_' . uniqid('', true);
        mkdir($this->basePath, 0777, true);
        $this->pathsToCleanup[] = $this->basePath;

        $outsideDirectory = dirname($this->basePath) . '/ke_search_outside_symlink_target_' . uniqid('', true);
        mkdir($outsideDirectory, 0777, true);
        $this->pathsToCleanup[] = $outsideDirectory;

        $symlinkPath = $this->basePath . '/typo3temp/ke_search_symlink_' . uniqid('', true);
        if (!is_dir(dirname($symlinkPath))) {
            mkdir(dirname($symlinkPath), 0777, true);
            $this->pathsToCleanup[] = dirname($symlinkPath);
        }

        if (!@symlink($outsideDirectory, $symlinkPath)) {
            self::markTestSkipped('Could not create symlink in this environment.');
        }
        $this->pathsToCleanup[] = $symlinkPath;

        $directoryInput = 'typo3temp/' . basename($symlinkPath);

        $indexer = $this->createFileIndexerWithoutConstructor();
        $resolvedDirectories = $indexer->getAbsoluteDirectoryPath([$directoryInput], $this->basePath);

        self::assertSame([], $resolvedDirectories);
        self::assertNotEmpty($indexer->getErrors());
    }

    private function createFileIndexerWithoutConstructor(): File
    {
        $indexer = (new \ReflectionClass(File::class))->newInstanceWithoutConstructor();
        $indexer->pObj = (object)['logger' => new NullLogger()];
        return $indexer;
    }

    private function removePath(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);
            return;
        }

        if (is_file($path)) {
            @unlink($path);
            return;
        }

        if (is_dir($path)) {
            @rmdir($path);
        }
    }
}
