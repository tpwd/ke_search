<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\IndexerConfiguration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Tpwd\KeSearch\Lib\Db;

class DatabaseIndexerConfigurationProvider implements IndexerConfigurationProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigurations(): array
    {
        $queryBuilder = Db::getQueryBuilder('tx_kesearch_indexerconfig');
        $rows = $queryBuilder
            ->select('*')
            ->from('tx_kesearch_indexerconfig')
            ->executeQuery()
            ->fetchAllAssociative();

        $configurations = [];
        foreach ($rows as $row) {
            $row['identifier'] = 'database:' . $row['uid'];
            $row['source'] = 'database';
            $row['sourceFile'] = '';
            $configurations[] = $row;
        }

        $this->logger->debug('DatabaseIndexerConfigurationProvider found ' . count($configurations) . ' configurations.');

        return $configurations;
    }
}
