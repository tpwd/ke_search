<?php

namespace Tpwd\KeSearch\Utility;

use Tpwd\KeSearch\Domain\Repository\GenericRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentUtility
{
    public static function getPlainContentFromContentRow(array $contentRow, string $fieldName): string
    {
        $content = (string)$contentRow[$fieldName];

        // following lines prevents having words one after the other like: HelloAllTogether
        $content = str_replace('<td', ' <td', $content);
        $content = str_replace('<br', ' <br', $content);
        $content = str_replace('<p', ' <p', $content);
        $content = str_replace('<li', ' <li', $content);

        if (isset($contentRow['CType']) && $contentRow['CType'] == 'table') {
            // replace table dividers with whitespace
            $content = str_replace('|', ' ', $content);
        }

        // remove script and style tags
        // thanks to the wordpress project
        // https://core.trac.wordpress.org/browser/tags/5.3/src/wp-includes/formatting.php#L5178
        $content = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $content);

        // remove other tags
        $content = strip_tags($content);

        // hook for modifiying a content rows content
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentRow'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentRow'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyContentFromContentRow(
                    $content,
                    $contentRow,
                    $fieldName
                );
            }
        }

        return $content;
    }

    /**
     * Expects a row from tt_content and the processed additional table configuration (set in the indexer
     * configuration). Finds the related row from the additional table and returns the content for the
     * fields set in the additional table configuration.
     *
     * @param array $ttContentRow
     * @param array $processedAdditionalTableConfig
     * @return string
     */
    public static function getContentFromAdditionalTables(
        array $ttContentRow,
        array $processedAdditionalTableConfig
    ): string {
        $content = ' ';
        $config = false;
        if (isset($processedAdditionalTableConfig[$ttContentRow['CType']])) {
            $config = $processedAdditionalTableConfig[$ttContentRow['CType']];
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
                    $content .= ' ' . self::getPlainContentFromContentRow($additionalTableContentRow, $field);
                }
            }
        }
        return trim($content);
    }
}
