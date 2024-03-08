<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Armin Vieweg
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Tpwd\KeSearch\UserFunction\CustomFieldValidation;

use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Validates given filter option tag by applying SearchHelper::makeTag().
 * Checks length, which may not smaller than basic.searchWordLength extension (and MySQL) setting.
 * Checks if it contains not allowed characters.
 * Checks if it is in the list of stop words.
 */
class FilterOptionTagValidator
{
    /**
     * @param string $value
     * @return string Updated string, which fits the requirements
     */
    public function evaluateFieldValue(string $value): string
    {
        $tag = SearchHelper::makeTag($value, false);

        if ($value != $tag) {
            if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 12) {
                // @extensionScannerIgnoreLine
                $severity = AbstractMessage::INFO;
            } else {
                // @phpstan-ignore-next-line
                $severity = ContextualFeedbackSeverity::INFO;
            }
            /** @var FlashMessage $message */
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->translate('tag_has_been_updated_message', [$value, $tag]),
                $this->translate('tag_has_been_updated'),
                $severity,
                true
            );

            /** @var FlashMessageService $flashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            // @extensionScannerIgnoreLine
            $flashMessageService->getMessageQueueByIdentifier()->addMessage($message);
        }

        return $tag;
    }

    /**
     * JavaScript validation
     *
     * @return string javascript function code for js validation
     */
    public function returnFieldJs()
    {
        return 'return value;';
    }

    /**
     * Returns the translation of current language
     *
     * @param string $key
     * @param array $arguments optional arguments
     * @return string Translated text
     */
    protected function translate($key, array $arguments = [])
    {
        return LocalizationUtility::translate(
            'LLL:EXT:ke_search/Resources/Private/Language/locallang_mod.xlf:' . $key,
            'KeSearch',
            $arguments
        );
    }
}
