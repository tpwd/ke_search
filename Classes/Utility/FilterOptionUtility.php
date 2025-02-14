<?php

namespace Tpwd\KeSearch\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class FilterOptionUtility
{
    public static function getPlainTagsFromIndexRecordTags(string $tags): array
    {
        $plainTags = [];
        $tagArray = GeneralUtility::trimExplode(',', $tags, true);

        foreach ($tagArray as $tag) {
            $tag = substr($tag, 1, -1); // Remove the first and last character
            $plainTags[] = $tag;
        }

        return $plainTags;
    }
}
