<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Tpwd\KeSearch\IndexerConfiguration\IndexerConfigurationProviderInterface;

class IndexerConfigurationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var iterable<IndexerConfigurationProviderInterface>
     */
    private iterable $providers;

    /**
     * @param iterable<IndexerConfigurationProviderInterface> $providers
     */
    public function __construct(iterable $providers = [])
    {
        $this->providers = $providers;
        $this->logger = new NullLogger();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigurations(): array
    {
        $allConfigurations = [];
        $seenIdentifiers = [];
        $seenUids = [];

        foreach ($this->providers as $provider) {
            $configurations = $provider->getConfigurations();
            foreach ($configurations as $config) {
                if (!empty($config['hidden']) || !empty($config['deleted'])) {
                    continue;
                }

                $identifier = (string)($config['identifier'] ?? '');
                $uid = $config['uid'] ?? null;

                if ($identifier !== '' && isset($seenIdentifiers[$identifier])) {
                    $this->logger->error(
                        sprintf('Duplicate indexer configuration identifier "%s" detected. Dropping duplicate.', $identifier),
                        ['config' => $config]
                    );
                    continue;
                }

                if ($uid !== null && isset($seenUids[$uid])) {
                    $this->logger->error(
                        sprintf('Duplicate indexer configuration UID "%s" detected. Dropping duplicate.', (string)$uid),
                        ['config' => $config]
                    );
                    continue;
                }

                if ($identifier !== '') {
                    $seenIdentifiers[$identifier] = true;
                }
                if ($uid !== null) {
                    $seenUids[$uid] = true;
                }

                $allConfigurations[] = $config;
            }
        }

        $this->logger->info(sprintf('Loaded %d indexer configuration(s) in total.', count($allConfigurations)));

        return $allConfigurations;
    }
}
