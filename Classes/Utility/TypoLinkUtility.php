<?php

namespace Tpwd\KeSearch\Utility;

class TypoLinkUtility
{
    /**
     * Extracts the page UID from a typolink string
     *
     * @param string $typolink The typolink string (e.g., "t3://page?uid=123")
     * @return int|null The extracted page UID or null if parsing fails
     */
    static public function extractPageUidFromTypoLink(string $typolink): ?int
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
