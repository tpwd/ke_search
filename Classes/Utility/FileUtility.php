<?php

namespace Tpwd\KeSearch\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Christian Bülter <ke_search@tpwd.de>
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

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileUtility
{
    /**
     * @param File $file
     * @param array $indexerConfig
     * @return bool
     */
    public static function isFileIndexable(File $file, array $indexerConfig): bool
    {
        $isExcludedFromSearch =
            $file->hasProperty('tx_kesearch_no_search')
            && ((int)$file->getProperty('tx_kesearch_no_search') === 1);

        $isInList = GeneralUtility::inList(
            $indexerConfig['fileext'],
            $file->getExtension()
        );

        return !$isExcludedFromSearch && $isInList;
    }
}
