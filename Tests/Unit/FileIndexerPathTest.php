<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Indexer\Types\File;
use Tpwd\KeSearch\Service\IndexerStatusService;
use TYPO3\CMS\Core\Log\Logger;

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

    #[Test]
    public function fatalMemoryErrorsAreReturnedAsUserVisibleReports(): void
    {
        $runner = (new \ReflectionClass(IndexerRunner::class))->newInstanceWithoutConstructor();
        $runner->logger = $this->createMock(Logger::class);
        $runner->logger->expects(self::once())
            ->method('critical')
            ->with(self::stringContains('Fatal error during indexing:'), self::anything());

        $indexerStatusService = $this->createMock(IndexerStatusService::class);
        $indexerStatusService->expects(self::once())->method('clearIndexerStartTime');
        $indexerStatusService->expects(self::once())
            ->method('setFinishedStatus')
            ->with(['uid' => 42, 'title' => 'Files']);

        $property = new \ReflectionProperty(IndexerRunner::class, 'indexerStatusService');
        $property->setAccessible(true);
        $property->setValue($runner, $indexerStatusService);

        $method = new \ReflectionMethod(IndexerRunner::class, 'handleFatalIndexingError');
        $report = $method->invoke(
            $runner,
            new \Error('Allowed memory size of 134217728 bytes exhausted'),
            ['uid' => 42, 'title' => 'Files']
        );

        self::assertStringContainsString('Fatal indexing error', $report);
        self::assertStringContainsString('Allowed memory size of 134217728 bytes exhausted', $report);
    }

    private function createFileIndexerWithoutConstructor(): File
    {
        $indexer = (new \ReflectionClass(File::class))->newInstanceWithoutConstructor();
        $indexer->pObj = $this->createIndexerRunnerStub();
        return $indexer;
    }

    private function createIndexerRunnerStub(): IndexerRunner
    {
        $runner = $this->createMock(IndexerRunner::class);
        $runner->logger = $this->createMock(Logger::class);
        return $runner;
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
