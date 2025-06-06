<?php

namespace Tpwd\KeSearch\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentUtility
{
    /**
     * Returns an indexable string without tags. Expects the content row (which can be either from tt_content or
     * from an additional table) and the field name which should be processed. Optionally the type can be set to "link"
     * (refers to the TCA type "link"), in this case it will just return an empty string because link fields do not
     * go into the index directly.
     *
     * @param array $contentRow
     * @param string $fieldName
     * @param string $type
     * @return string
     */
    public static function getPlainContentFromContentRow(
        array $contentRow,
        string $fieldName,
        string $type = 'text'
    ): string {
        if (!isset($contentRow[$fieldName])) {
            return '';
        }

        if ($type == 'link') {
            $content = '';
        } else {
            $content = (string)$contentRow[$fieldName];
        }

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
        $content = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $content) ?? '';

        // remove other tags
        $content = strip_tags($content);

        // hook for modifiying a content rows content
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentRow'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['modifyContentFromContentRow'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $_procObj->modifyContentFromContentRow($content, $contentRow, $fieldName, $type);
            }
        }

        return $content;
    }

    public static function addHiddenContent(array &$additionalFields, string $hiddenContent)
    {
        $hiddenContent = trim($hiddenContent);
        if (empty($hiddenContent)) {
            return;
        }
        if (!isset($additionalFields['hidden_content'])) {
            $additionalFields['hidden_content'] = '';
        }
        if (!empty($additionalFields['hidden_content'])) {
            $additionalFields['hidden_content'] .= ' ';
        }
        $additionalFields['hidden_content'] .= $hiddenContent;
    }

    /**
     * Replaces a pattern in the text content, but only in the text parts, not in the HTML tags.
     *
     * @param string $pattern Regular expression pattern to match
     * @param string $replace Replacement string
     * @param string $content The content in which to perform the replacement
     * @return string
     */
    public static function replaceInText(string $pattern, string $replace, string $content): string
    {
        $parts = preg_split('/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = preg_replace($pattern, $replace, $part);
            }
        }
        return implode('', $parts);
    }
}
