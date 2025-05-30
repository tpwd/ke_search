<?php

namespace Tpwd\KeSearch\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\DataProcessing\FilesProcessor;

class AttachedFilesService
{
    /**
     * Finds files attached to a content element and returns them as file reference objects array.
     * This method is used to collect files for indexing.
     * It uses the FilesProcessor to process the content element data and extract file references.
     * FilesProcessor is meant to be used in the frontend context while indexing is done in the backend / CLI context,
     * so this should be refactored in the future.
     *
     * @param array $ttContentRow
     * @param array $indexerConfig
     * @return array
     */
    public function findAttachedFiles(array $ttContentRow, array $indexerConfig): array
    {
        // make cObj
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        // make filesProcessor
        $filesProcessor = GeneralUtility::makeInstance(FilesProcessor::class);

        // Set current data
        // @extensionScannerIgnoreLine
        $cObj->data = $ttContentRow;

        // Get files by filesProcessor
        $processedData = [];

        // set tt_content fields used for file references
        if (empty($indexerConfig['file_reference_fields'])) {
            $fileReferenceFields = ['media'];
        } else {
            $fileReferenceFields = GeneralUtility::trimExplode(',', $indexerConfig['file_reference_fields']);
        }
        $filesProcessorConfiguration = $this->getFilesProcessorConfiguration($fileReferenceFields);

        $fileReferenceObjects = [];
        foreach ($filesProcessorConfiguration as $configuration) {
            $processedData = $filesProcessor->process($cObj, [], $configuration, $processedData);
            $fileReferenceObjects = array_merge($fileReferenceObjects, $processedData['files']);
        }

        return $fileReferenceObjects;
    }

    /**
     * Creates a $filesProcessorConfiguration according to the Page-Indexer-Configuration
     *
     * @param array $fileReferenceFields
     * @return array
     */
    protected function getFilesProcessorConfiguration(array $fileReferenceFields): array
    {
        $filesProcessorConfiguration = [];
        foreach ($fileReferenceFields as $fileReferenceField) {
            $filesProcessorConfiguration[] = [
                'references.' => [
                    'fieldName' => $fileReferenceField,
                    'table' => 'tt_content',
                ],
                'collections.' => [
                    'field' => 'file_collections',
                ],
                'sorting.' => [
                    'field ' => 'filelink_sorting',
                ],
                'as' => 'files',
            ];
        }
        return $filesProcessorConfiguration;
    }
}
