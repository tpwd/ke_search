<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\IndexerConfiguration;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Tpwd\KeSearch\Lib\Db;

class IndexerConfigurationNormalizer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * In-memory registry mapping synthetic UIDs to category UIDs.
     *
     * @var array<int, array<int, int>>
     */
    private static array $selectedCategoriesMap = [];

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Normalizes raw YAML configuration data into a DB-row-shaped array.
     *
     * @param array<array-key, mixed> $rawConfig
     * @param string $sourceFile
     * @return array<string, mixed>|null
     */
    public function normalize(array $rawConfig, string $sourceFile = ''): ?array
    {
        $identifier = trim((string)($rawConfig['identifier'] ?? ''));
        $title = trim((string)($rawConfig['title'] ?? ''));
        $type = trim((string)($rawConfig['type'] ?? ''));

        $missingFields = [];
        if ($identifier === '') {
            $missingFields[] = 'identifier';
        }
        if ($title === '') {
            $missingFields[] = 'title';
        }
        if ($type === '') {
            $missingFields[] = 'type';
        }

        if (!empty($missingFields)) {
            $this->logger->error(
                sprintf(
                    'Invalid indexer configuration in "%s": missing required field(s) %s.',
                    $sourceFile !== '' ? $sourceFile : 'unknown source',
                    implode(', ', $missingFields)
                ),
                ['config' => $rawConfig, 'sourceFile' => $sourceFile]
            );
            return null;
        }

        $syntheticUid = $this->generateSyntheticUid($identifier);

        // Resolve categories
        $categoryUids = $this->resolveCategories($rawConfig);
        self::registerSelectedCategories($syntheticUid, $categoryUids);

        $startingpoints = $rawConfig['startingpoints_recursive'] ?? $this->getTcaDefault('startingpoints_recursive', '');
        $singlePages = $rawConfig['single_pages'] ?? $this->getTcaDefault('single_pages', '');
        $sysfolder = $rawConfig['sysfolder'] ?? $this->getTcaDefault('sysfolder', '');
        $storagePid = $rawConfig['storagepid'] ?? $this->getTcaDefault('storagepid', 0);
        $targetPid = $rawConfig['targetpid'] ?? $this->getTcaDefault('targetpid', 0);
        $fileext = $rawConfig['fileext'] ?? $this->getTcaDefault('fileext', '');
        $contentFields = $rawConfig['content_fields'] ?? $this->getTcaDefault('content_fields', '');
        $fileReferenceFields = $rawConfig['file_reference_fields'] ?? $this->getTcaDefault('file_reference_fields', '');
        $contenttypes = $rawConfig['contenttypes'] ?? $this->getTcaDefault('contenttypes', '');
        $additionalTables = $rawConfig['additional_tables'] ?? $this->getTcaDefault('additional_tables', '');
        $doctypes = $rawConfig['index_page_doctypes'] ?? $this->getTcaDefault('index_page_doctypes', '');
        $fileCollections = $rawConfig['file_collections'] ?? $this->getTcaDefault('file_collections', '');
        $directories = $rawConfig['directories'] ?? $this->getTcaDefault('directories', '');

        $restrictions = $rawConfig['index_content_with_restrictions'] ?? $this->getTcaDefault('index_content_with_restrictions', 'no');
        if (is_bool($restrictions)) {
            $restrictions = $restrictions ? 'yes' : 'no';
        }

        $categoryMode = (int)($rawConfig['index_news_category_mode'] ?? (!empty($categoryUids) ? 2 : $this->getTcaDefault('index_news_category_mode', 1)));

        $normalized = [
            'uid' => $syntheticUid,
            'pid' => (int)($rawConfig['pid'] ?? $storagePid),
            'tstamp' => time(),
            'crdate' => time(),
            'deleted' => 0,
            'hidden' => isset($rawConfig['hidden']) ? (filter_var($rawConfig['hidden'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0,
            'title' => $title,
            'type' => $type,
            'storagepid' => $this->toCsvString($storagePid),
            'targetpid' => $this->toCsvString($targetPid),
            'startingpoints_recursive' => $this->toCsvString($startingpoints),
            'single_pages' => $this->toCsvString($singlePages),
            'sysfolder' => $this->toCsvString($sysfolder),
            'index_content_with_restrictions' => (string)$restrictions,
            'index_news_category_mode' => $categoryMode,
            'index_news_category_selection' => implode(',', $categoryUids),
            'index_extnews_category_selection' => implode(',', $categoryUids),
            'index_news_archived' => (int)($rawConfig['index_news_archived'] ?? $this->getTcaDefault('index_news_archived', 0)),
            'index_news_useHRDatesSingle' => (int)($rawConfig['index_news_useHRDatesSingle'] ?? 0),
            'index_news_useHRDatesSingleWithoutDay' => (int)($rawConfig['index_news_useHRDatesSingleWithoutDay'] ?? 0),
            'index_news_files_mode' => (int)($rawConfig['index_news_files_mode'] ?? $this->getTcaDefault('index_news_files_mode', 0)),
            'index_use_page_tags' => isset($rawConfig['index_use_page_tags']) ? (filter_var($rawConfig['index_use_page_tags'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : (int)$this->getTcaDefault('index_use_page_tags', 0),
            'index_use_page_tags_for_files' => isset($rawConfig['index_use_page_tags_for_files']) ? (filter_var($rawConfig['index_use_page_tags_for_files'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : (int)$this->getTcaDefault('index_use_page_tags_for_files', 0),
            'index_page_doctypes' => $this->toCsvString($doctypes),
            'directories' => is_array($directories) ? implode("\n", $directories) : (string)$directories,
            'fileext' => $this->toCsvString($fileext),
            'content_fields' => $this->toCsvString($contentFields),
            'file_reference_fields' => $this->toCsvString($fileReferenceFields),
            'file_collections' => $this->toCsvString($fileCollections),
            'filteroption' => (int)($rawConfig['filteroption'] ?? $this->getTcaDefault('filteroption', 0)),
            'fal_storage' => (int)($rawConfig['fal_storage'] ?? $this->getTcaDefault('fal_storage', 0)),
            'contenttypes' => $this->toCsvString($contenttypes),
            'additional_tables' => is_array($additionalTables) ? $this->arrayToIniString($additionalTables) : (string)$additionalTables,
            'identifier' => $identifier,
            'extensionKey' => $this->extractExtensionKey($sourceFile, $rawConfig),
            'source' => 'yaml',
            'sourceFile' => $sourceFile,
        ];

        // Pass through any custom options or fields
        foreach ($rawConfig as $key => $value) {
            if (!array_key_exists($key, $normalized) && is_scalar($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Extracts the extension key from the source file path (e.g. EXT:my_ext/...) or config array.
     *
     * @param array<string, mixed> $config
     */
    protected function extractExtensionKey(string $sourceFile, array $config = []): string
    {
        if (!empty($config['extensionKey']) && is_string($config['extensionKey'])) {
            return trim($config['extensionKey']);
        }
        $trimmedSourceFile = trim($sourceFile);
        if (str_starts_with($trimmedSourceFile, 'EXT:')) {
            $pathWithoutPrefix = substr($trimmedSourceFile, 4);
            $slashPos = strpos($pathWithoutPrefix, '/');
            return $slashPos !== false ? substr($pathWithoutPrefix, 0, $slashPos) : $pathWithoutPrefix;
        }
        return '';
    }

    /**
     * Generates a synthetic unique identifier (UID) based on the given identifier.
     * The generated UID is always a negative integer and adheres to specific constraints
     * (e.g., it is less than or equal to -2).
     *
     * @param string $identifier The input string used to compute the synthetic UID.
     * @return int Returns a negative synthetic UID based on the provided identifier.
     */
    public function generateSyntheticUid(string $identifier): int
    {
        // Use sha1 instead of crc32 to significantly reduce the risk of collisions
        // between synthetic UIDs of different YAML indexer configurations.
        $hash = hexdec(substr(sha1($identifier), 0, 8)) & 0x7FFFFFFF;
        // Ensure negative UID is <= -2 (leaving -1 for cleanup)
        return -(2 + ($hash % 2000000000));
    }

    /**
     * Registers selected category UIDs in-memory for a synthetic UID.
     *
     * @param int $indexerConfigUid
     * @param array<int, int> $categoryUids
     */
    public static function registerSelectedCategories(int $indexerConfigUid, array $categoryUids): void
    {
        self::$selectedCategoriesMap[$indexerConfigUid] = $categoryUids;
    }

    /**
     * Gets selected category UIDs from the in-memory registry for a synthetic UID.
     *
     * @param int $indexerConfigUid
     * @return array<int, int>|null
     */
    public static function getSelectedCategories(int $indexerConfigUid): ?array
    {
        return self::$selectedCategoriesMap[$indexerConfigUid] ?? null;
    }

    /**
     * Clears the in-memory category registry.
     */
    public static function clearCategoryRegistry(): void
    {
        self::$selectedCategoriesMap = [];
    }

    /**
     * Resolves category references (identifiers, titles, or numeric UIDs) to a list of UIDs.
     *
     * @param array<string, mixed> $config
     * @return array<int, int>
     */
    protected function resolveCategories(array $config): array
    {
        $rawCategories = $config['index_extnews_category_selection']
            ?? $config['category_selection']
            ?? $config['index_news_category_selection']
            ?? [];

        if (is_string($rawCategories)) {
            $rawCategories = array_filter(array_map('trim', explode(',', $rawCategories)));
        }

        if (!is_array($rawCategories) || empty($rawCategories)) {
            return [];
        }

        $resolvedUids = [];
        $unresolvedIdentifiers = [];

        foreach ($rawCategories as $category) {
            if (is_numeric($category)) {
                $resolvedUids[] = (int)$category;
            } elseif (is_string($category) && trim($category) !== '') {
                $unresolvedIdentifiers[] = trim($category);
            }
        }

        if (!empty($unresolvedIdentifiers)) {
            try {
                $queryBuilder = Db::getQueryBuilder('sys_category');
                $rows = $queryBuilder
                    ->select('uid', 'title')
                    ->from('sys_category')
                    ->executeQuery()
                    ->fetchAllAssociative();

                foreach ($unresolvedIdentifiers as $identifier) {
                    $found = false;
                    foreach ($rows as $row) {
                        if (strcasecmp((string)$row['title'], $identifier) === 0) {
                            $resolvedUids[] = (int)$row['uid'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $this->logger->warning(
                            sprintf('Category identifier "%s" could not be resolved to a sys_category record.', $identifier)
                        );
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->debug('Could not query sys_category for category resolution: ' . $e->getMessage());
            }
        }

        return array_values(array_unique($resolvedUids));
    }

    /**
     * Converts mixed value (array, int, string) to a comma-separated string.
     */
    protected function toCsvString(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_filter(array_map(fn($v) => is_scalar($v) ? trim((string)$v) : '', $value), fn($v) => $v !== ''));
        }
        return trim((string)$value);
    }

    /**
     * Converts an associative array of table configurations to an INI format string.
     *
     * @param array<string, mixed> $array
     */
    protected function arrayToIniString(array $array): string
    {
        $ini = '';
        foreach ($array as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            $ini .= '[' . $section . "]\n";
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $ini .= $key . '[] = ' . $v . "\n";
                    }
                } else {
                    $ini .= $key . ' = ' . $value . "\n";
                }
            }
            $ini .= "\n";
        }
        return trim($ini);
    }

    /**
     * Gets the TCA column configuration for tx_kesearch_indexerconfig.
     *
     * @return array<string, mixed>
     */
    protected function getTcaColumns(): array
    {
        if (!empty($GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns']) && is_array($GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns'])) {
            return $GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns'];
        }

        return [];
    }

    /**
     * Gets the default value for a column from TCA.
     */
    protected function getTcaDefault(string $columnName, mixed $fallback = null): mixed
    {
        $columns = $this->getTcaColumns();
        return $columns[$columnName]['config']['default'] ?? $fallback;
    }
}
