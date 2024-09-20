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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Derived of the old piBase class typo3/sysext/frontend/Classes/Plugin/AbstractPlugin.php
 *
 * Since ke_search still relies on some of the functions provided by the AbstractPlugin class
 * these are made available here.
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
     * Extension key.
     *
     * @var string
     */
    public string $extKey;

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
        $pid_list = GeneralUtility::makeInstance(PageRepository::class)->getPageIdsRecursive($pid_list_arr, $recursive);
        return implode(',', $pid_list);
    }

    /**
     * Wraps the input string in a <div> tag with the class attribute set to the prefixId.
     *
     * @param string $str HTML content to wrap in the div-tags with the "main class" of the plugin
     * @return string HTML content wrapped, ready to return to the parent object.
     */
    public function pi_wrapInBaseClass(string $str): string
    {
        $content = '<div class="' . str_replace('_', '-', $this->prefixId) . '">' . $str . '</div>';
        return $content;
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
        return $this->cObj->parseFunc($str, null, '< lib.parseFunc_RTE');
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
