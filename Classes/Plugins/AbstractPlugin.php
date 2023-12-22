<?php

namespace Tpwd\KeSearch\Plugins;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Derived of the old piBase class typo3/sysext/frontend/Classes/Plugin/AbstractPlugin.php
 * which is planned to be removed in TYPO3 13.
 *
 * Since ke_search still relies on some of the functions provided by the AbstractPlugin class
 * these are made available here.
 *
 * The class only contains the necessary functions and properties (mainly related to language and FlexForms),
 * not including functions for database access, template based markers, links, listing, browsing or menu generation.
 */
class AbstractPlugin
{
    protected ?ContentObjectRenderer $cObj = null;

    /**
     * Should be same as classname of the plugin, used for CSS classes, variables
     *
     * @var string
     */
    public string $prefixId = '';

    /**
     * Path to the plugin class script relative to extension directory, eg. 'pi1/class.tx_newfaq_pi1.php'
     *
     * @var string
     */
    public string $scriptRelPath = '';

    /**
     * Extension key.
     *
     * @var string
     */
    public string $extKey;

    /**
     * Local Language content
     *
     * @var array
     */
    public array $LOCAL_LANG = [];

    /**
     * Contains those LL keys, which have been set to (empty) in TypoScript.
     * This is necessary, as we cannot distinguish between a nonexisting
     * translation and a label that has been cleared by TS.
     * In both cases ['key'][0]['target'] is "".
     *
     * @var array
     */
    protected array $LOCAL_LANG_UNSET = [];

    /**
     * Flag that tells if the locallang file has been fetch (or tried to
     * be fetched) already.
     *
     * @var bool
     */
    public bool $LOCAL_LANG_loaded = false;

    /**
     * Pointer to the language to use.
     *
     * @var string
     */
    public string $LLkey = 'default';

    /**
     * Pointer to alternative fall-back language to use.
     *
     * @var string
     */
    public string $altLLkey = '';

    /**
     * Should normally be set in the main function with the TypoScript content passed to the method.
     *
     * $conf[LOCAL_LANG][_key_] is reserved for Local Language overrides.
     * $conf[userFunc] reserved for setting up the USER / USER_INT object. See TSref
     *
     * @var array
     */
    public array $conf = [];

    /**
     * Property for accessing TypoScriptFrontendController centrally
     *
     * @var TypoScriptFrontendController|null
     */
    protected ?TypoScriptFrontendController $frontendController;

