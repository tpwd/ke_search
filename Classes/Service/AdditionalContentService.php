<?php

namespace Tpwd\KeSearch\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Tpwd\KeSearch\Domain\Repository\GenericRepository;
use Tpwd\KeSearch\Utility\ContentUtility;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdditionalContentService
{
    private LoggerInterface $logger;
    protected array $processedAdditionalTableConfig = [];
    protected array $indexerConfig = [];
    private GenericRepository $genericRepository;
    private RteHtmlParser $rteHtmlParser;
    private LinkService $linkService;

    public function __construct(
        LoggerInterface $logger,
        GenericRepository $genericRepository,
        RteHtmlParser $rteHtmlParser,
        LinkService $linkService
    ) {
        $this->logger = $logger;
        $this->genericRepository = $genericRepository;
        $this->rteHtmlParser = $rteHtmlParser;
        $this->linkService = $linkService;
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
     * Returns an array with the keys "content" and "files". "content" has the indexable content of the content row,
     * "files" has an array of file objects.
     *
     * @param array $ttContentRow
     * @return array
     */
    public function getContentAndFilesFromAdditionalTables(array $ttContentRow): array
    {
        $content = ' ';
        $files = [];
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
                    $content .= ' ' . ContentUtility::getPlainContentFromContentRow(
                        $additionalTableContentRow,
                        $field,
                        $GLOBALS['TCA'][$config['table']]['columns'][$field]['config']['type'] ?? ''
                    );
                    $files = array_merge($files, $this->findLinkedFiles($additionalTableContentRow, $field));
                }
            }
        }
        return ['content' => trim($content), 'files' => $files];
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
        foreach ($additionalTableConfig as $configKey => $config) {
            if (!$this->genericRepository->tableExists($config['table'])) {
                unset($additionalTableConfig[$configKey]);
            }
        }
        return $additionalTableConfig;
    }

    /**
     * Finds files linked in rich text and link fields (TCA type "link"). Returns them as array of file objects.
     *
     * @param array $contentRow content element (row from tt_content or additional content table)
     * @param string $field
     * @return array
     */
    public function findLinkedFiles(array $contentRow, string $field = 'bodytext'): array
    {
        if (!isset($contentRow[$field])) {
            return [];
        }
        $fileObjects = [];

        // Find files linked in RTE
        $blockSplit = $this->rteHtmlParser->splitIntoBlock('A', (string)$contentRow[$field], true);
        foreach ($blockSplit as $k => $v) {
            list($attributes) = $this->rteHtmlParser->get_tag_attributes($this->rteHtmlParser->getFirstTag($v), true);
            if (!empty($attributes['href'])) {
                try {
                    $hrefInformation = $this->linkService->resolve($attributes['href']);
                    if ($hrefInformation['type'] === LinkService::TYPE_FILE) {
                        $fileObjects[] = $hrefInformation['file'];
                    }
                } catch (Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        }

        // Find files linked in link field
        if (str_starts_with($contentRow[$field], 't3://')) {
            $link = $contentRow[$field];
            // The link may contain additional information separated by space, like
            // t3://file?uid=620 _blank - "Link to PDF file"
            // We remove that here to keep only the plain link
            if (strpos($link, ' ')) {
                $link = substr($link, 0, strpos($link, ' '));
            }
            $hrefInformation = $this->linkService->resolve($link);
            if ($hrefInformation['type'] === LinkService::TYPE_FILE) {
                $fileObjects[] = $hrefInformation['file'];
            }
        }
        return $fileObjects;
    }
}
