<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Indexer\Types;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\Domain\Repository\IndexRepository;
use Tpwd\KeSearch\Indexer\IndexerBase;
use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Indexer\Types\File;
use Tpwd\KeSearch\Lib\Fileinfo;
use Tpwd\KeSearch\Service\IndexerStatusService;
use TYPO3\CMS\Core\Log\Logger;

class FileTest extends TestCase
{
    private array $temporaryFiles = [];

    private IndexRepository $indexRepository;

    /** @var Logger&MockObject */
    private Logger $logger;

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('metadataTimestampScenarios')]
    public function isMetadataNewerThanIndexRecordHandlesTimestampBoundaries(
        array $metaData,
        array $indexRow,
        bool $expectedResult,
    ): void {
        self::assertSame($expectedResult, File::isMetadataNewerThanIndexRecord($metaData, $indexRow));
    }

    /**
     * @return array<string, array{array<string, int>, array<string, int>, bool}>
     */
    public static function metadataTimestampScenarios(): array
    {
        return [
            'metadata is newer' => [['tstamp' => 101], ['tstamp' => 100], true],
            'timestamps are equal' => [['tstamp' => 100], ['tstamp' => 100], true],
            'metadata is older' => [['tstamp' => 99], ['tstamp' => 100], false],
            'metadata timestamp is missing' => [[], ['tstamp' => 100], false],
            'index timestamp is missing' => [['tstamp' => 100], [], false],
            'metadata timestamp is zero' => [['tstamp' => 0], ['tstamp' => 100], false],
            'index timestamp is zero' => [['tstamp' => 100], ['tstamp' => 0], false],
        ];
    }

    #[Test]
    public function incrementalIndexingSkipsFileWhenMetadataIsOlder(): void
    {
        $filePath = $this->createTemporaryFile();
        $file = $this->createFalFile($filePath);
        $indexer = $this->createIndexer(
            ['uid' => 7, 'content' => 'stored content', 'tstamp' => 100, 'language' => -1],
            File::INDEXING_MODE_INCREMENTAL,
            $filePath
        );
        $indexer->expects(self::once())
            ->method('getMetaDataForFile')
            ->with($file)
            ->willReturn(['tstamp' => 99]);
        $indexer->expects(self::never())->method('storeToIndex');
        $indexer->expects(self::never())->method('getFileContent');
        $indexer->expects(self::never())->method('removeIndexRecordIfLanguageChanged');

        self::assertSame(0, $indexer->extractContentAndSaveToIndex([$file]));
    }

    #[Test]
    public function incrementalIndexingRefreshesChangedMetadataUsingStoredContent(): void
    {
        $filePath = $this->createTemporaryFile();
        $file = $this->createFalFile($filePath);
        $indexer = $this->createIndexer(
            ['uid' => 7, 'content' => 'stored content', 'tstamp' => 100, 'language' => -1],
            File::INDEXING_MODE_INCREMENTAL,
            $filePath
        );
        $indexer->expects(self::once())
            ->method('getMetaDataForFile')
            ->with($file)
            ->willReturn(['tstamp' => 101]);
        $indexer->expects(self::once())
            ->method('removeIndexRecordIfLanguageChanged')
            ->with($file, ['uid' => 7, 'content' => 'stored content', 'tstamp' => 100, 'language' => -1]);
        $indexer->expects(self::once())
            ->method('storeToIndex')
            ->with($file, 'stored content');
        $indexer->expects(self::never())->method('getFileContent');
        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Re-indexing FAL file because metadata has changed', ['file' => '1:/file.txt']);

        self::assertSame(1, $indexer->extractContentAndSaveToIndex([$file]));
    }

    #[Test]
    public function incrementalIndexingExtractsContentWhenNoMatchingIndexRowExists(): void
    {
        $filePath = $this->createTemporaryFile();
        $file = $this->createFalFile($filePath);
        $indexer = $this->createIndexer(false, File::INDEXING_MODE_INCREMENTAL, $filePath);
        $indexer->expects(self::never())->method('getMetaDataForFile');
        $indexer->expects(self::once())
            ->method('getFileContent')
            ->with($filePath)
            ->willReturn('extracted content');
        $indexer->expects(self::once())
            ->method('storeToIndex')
            ->with($file, 'extracted content');

        self::assertSame(1, $indexer->extractContentAndSaveToIndex([$file]));
    }

    #[Test]
    public function fullIndexingStoresExistingContentWithoutCheckingMetadata(): void
    {
        $filePath = $this->createTemporaryFile();
        $file = $this->createFalFile($filePath);
        $indexer = $this->createIndexer(
            ['uid' => 7, 'content' => 'stored content', 'tstamp' => 100, 'language' => -1],
            File::INDEXING_MODE_FULL,
            $filePath
        );
        $indexer->expects(self::never())->method('getMetaDataForFile');
        $indexer->expects(self::once())
            ->method('storeToIndex')
            ->with($file, 'stored content');
        $indexer->expects(self::never())->method('getFileContent');

        self::assertSame(1, $indexer->extractContentAndSaveToIndex([$file]));
    }

    #[Test]
    public function incrementalIndexingDoesNotCheckMetadataForNonFalPaths(): void
    {
        $filePath = $this->createTemporaryFile();
        $indexer = $this->createIndexer(
            ['uid' => 7, 'content' => 'stored content', 'tstamp' => 100, 'language' => -1],
            File::INDEXING_MODE_INCREMENTAL,
            $filePath
        );
        $indexer->expects(self::never())->method('getMetaDataForFile');
        $indexer->expects(self::never())->method('storeToIndex');
        $indexer->expects(self::never())->method('getFileContent');

        self::assertSame(0, $indexer->extractContentAndSaveToIndex([$filePath]));
    }

    #[Test]
    #[DataProvider('languageChangeScenarios')]
    public function staleIndexRecordIsDeletedOnlyWhenLanguageChanges(
        int $fileLanguage,
        int $indexedLanguage,
        bool $shouldDelete,
    ): void {
        /** @var File&MockObject $indexer */
        $indexer = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['detectFileLanguage'])
            ->getMock();
        $fileProperties = ['language' => 'fr'];
        $file = $this->createMock(\TYPO3\CMS\Core\Resource\File::class);
        $file->method('getProperties')->willReturn($fileProperties);
        $indexer->expects(self::once())
            ->method('detectFileLanguage')
            ->with($fileProperties)
            ->willReturn($fileLanguage);

        $indexRepository = $this->createMock(IndexRepository::class);
        $indexRepository->expects($shouldDelete ? self::once() : self::never())
            ->method('deleteByUid')
            ->with(7);
        $this->setIndexerProperty($indexer, 'indexRepository', $indexRepository);

        $method = new \ReflectionMethod(File::class, 'removeIndexRecordIfLanguageChanged');
        $method->setAccessible(true);
        $method->invoke($indexer, $file, ['uid' => 7, 'language' => $indexedLanguage]);
    }

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function languageChangeScenarios(): array
    {
        return [
            'different language' => [2, 1, true],
            'same language' => [1, 1, false],
        ];
    }

    /** @return File&MockObject */
    private function createIndexer(array|false $indexRow, int $indexingMode, string $filePath): File
    {
        $indexer = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getUniqueHashForFile',
                'storeToIndex',
                'getFileContent',
                'getMetaDataForFile',
                'removeIndexRecordIfLanguageChanged',
            ])
            ->getMock();
        $indexer->method('getUniqueHashForFile')->willReturn('file-hash');
        $fileInfo = $this->createMock(Fileinfo::class);
        $fileInfo->method('setFile')->willReturn(true);
        $indexer->fileInfo = $fileInfo;
        $indexer->indexerConfig = [];

        $runner = $this->createMock(IndexerRunner::class);
        $this->logger = $this->createMock(Logger::class);
        $runner->logger = $this->logger;
        $indexer->pObj = $runner;

        $this->indexRepository = $this->createMock(IndexRepository::class);
        $this->indexRepository->expects(self::once())
            ->method('findOneByHashAndModificationTime')
            ->with('file-hash', filemtime($filePath))
            ->willReturn($indexRow);
        $this->setIndexerProperty($indexer, 'indexRepository', $this->indexRepository);
        $this->setIndexerProperty(
            $indexer,
            'indexerStatusService',
            $this->createMock(IndexerStatusService::class)
        );
        $this->setIndexerProperty($indexer, 'indexingMode', $indexingMode);

        return $indexer;
    }

    private function createFalFile(string $filePath): \TYPO3\CMS\Core\Resource\File
    {
        $file = $this->createMock(\TYPO3\CMS\Core\Resource\File::class);
        $file->method('getForLocalProcessing')->with(false)->willReturn($filePath);
        $file->method('getCombinedIdentifier')->willReturn('1:/file.txt');

        return $file;
    }

    private function createTemporaryFile(): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'ke_search_file_');
        if ($temporaryFile === false) {
            self::fail('Could not create temporary file.');
        }
        $this->temporaryFiles[] = $temporaryFile;

        return $temporaryFile;
    }

    private function setIndexerProperty(File $indexer, string $propertyName, mixed $value): void
    {
        $property = new \ReflectionProperty(IndexerBase::class, $propertyName);
        $property->setAccessible(true);
        $property->setValue($indexer, $value);
    }
}
