<?php

namespace Tpwd\KeSearch\Lib;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/***************************************************************
 *  Copyright notice
 *  (c) 2010 Stefan Froemken
 *  All rights reserved
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Plugin 'Faceted search - searchbox and filters' for the 'ke_search' extension.
 * @author    Stefan Froemken
 */
class Searchresult
{
    private array $row = [];
    private array $swords = [];
    private array $conf = [];
    private array $extConfPremium;

    public function __construct()
    {
        $this->extConfPremium = SearchHelper::getExtConfPremium();
    }

    /**
     * Sets the plugin configuration (from the FlexForm configuration)
     *
     * @param array $pluginConfiguration
     */
    public function setPluginConfiguration(array $pluginConfiguration)
    {
        $this->conf = $pluginConfiguration;
    }

    /**
     * Sets the search word array
     *
     * @param array $swords
     */
    public function setSwords(array $swords)
    {
        $this->swords = $swords;
    }

    /**
     * set row array with current result element
     * @param array $row
     */
    public function setRow(array $row)
    {
        $this->row = $row;
    }

    /**
     * get title for result row
     * @return string The linked result title
     */
    public function getTitle(): string
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        // configure the link
        $linkconf = $this->getResultLinkConfiguration();

        list($type) = explode(':', $this->row['type']);
        switch ($type) {
            case 'file':
                // if we use FAL, see if we have a title in the metadata
                if ($this->row['orig_uid'] && ($fileObject = SearchHelper::getFile($this->row['orig_uid']))) {
                    $metadata = $fileObject->getMetaData()->get();
                    $linktext = ($metadata['title'] ?: $this->row['title']);
                } else {
                    $linktext = $this->row['title'];
                }
                break;
            default:
                $linktext = $this->row['title'];
                break;
        }

        // clean title
        $linktext = htmlspecialchars(strip_tags($linktext));

