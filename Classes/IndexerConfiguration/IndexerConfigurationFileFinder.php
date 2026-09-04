<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\IndexerConfiguration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexerConfigurationFileFinder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?PackageManager $packageManager;

    public function __construct(?PackageManager $packageManager = null)
    {
        $this->packageManager = $packageManager;
        $this->logger = new NullLogger();
    }

    /**
     * Finds all YAML configuration files in active packages in deterministic order.
     *
     * @return list<string> List of EXT:... paths
     */
    public function findAllConfigurationFiles(): array
    {
        $packageManager = $this->packageManager ?? GeneralUtility::makeInstance(PackageManager::class);
        $files = [];

        foreach ($packageManager->getActivePackages() as $packageKey => $package) {
            $packagePath = rtrim($package->getPackagePath(), '/');
            $searchDir = $packagePath . '/Configuration/KeSearch/IndexerConfigurations';

            if (!is_dir($searchDir)) {
                continue;
            }

            $realPackagePath = realpath($packagePath);
            $realSearchDir = realpath($searchDir);
            if ($realPackagePath === false || $realSearchDir === false) {
                continue;
            }

            $packageFiles = [];
            // Note: FOLLOW_SYMLINKS is intentionally NOT set. Following symlinks here would
            // allow configuration files located outside of this package (e.g. in a writable
            // upload directory) to be picked up as trusted indexer configurations.
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($searchDir, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $extension = strtolower($fileInfo->getExtension());
                if ($extension !== 'yaml' && $extension !== 'yml') {
                    continue;
                }

                // Defensive check in case a symlink still resolves outside of the package
                // directory (e.g. via a symlinked subdirectory): skip such files.
                $realFilePath = realpath($fileInfo->getPathname());
                if ($realFilePath === false || !str_starts_with($realFilePath, $realSearchDir . DIRECTORY_SEPARATOR)) {
                    $this->logger->warning(
                        sprintf('Skipping configuration file "%s" because it resolves outside of "%s".', $fileInfo->getPathname(), $realSearchDir)
                    );
                    continue;
                }

                $relativePath = ltrim(substr($realFilePath, strlen($realPackagePath)), '/');
                $packageFiles[] = 'EXT:' . $packageKey . '/' . $relativePath;
            }

            sort($packageFiles, SORT_STRING);
            foreach ($packageFiles as $file) {
                $files[] = $file;
            }
        }

        $this->logger->debug(
            sprintf('IndexerConfigurationFileFinder found %d configuration file(s).', count($files)),
            ['files' => $files]
        );

        return $files;
    }
}
