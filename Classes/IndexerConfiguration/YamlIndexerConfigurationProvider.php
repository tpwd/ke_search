<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\IndexerConfiguration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class YamlIndexerConfigurationProvider implements IndexerConfigurationProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?IndexerConfigurationFileFinder $fileFinder;
    private ?IndexerConfigurationYamlLoader $yamlLoader;
    private ?IndexerConfigurationNormalizer $normalizer;

    public function __construct(
        ?IndexerConfigurationFileFinder $fileFinder = null,
        ?IndexerConfigurationYamlLoader $yamlLoader = null,
        ?IndexerConfigurationNormalizer $normalizer = null
    ) {
        $this->fileFinder = $fileFinder;
        $this->yamlLoader = $yamlLoader;
        $this->normalizer = $normalizer;
        $this->logger = new NullLogger();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigurations(): array
    {
        $fileFinder = $this->fileFinder ?? GeneralUtility::makeInstance(IndexerConfigurationFileFinder::class);
        $yamlLoader = $this->yamlLoader ?? GeneralUtility::makeInstance(IndexerConfigurationYamlLoader::class);
        $normalizer = $this->normalizer ?? GeneralUtility::makeInstance(IndexerConfigurationNormalizer::class);

        $files = $fileFinder->findAllConfigurationFiles();
        $configurations = [];

        foreach ($files as $file) {
            $rawConfig = $yamlLoader->load($file);
            if ($rawConfig === null) {
                continue;
            }

            $normalized = $normalizer->normalize($rawConfig, $file);
            if ($normalized === null || !empty($normalized['hidden']) || !empty($normalized['deleted'])) {
                continue;
            }

            $configurations[] = $normalized;
        }

        $this->logger->debug(
            sprintf('YamlIndexerConfigurationProvider loaded %d configuration(s) from %d file(s).', count($configurations), count($files))
        );

        return $configurations;
    }
}
