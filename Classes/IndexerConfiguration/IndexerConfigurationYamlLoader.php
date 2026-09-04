<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\IndexerConfiguration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexerConfigurationYamlLoader implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?YamlFileLoader $yamlFileLoader;

    public function __construct(?YamlFileLoader $yamlFileLoader = null)
    {
        $this->yamlFileLoader = $yamlFileLoader;
        $this->logger = new NullLogger();
    }

    /**
     * Loads and parses a YAML configuration file.
     * Catches any YAML/loading exceptions, logs an error, and returns null on failure.
     *
     * @param string $fileName EXT:... or absolute/relative path
     * @return array<string, mixed>|null
     */
    public function load(string $fileName): ?array
    {
        try {
            $yamlFileLoader = $this->yamlFileLoader ?? GeneralUtility::makeInstance(YamlFileLoader::class);
            return $yamlFileLoader->load($fileName, YamlFileLoader::PROCESS_PLACEHOLDERS | YamlFileLoader::PROCESS_IMPORTS);
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to load YAML configuration file "%s": %s', $fileName, $e->getMessage()),
                ['exception' => $e, 'file' => $fileName]
            );
            return null;
        }
    }
}
