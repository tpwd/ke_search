<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\IndexerConfiguration;

interface IndexerConfigurationProviderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigurations(): array;
}
