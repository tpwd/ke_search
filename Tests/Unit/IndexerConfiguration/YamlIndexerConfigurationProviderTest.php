<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\IndexerConfiguration;

use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationFileFinder;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationNormalizer;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationYamlLoader;
use Tpwd\KeSearch\IndexerConfiguration\YamlIndexerConfigurationProvider;

class YamlIndexerConfigurationProviderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function getConfigurationsFindsLoadsAndNormalizesFiles(): void
    {
        $fileFinderMock = $this->createMock(IndexerConfigurationFileFinder::class);
        $fileFinderMock->expects(self::once())
            ->method('findAllConfigurationFiles')
            ->willReturn(['EXT:site/file1.yaml', 'EXT:site/file2.yaml']);

        $yamlLoaderMock = $this->createMock(IndexerConfigurationYamlLoader::class);
        $yamlLoaderMock->expects(self::exactly(2))
            ->method('load')
            ->willReturnMap([
                ['EXT:site/file1.yaml', ['identifier' => 'cfg1', 'title' => 'Title 1', 'type' => 'page']],
                ['EXT:site/file2.yaml', null], // Simulating a broken file
            ]);

        $normalizerMock = $this->createMock(IndexerConfigurationNormalizer::class);
        $normalizerMock->expects(self::once())
            ->method('normalize')
            ->with(['identifier' => 'cfg1', 'title' => 'Title 1', 'type' => 'page'], 'EXT:site/file1.yaml')
            ->willReturn(
                [
                    'uid' => -10,
                    'identifier' => 'cfg1',
                    'title' => 'Title 1',
                    'type' => 'page',
                    'extensionKey' => 'site',
                    'source' => 'yaml',
                    'sourceFile' => 'EXT:site/file1.yaml',
                ]
            );

        $subject = new YamlIndexerConfigurationProvider($fileFinderMock, $yamlLoaderMock, $normalizerMock);
        $configurations = $subject->getConfigurations();

        self::assertCount(1, $configurations);
        self::assertSame('cfg1', $configurations[0]['identifier']);
        self::assertSame('site', $configurations[0]['extensionKey']);
        self::assertSame(-10, $configurations[0]['uid']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function getConfigurationsFiltersOutHiddenAndDeletedConfigurations(): void
    {
        $fileFinderMock = $this->createMock(IndexerConfigurationFileFinder::class);
        $fileFinderMock->expects(self::once())
            ->method('findAllConfigurationFiles')
            ->willReturn(['EXT:site/active.yaml', 'EXT:site/hidden.yaml', 'EXT:site/deleted.yaml']);

        $yamlLoaderMock = $this->createMock(IndexerConfigurationYamlLoader::class);
        $yamlLoaderMock->expects(self::exactly(3))
            ->method('load')
            ->willReturnMap([
                ['EXT:site/active.yaml', ['identifier' => 'active_cfg']],
                ['EXT:site/hidden.yaml', ['identifier' => 'hidden_cfg']],
                ['EXT:site/deleted.yaml', ['identifier' => 'deleted_cfg']],
            ]);

        $normalizerMock = $this->createMock(IndexerConfigurationNormalizer::class);
        $normalizerMock->expects(self::exactly(3))
            ->method('normalize')
            ->willReturnMap([
                [
                    ['identifier' => 'active_cfg'],
                    'EXT:site/active.yaml',
                    [
                        'uid' => -10,
                        'identifier' => 'active_cfg',
                        'title' => 'Active Config',
                        'type' => 'page',
                        'hidden' => 0,
                        'deleted' => 0,
                    ],
                ],
                [
                    ['identifier' => 'hidden_cfg'],
                    'EXT:site/hidden.yaml',
                    [
                        'uid' => -11,
                        'identifier' => 'hidden_cfg',
                        'title' => 'Hidden Config',
                        'type' => 'news',
                        'hidden' => 1,
                        'deleted' => 0,
                    ],
                ],
                [
                    ['identifier' => 'deleted_cfg'],
                    'EXT:site/deleted.yaml',
                    [
                        'uid' => -12,
                        'identifier' => 'deleted_cfg',
                        'title' => 'Deleted Config',
                        'type' => 'file',
                        'hidden' => 0,
                        'deleted' => 1,
                    ],
                ],
            ]);

        $subject = new YamlIndexerConfigurationProvider($fileFinderMock, $yamlLoaderMock, $normalizerMock);
        $configurations = $subject->getConfigurations();

        self::assertCount(1, $configurations);
        self::assertSame('active_cfg', $configurations[0]['identifier']);
    }
}
