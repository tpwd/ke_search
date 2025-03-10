<?php

declare(strict_types=1);

/***************************************************************
 *  Copyright notice
 *  (c) 2020 Christian Bülter
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

namespace Tpwd\KeSearch\Updates;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Class MakeTagsAlphanumericUpgradeWizard
 */
#[UpgradeWizard('keSearchMakeTagsAlphanumericUpgradeWizard')]
class MakeTagsAlphanumericUpgradeWizard implements UpgradeWizardInterface
{
    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'Make ke_search tags alphanumeric';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Removes non-alphanumeric characters from tags in filter options. Please re-index after running this upgrade wizard.';
    }

    /**
     * @return bool
     */
    public function executeUpdate(): bool
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_kesearch_filteroptions');
        $statement = $connection->prepare('SELECT uid,tag FROM tx_kesearch_filteroptions WHERE tag REGEXP "[^a-z0-9]+"');
        $result = $statement->executeQuery();
        $filterOptionRows = $result->fetchAllAssociative();
        if (!empty($filterOptionRows)) {
            foreach ($filterOptionRows as $filterOptionRow) {
                $query = $connectionPool->getQueryBuilderForTable('tx_kesearch_filteroptions');
                $query
                    ->update('tx_kesearch_filteroptions')
                    ->where($query->expr()->eq('uid', $query->createNamedParameter($filterOptionRow['uid'], Connection::PARAM_INT)))
                    ->set('tag', preg_replace('/[^A-Za-z0-9]/', '', $filterOptionRow['tag']))
                    ->executeStatement();
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    public function updateNecessary(): bool
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable('tx_kesearch_filteroptions');
        $statement = $connection->prepare('SELECT COUNT(*) FROM tx_kesearch_filteroptions WHERE tag REGEXP "[^a-z0-9]+"');
        $result = $statement->executeQuery();
        $countResult = $result->fetchNumeric();
        return !empty($countResult) && $countResult[0];
    }

    /**
     * Returns an array of class names of prerequisite classes
     *
     * This way a wizard can define dependencies like "database up-to-date" or
     * "reference index updated"
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
