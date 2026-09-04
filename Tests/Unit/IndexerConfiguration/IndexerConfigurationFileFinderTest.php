<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\IndexerConfiguration;

use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationFileFinder;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;

class IndexerConfigurationFileFinderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function findsYamlFilesInActivePackages(): void
    {
        $tempDir = sys_get_temp_dir() . '/kesearch_test_' . uniqid();
        $configDir = $tempDir . '/Configuration/KeSearch/IndexerConfigurations';
        mkdir($configDir, 0777, true);

        file_put_contents($configDir . '/b_indexer.yaml', 'identifier: b');
        file_put_contents($configDir . '/a_indexer.yml', 'identifier: a');
        file_put_contents($configDir . '/ignored.txt', 'ignored');

        $packageMock = $this->createMock(PackageInterface::class);
        $packageMock->method('getPackagePath')->willReturn($tempDir . '/');

        $packageManagerMock = $this->createMock(PackageManager::class);
        $packageManagerMock->method('getActivePackages')->willReturn([
            'my_ext' => $packageMock,
        ]);

        $subject = new IndexerConfigurationFileFinder($packageManagerMock);
        $files = $subject->findAllConfigurationFiles();

        self::assertCount(2, $files);
        self::assertSame('EXT:my_ext/Configuration/KeSearch/IndexerConfigurations/a_indexer.yml', $files[0]);
        self::assertSame('EXT:my_ext/Configuration/KeSearch/IndexerConfigurations/b_indexer.yaml', $files[1]);

        // Cleanup
        unlink($configDir . '/b_indexer.yaml');
        unlink($configDir . '/a_indexer.yml');
        unlink($configDir . '/ignored.txt');
        rmdir($configDir);
        rmdir($tempDir . '/Configuration/KeSearch');
        rmdir($tempDir . '/Configuration');
        rmdir($tempDir);
    }
}