        // highlight hits in result title?
        if (($this->conf['highlightSword'] ?? '') && count($this->swords)) {
            $linktext = $this->highlightArrayOfWordsInContent($this->swords, $linktext);
        }
        return $cObj->typoLink($linktext, $linkconf);
    }

    /**
     * get result url (not) linked
     * @return string The results URL
     */
    public function getResultUrl($linked = false): string
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $linkText = $cObj->typoLink_URL($this->getResultLinkConfiguration());
        $linkText = htmlspecialchars($linkText);
        if ($linked) {
            return $cObj->typoLink($linkText, $this->getResultLinkConfiguration());
        }
        return $linkText;
    }

    /**
     * get result link configuration
     * It can devide between the result types (file, page, content)
     *
     * @return array configuration for typolink
     */
    public function getResultLinkConfiguration(): array
    {
        return SearchHelper::getResultLinkConfiguration(
            $this->row,
            $this->conf['resultLinkTarget'],
            $this->conf['resultLinkTargetFiles']
        );
    }

    /**
     * get teaser for result list
     *
     * @return string The teaser
     */
    public function getTeaser(): string
    {
        $content = $this->getContentForTeaser();
        return $this->buildTeaserContent($content);
    }

    /**
     * get content for teaser
     * This can be the abstract or content col
     *
     * @return string The content
     */
    public function getContentForTeaser(): string
    {
        $content = $this->row['content'];
        if (!empty($this->row['abstract'])) {
            $content = nl2br($this->row['abstract']);
            if ($this->conf['previewMode'] == 'hit') {
                if (!$this->isArrayOfWordsInString($this->swords, $this->row['abstract'])) {
                    $content = $this->row['content'];
                }
            }
        }
        return $content;
    }

    /**
     * check if an array with words was found in given content
     * @param array $wordArray A single dimmed Array containing words
     * to search for. F.E. array('hello', 'georg', 'company')
     * @param string $content The string to search in
     * @param bool $checkAll If this is checked, then all words have to be found in string.
     * If false: The method returns true directly, if one of the words was found
     * @return bool Returns true if the word(s) are found
     */
    public function isArrayOfWordsInString(array $wordArray, string $content, bool $checkAll = false): bool
    {
        $found = false;
        foreach ($wordArray as $word) {
            if (mb_stripos($content, $word) === false) {
                $found = false;
                if ($checkAll === true) {
                    return false;
                }
            } else {
                $found = true;
                if ($checkAll === false) {
                    return true;
                }
            }
        }
        return $found;
    }

    /**
     * Find and highlight the searchwords
     *
     * @param array $wordArray
     * @param string $content
     * @return string The content with highlighted searchwords
     */
    public function highlightArrayOfWordsInContent(array $wordArray, string $content): string
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        if (count($wordArray)) {
            $highlightedWord = (!empty($this->conf['highlightedWord_stdWrap'])) ?
                $cObj->stdWrap('\0', $this->conf['highlightedWord_stdWrap']) :
                '<span class="hit">\0</span>';

            foreach ($wordArray as $word) {
                $word = preg_quote($word, '/');
                $word = htmlspecialchars($word);
                // Highlight hits within words when using ke_seaarch_premium "in word search"
                if (
                    (ExtensionManagementUtility::isLoaded('ke_search_premium') && ($this->extConfPremium['enableSphinxSearch'] ?? false) && (int)($this->extConfPremium['enableInWordSearch'] ?? false))
                    ||
                    (ExtensionManagementUtility::isLoaded('ke_search_premium') && ($this->extConfPremium['enableNativeInWordSearch'] ?? false))
                ) {
                    $pattern = '/(' . $word . ')/iu';
                } else {
                    $pattern = '/\b(' . $word . ')/iu';
                }
                $content = preg_replace($pattern, $highlightedWord, $content);
            }
        }
        return $content;
    }

    /**
     * Build Teasercontent
     *
     * @param string $content The whole resultcontent
     * @return string The cutted recultcontent
     */
    public function buildTeaserContent(string $content): string
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $content = strip_tags($content);
        if (count($this->swords)) {
            $amountOfSearchWords = count($this->swords);
            // with each new searchword and all the croppings here the teaser for each word will become too small/short
            // I decided to add 20 additional letters for each searchword. It looks much better and is more readable
            $charsForEachSearchWord = ceil($this->conf['resultChars'] / $amountOfSearchWords) + 20;
            $charsBeforeAfterSearchWord = ceil($charsForEachSearchWord / 2);
            $aSearchWordWasFound = false;
            $isSearchWordAtTheBeginning = false;
            $teaserArray = [];
            foreach ($this->swords as $word) {
                // Always remove whitespace around searchword first
                $word = trim($word);

                // Check teaser text array first to avoid duplicate text parts
                if (count($teaserArray) > 0) {
                    foreach ($teaserArray as $teaserArrayItem) {
                        $searchWordPositionInTeaserArray = mb_stripos($teaserArrayItem, $word);
                        if ($searchWordPositionInTeaserArray === false) {
                            continue;
                        }
                        // One finding in teaser text array is sufficient
                        $aSearchWordWasFound = true;
                        break;
                    }
                }

                // Only search for current search word in content if it wasn't found in teaser text array already
                if ($aSearchWordWasFound === false) {
                    $pos = mb_stripos($content, $word);
                    if ($pos === false) {
                        continue;
                    }
                    $aSearchWordWasFound = true;

                    // if search word is the first word
                    if ($pos === 0) {
                        $isSearchWordAtTheBeginning = true;
                    }

                    // find search starting point
                    $startPos = $pos - $charsBeforeAfterSearchWord;
                    if ($startPos < 0) {
                        $startPos = 0;
                    }

                    // crop some words behind searchword
                    $partWithSearchWord = mb_substr($content, $startPos);
                    $temp = $cObj->crop($partWithSearchWord, $charsForEachSearchWord . '|…|1');

                    // crop some words before search word
                    // after last cropping our text is too short now. So we have to find a new cutting position
                    ($startPos > 10) ? $length = strlen($temp) - 10 : $length = strlen($temp);

                    // Store content part containing the search word in teaser text array
                    $teaserArray[] = $cObj->crop($temp, '-' . $length . '||1');
                }
            }

            // When the searchword was found in title but not in content the teaser is empty
            // in that case we have to get the first x letters without containing any searchword
            if ($aSearchWordWasFound === false) {
                $teaser = $cObj->crop($content, $this->conf['resultChars'] . '||1');
            } elseif ($isSearchWordAtTheBeginning === true) {
                $teaser = implode(' ', $teaserArray);
            } else {
                $teaser = '…' . implode(' ', $teaserArray);
            }

            // highlight hits?
            if ($this->conf['highlightSword'] ?? '') {
                $teaser = $this->highlightArrayOfWordsInContent($this->swords, $teaser);
            }
            return $teaser;
        }
        return $cObj->crop($content, $this->conf['resultChars'] ?? 0 . '|…|1');
    }
}
