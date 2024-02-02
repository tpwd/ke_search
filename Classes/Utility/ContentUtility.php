<?php

namespace Tpwd\KeSearch\Utility;

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
}
