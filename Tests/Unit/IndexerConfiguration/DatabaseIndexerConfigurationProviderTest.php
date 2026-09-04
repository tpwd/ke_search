<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Tests\Unit\IndexerConfiguration;

use PHPUnit\Framework\TestCase;
use Tpwd\KeSearch\IndexerConfiguration\DatabaseIndexerConfigurationProvider;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationProviderInterface;

class DatabaseIndexerConfigurationProviderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function implementsIndexerConfigurationProviderInterface(): void
    {
        $provider = new DatabaseIndexerConfigurationProvider();
        self::assertInstanceOf(IndexerConfigurationProviderInterface::class, $provider);
    }
}
