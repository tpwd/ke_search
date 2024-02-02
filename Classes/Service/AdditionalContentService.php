<?php

namespace Tpwd\KeSearch\Service;

use Psr\Log\LoggerInterface;
use Tpwd\KeSearch\Domain\Repository\GenericRepository;
use Tpwd\KeSearch\Utility\ContentUtility;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdditionalContentService
{
    private LoggerInterface $logger;
    protected array $processedAdditionalTableConfig = [];
    protected array $indexerConfig = [];

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
     * Returns an array with the keys "content" and "files". "content" has the indexable content of the content row,
     * "files" has an array of file objects.
     *
     * @param array $ttContentRow
     * @return array
     */
    public function getContentAndFilesFromAdditionalTables(array $ttContentRow): array {
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
                    $content .= ' ' . ContentUtility::getPlainContentFromContentRow($additionalTableContentRow, $field);
                    $files = array_merge($files, $this->findLinkedFilesInRte($additionalTableContentRow, $field));
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
        return $additionalTableConfig;
    }

    /**
     * Finds files linked in RTE text. Returns them as array of file objects.
     *
     * @param array $contentRow content element (row from tt_content or additional content table)
     * @param string $field
     * @return array
     */
    public function findLinkedFilesInRte($contentRow, $field = 'bodytext'): array
    {
        $fileObjects = [];
        /* @var $rteHtmlParser RteHtmlParser */
        $rteHtmlParser = GeneralUtility::makeInstance(RteHtmlParser::class);
        /** @var LinkService $linkService */
        $linkService = GeneralUtility::makeInstance(LinkService::class);

        $blockSplit = $rteHtmlParser->splitIntoBlock('A', (string)$contentRow[$field], true);
        foreach ($blockSplit as $k => $v) {
            list($attributes) = $rteHtmlParser->get_tag_attributes($rteHtmlParser->getFirstTag($v), true);
            if (!empty($attributes['href'])) {
                try {
                    $hrefInformation = $linkService->resolve($attributes['href']);
                    if ($hrefInformation['type'] === LinkService::TYPE_FILE) {
                        $fileObjects[] = $hrefInformation['file'];
                    }
                } catch (Exception $exception) {
                    // @extensionScannerIgnoreLine
                    $this->pObj->logger->error($exception->getMessage());
                }
            }
        }
        return $fileObjects;
    }
}
