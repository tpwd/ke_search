<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationProviderInterface;
use Tpwd\KeSearch\Service\IndexerConfigurationService;

class IndexerConfigurationServiceTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function aggregatesConfigurationsFromProvidersAndDeduplicates(): void
    {
        $dbProvider = $this->createMock(IndexerConfigurationProviderInterface::class);
        $dbProvider->method('getConfigurations')->willReturn([
            [
                'uid' => 1,
                'identifier' => 'database:1',
                'title' => 'DB Page Indexer',
                'type' => 'page',
                'source' => 'database',
            ],
            [
                'uid' => 2,
                'identifier' => 'custom_shared_id',
                'title' => 'DB News Indexer',
                'type' => 'news',
                'source' => 'database',
            ],
        ]);

        $yamlProvider = $this->createMock(IndexerConfigurationProviderInterface::class);
        $yamlProvider->method('getConfigurations')->willReturn([
            // Valid distinct YAML config
            [
                'uid' => -100,
                'identifier' => 'yaml_file_indexer',
                'title' => 'YAML File Indexer',
                'type' => 'file',
                'source' => 'yaml',
            ],
            // Hidden YAML config
            [
                'uid' => -102,
                'identifier' => 'yaml_hidden_indexer',
                'title' => 'YAML Hidden Indexer',
                'type' => 'page',
                'hidden' => 1,
                'source' => 'yaml',
            ],
            // Deleted YAML config
            [
                'uid' => -103,
                'identifier' => 'yaml_deleted_indexer',
                'title' => 'YAML Deleted Indexer',
                'type' => 'page',
                'deleted' => 1,
                'source' => 'yaml',
            ],
            // Duplicate identifier (same as DB indexer)
            [
                'uid' => -101,
                'identifier' => 'custom_shared_id',
                'title' => 'Duplicate Identifier YAML Indexer',
                'type' => 'news',
                'source' => 'yaml',
            ],
            // Duplicate UID
            [
                'uid' => 1,
                'identifier' => 'yaml_duplicate_uid',
                'title' => 'Duplicate UID YAML Indexer',
                'type' => 'page',
                'source' => 'yaml',
            ],
        ]);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(self::exactly(2))
            ->method('error');
        $loggerMock->expects(self::once())
            ->method('info')
            ->with('Loaded 3 indexer configuration(s) in total.');

        $service = new IndexerConfigurationService([$dbProvider, $yamlProvider]);
        $service->setLogger($loggerMock);

        $result = $service->getConfigurations();

        self::assertCount(3, $result);
        self::assertSame('database:1', $result[0]['identifier']);
        self::assertSame('custom_shared_id', $result[1]['identifier']);
        self::assertSame('yaml_file_indexer', $result[2]['identifier']);
    }
}
