<?php

namespace Tpwd\KeSearch\Utility;

/**
 * @deprecated Unused since the news indexer resolves links with the core LinkService.
 *             Will be removed in next major version.
 */
class TypoLinkUtility
{
    /**
     * Extracts the page UID from a typolink string
     *
     * @deprecated Only knows "t3://page?uid=123" and a plain number. Decode the typolink with
     *             \TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService and resolve it with
     *             \TYPO3\CMS\Core\LinkHandling\LinkService instead.
     *             Will be removed in next major version.
     * @param ?string $typolink The typolink string (e.g., "t3://page?uid=123")
     * @return int|null The extracted page UID or null if parsing fails
     */
    public static function extractPageUidFromTypoLink(?string $typolink): ?int
    {
        if (empty($typolink)) {
            return null;
        }

        // Handle t3://page?uid=123 format
        if (preg_match('/t3:\/\/page\?uid=(\d+)/', $typolink, $matches)) {
            return (int)$matches[1];
        }

        // Handle direct numeric UID (fallback for legacy data)
        if (is_numeric($typolink)) {
            return (int)$typolink;
        }

        return null;
    }
}