    /**
     * Class Constructor (true constructor)
     * Will also set $this->LLkey based on the "config.language" setting.
     *
     * @param null $_ unused
     * @param ?TypoScriptFrontendController $frontendController
     *
     * PHPStan complains about the unused parameter $_
     * @phpstan-ignore-next-line
     */
    public function __construct($_ = null, TypoScriptFrontendController $frontendController = null)
    {
        $this->frontendController = $frontendController ?: $GLOBALS['TSFE'];
        $this->LLkey = $this->frontendController->getLanguage()->getTypo3Language();

        $locales = GeneralUtility::makeInstance(Locales::class);
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            if (in_array($this->LLkey, $locales->getLocales())) {
                foreach ($locales->getLocaleDependencies($this->LLkey) as $language) {
                    $this->altLLkey .= $language . ',';
                }
                $this->altLLkey = rtrim($this->altLLkey, ',');
            }
        } else {
            if ($locales->isValidLanguageKey($this->LLkey)) {
                $alternativeLanguageKeys = $locales->getLocaleDependencies($this->LLkey);
                $alternativeLanguageKeys = array_reverse($alternativeLanguageKeys);
                $this->altLLkey = implode(',', $alternativeLanguageKeys);
            }
        }
    }

    /**
     * This setter is called when the plugin is called from UserContentObject (USER)
     * via ContentObjectRenderer->callUserFunction().
     */
    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * Returns the row $uid from $table
     * (Simply calling $this->frontendEngine->sys_page->checkRecord())
     *
     * @param string $table The table name
     * @param int $uid The uid of the record from the table
     * @param bool $checkPage If $checkPage is set, it's required that the page on which the record resides is accessible
     * @return array|int If record is found, an array. Otherwise, 0.
     */
    public function pi_getRecord(string $table, int $uid, bool $checkPage = false)
    {
        // @extensionScannerIgnoreLine
        return $this->frontendController->sys_page->checkRecord($table, $uid, $checkPage);
    }

    /**
     * Returns a comma list of page ids for a query (e.g. 'WHERE pid IN (...)')
     *
     * @param string $pid_list A comma list of page ids (if empty current page is used)
     * @param int $recursive An integer >=0 telling how deep to dig for pids under each entry in $pid_list
     * @return string List of PID values (comma separated)
     */
    public function pi_getPidList(string $pid_list, int $recursive = 0): string
    {
        if (!strcmp($pid_list, '')) {
            $pid_list = (string)$this->frontendController->id;
        }
        $recursive = MathUtility::forceIntegerInRange($recursive, 0);
        $pid_list_arr = array_unique(GeneralUtility::intExplode(',', $pid_list, true));
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            $pid_list = [];
            foreach ($pid_list_arr as $val) {
                $val = MathUtility::forceIntegerInRange($val, 0);
                if ($val) {
                    // @extensionScannerIgnoreLine
                    $_list = $this->cObj->getTreeList(-1 * $val, $recursive);
                    if ($_list) {
                        $pid_list[] = $_list;
                    }
                }
            }
        } else {
            $pid_list = GeneralUtility::makeInstance(PageRepository::class)->getPageIdsRecursive($pid_list_arr, $recursive);
        }
        return implode(',', $pid_list);
    }

    /**
     * Wraps the input string in a <div> tag with the class attribute set to the prefixId.
     * All content returned from your plugins should be returned through this function so all content from your plugin is encapsulated in a <div>-tag nicely identifying the content of your plugin.
     *
     * @param string $str HTML content to wrap in the div-tags with the "main class" of the plugin
     * @return string HTML content wrapped, ready to return to the parent object.
     */
    public function pi_wrapInBaseClass(string $str): string
    {
        $content = '<div class="' . str_replace('_', '-', $this->prefixId) . '">
		' . $str . '
	</div>
	';
        if (!($this->frontendController->config['config']['disablePrefixComment'] ?? false)) {
            $content = '


	<!--

		BEGIN: Content of extension "' . $this->extKey . '", plugin "' . $this->prefixId . '"

	-->
	' . $content . '
	<!-- END: Content of extension "' . $this->extKey . '", plugin "' . $this->prefixId . '" -->

	';
        }
        return $content;
    }

    /***************************
     *
     * Localization, locallang functions
     *
     **************************/
    /**
     * Returns the localized label of the LOCAL_LANG key, $key
     * Notice that for debugging purposes prefixes for the output values can be set with the internal vars ->LLtestPrefixAlt and ->LLtestPrefix
     *
     * @param string $key The key from the LOCAL_LANG array for which to return the value.
     * @param string $alternativeLabel Alternative string to return IF no value is found set for the key, neither for the local language nor the default.
     * @return string The value from LOCAL_LANG.
     */
    public function pi_getLL(string $key, string $alternativeLabel = ''): ?string
    {
        $word = null;
        if (!empty($this->LOCAL_LANG[$this->LLkey][$key][0]['target'])
            || isset($this->LOCAL_LANG_UNSET[$this->LLkey][$key])
        ) {
            $word = $this->LOCAL_LANG[$this->LLkey][$key][0]['target'];
        } elseif ($this->altLLkey) {
            $alternativeLanguageKeys = GeneralUtility::trimExplode(',', $this->altLLkey, true);
            foreach ($alternativeLanguageKeys as $languageKey) {
                if (!empty($this->LOCAL_LANG[$languageKey][$key][0]['target'])
                    || isset($this->LOCAL_LANG_UNSET[$languageKey][$key])
                ) {
                    // Alternative language translation for key exists
                    $word = $this->LOCAL_LANG[$languageKey][$key][0]['target'];
                    break;
                }
            }
        }
        if ($word === null) {
            if (!empty($this->LOCAL_LANG['default'][$key][0]['target'])
                || isset($this->LOCAL_LANG_UNSET['default'][$key])
            ) {
                // Get default translation (without charset conversion, english)
                $word = $this->LOCAL_LANG['default'][$key][0]['target'];
            } else {
                // Return alternative string or empty
                $word = !empty($this->LLtestPrefixAlt) ? $this->LLtestPrefixAlt . $alternativeLabel : $alternativeLabel;
            }
        }
        return !empty($this->LLtestPrefix) ? $this->LLtestPrefix . $word : $word;
    }

    /**
     * Loads local-language values from the file passed as a parameter or
     * by looking for a "locallang" file in the
     * plugin class directory ($this->scriptRelPath).
     * Also, locallang values set in the TypoScript property "_LOCAL_LANG" are
     * merged onto the values found in the "locallang" file.
     * Supported file extensions xlf
     *
     * @param string $languageFilePath path to the plugin language file in format EXT:....
     */
    public function pi_loadLL(string $languageFilePath = '')
    {
        if ($this->LOCAL_LANG_loaded) {
            return;
        }

        if ($languageFilePath === '' && $this->scriptRelPath) {
            $languageFilePath = 'EXT:' . $this->extKey . '/' . PathUtility::dirname($this->scriptRelPath) . '/locallang.xlf';
        }
        if ($languageFilePath !== '') {
            $languageFactory = GeneralUtility::makeInstance(LocalizationFactory::class);
            $this->LOCAL_LANG = $languageFactory->getParsedData($languageFilePath, $this->LLkey);
            $alternativeLanguageKeys = GeneralUtility::trimExplode(',', $this->altLLkey, true);
            foreach ($alternativeLanguageKeys as $languageKey) {
                $tempLL = $languageFactory->getParsedData($languageFilePath, $languageKey);
                if ($this->LLkey !== 'default' && isset($tempLL[$languageKey])) {
                    $this->LOCAL_LANG[$languageKey] = $tempLL[$languageKey];
                }
            }
            // Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
            if (isset($this->conf['_LOCAL_LANG.'])) {
                // Clear the "unset memory"
                $this->LOCAL_LANG_UNSET = [];
                foreach ($this->conf['_LOCAL_LANG.'] as $languageKey => $languageArray) {
                    // Remove the dot after the language key
                    $languageKey = substr($languageKey, 0, -1);
                    // Don't process label if the language is not loaded
                    if (is_array($languageArray) && isset($this->LOCAL_LANG[$languageKey])) {
                        foreach ($languageArray as $labelKey => $labelValue) {
                            if (!is_array($labelValue)) {
                                $this->LOCAL_LANG[$languageKey][$labelKey][0]['target'] = $labelValue;
                                if ($labelValue === '') {
                                    $this->LOCAL_LANG_UNSET[$languageKey][$labelKey] = '';
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->LOCAL_LANG_loaded = true;
    }

    /**
     * Will process the input string with the parseFunc function from ContentObjectRenderer based on configuration
     * set in "lib.parseFunc_RTE" in the current TypoScript template.
     *
     * @param string $str The input text string to process
     * @return string The processed string
     * @see ContentObjectRenderer::parseFunc()
     */
    public function pi_RTEcssText(string $str): string
    {
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
            // @phpstan-ignore-next-line
            $str = $this->cObj->parseFunc($str, [], '< lib.parseFunc_RTE');
        } else {
            // @phpstan-ignore-next-line
            $str = $this->cObj->parseFunc($str, null, '< lib.parseFunc_RTE');
        }
        return $str;
    }
    /*******************************
     *
     * FlexForms related functions
     *
     *******************************/
    /**
     * Converts $this->cObj->data['pi_flexform'] from XML string to flexForm array.
     *
     * @param string $field Field name to convert
     */
    public function pi_initPIflexForm(string $field = 'pi_flexform')
    {
        // Converting flexform data into array
        $fieldData = $this->cObj->data[$field] ?? null;
        if (!is_array($fieldData) && $fieldData) {
            $this->cObj->data[$field] = GeneralUtility::xml2array((string)$fieldData);
            if (!is_array($this->cObj->data[$field])) {
                $this->cObj->data[$field] = [];
            }
        }
    }

    /**
     * Return value from somewhere inside a FlexForm structure
     *
     * @param array $T3FlexForm_array FlexForm data
     * @param string $fieldName Field name to extract. Can be given like "test/el/2/test/el/field_templateObject" where each part will dig a level deeper in the FlexForm data.
     * @param string $sheet Sheet pointer, eg. "sDEF
     * @param string $lang Language pointer, eg. "lDEF
     * @param string $value Value pointer, eg. "vDEF
     * @return string|null The content.
     */
    public function pi_getFFvalue(
        array $T3FlexForm_array,
        string $fieldName,
        string $sheet = 'sDEF',
        string $lang = 'lDEF',
        string $value = 'vDEF'
    ): ?string {
        $sheetArray = $T3FlexForm_array['data'][$sheet][$lang] ?? '';
        if (is_array($sheetArray)) {
            return $this->pi_getFFvalueFromSheetArray($sheetArray, explode('/', $fieldName), $value);
        }
        return null;
    }

    /**
     * Returns part of $sheetArray pointed to by the keys in $fieldNameArray
     *
     * @param array $sheetArray Multidimensional array, typically FlexForm contents
     * @param array $fieldNameArr Array where each value points to a key in the FlexForms content - the input array will have the value returned pointed to by these keys. All integer keys will not take their integer counterparts, but rather traverse the current position in the array and return element number X (whether this is right behavior is not settled yet...)
     * @param string $value Value for outermost key, typ. "vDEF" depending on language.
     * @return mixed The value, typ. string.
     * @internal
     * @see pi_getFFvalue()
     */
    public function pi_getFFvalueFromSheetArray(array $sheetArray, array $fieldNameArr, string $value)
    {
        $tempArr = $sheetArray;
        foreach ($fieldNameArr as $k => $v) {
            if (MathUtility::canBeInterpretedAsInteger($v)) {
                if (is_array($tempArr)) {
                    $c = 0;
                    foreach ($tempArr as $values) {
                        if ($c == $v) {
                            $tempArr = $values;
                            break;
                        }
                        $c++;
                    }
                }
            } elseif (isset($tempArr[$v])) {
                $tempArr = $tempArr[$v];
            }
        }
        return $tempArr[$value] ?? '';
    }
}
