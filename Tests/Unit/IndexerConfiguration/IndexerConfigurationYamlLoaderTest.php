<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\IndexerConfiguration;

use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationYamlLoader;
use TYPO3\CMS\Core\Configuration\Loader\Exception\YamlParseException;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;

class IndexerConfigurationYamlLoaderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function loadReturnsArrayOnSuccess(): void
    {
        $loaderMock = $this->createMock(YamlFileLoader::class);
        $loaderMock->expects(self::once())
            ->method('load')
            ->with('EXT:my_ext/config.yaml', YamlFileLoader::PROCESS_PLACEHOLDERS | YamlFileLoader::PROCESS_IMPORTS)
            ->willReturn(['identifier' => 'test']);

        $subject = new IndexerConfigurationYamlLoader($loaderMock);
        $result = $subject->load('EXT:my_ext/config.yaml');

        self::assertSame(['identifier' => 'test'], $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function loadCatchesExceptionsAndReturnsNull(): void
    {
        $loaderMock = $this->createMock(YamlFileLoader::class);
        $loaderMock->expects(self::once())
            ->method('load')
            ->willThrowException(new YamlParseException('Malformed YAML'));

        $subject = new IndexerConfigurationYamlLoader($loaderMock);
        $result = $subject->load('EXT:my_ext/invalid.yaml');

        self::assertNull($result);
    }
}
