<?php

namespace Tpwd\KeSearch\Service;

use Psr\Log\LoggerInterface;
use Tpwd\KeSearch\Domain\Repository\GenericRepository;
use Tpwd\KeSearch\Utility\ContentUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdditionalContentService
{
    private LoggerInterface $logger;
    protected array $processedAdditionalTableConfig = [];
    private array $indexerConfig;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function init(array $indexerConfig)
    {
        $this->indexerConfig = $indexerConfig;
        $this->processedAdditionalTableConfig = $this->parseAndProcessAdditionalTablesConfiguration();
    }

    /**
     * Expects a row from tt_content and the processed additional table configuration (set in the indexer
     * configuration). Finds the related row from the additional table and returns the content for the
     * fields set in the additional table configuration.
     *
     * @param array $ttContentRow
     * @return string
     */
    public function getContentFromAdditionalTables(array $ttContentRow): string {
        $content = ' ';
        $config = false;
        if (isset($this->processedAdditionalTableConfig[$ttContentRow['CType']])) {
            $config = $this->processedAdditionalTableConfig[$ttContentRow['CType']];
        }
        if (is_array($config) & !empty($config['fields'])) {
            $genericRepository = GeneralUtility::makeInstance(GenericRepository::class);
            $additionalTableContentRows = $genericRepository->findByReferenceField(
                $config['table'],
                $config['referenceFieldName'],
                $ttContentRow['uid']
            );
            foreach ($additionalTableContentRows as $additionalTableContentRow) {
                foreach ($config['fields'] as $field) {
                    $content .= ' ' . ContentUtility::getPlainContentFromContentRow($additionalTableContentRow, $field);
                }
            }
        }
        return trim($content);
    }

    protected function parseAndProcessAdditionalTablesConfiguration(): array
    {
        $additionalTableConfig = false;
        // parse_ini_string will throw a warning if it could not parse the string.
        // If the system is configured to turn a warning into an exception we catch it here.
        try {
            $additionalTableConfig = parse_ini_string($this->indexerConfig['additional_tables'], true);
        } catch (\Exception $e) {
            $errorMessage =
                'Error while parsing additional table configuration for indexer "' . $this->indexerConfig['title']
                . '": ' . $e->getMessage();
            $this->logger->error($errorMessage);
        }
        if ($additionalTableConfig === false) {
            $errorMessage = 'Could not parse additional table configuration for indexer "' . $this->indexerConfig['title'] . '".';
            $this->logger->error($errorMessage);
            $additionalTableConfig = [];
        }
        return $additionalTableConfig;
    }

}
