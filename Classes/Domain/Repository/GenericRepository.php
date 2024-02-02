<?php

namespace Tpwd\KeSearch\Domain\Repository;

use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

/**
 * @author Christian Bülter
 */
class GenericRepository
{
    /**
     * Tries to find a table matching the type, either by checking hardcoded values or if the type is the same
     * as the table name.
     * Returns the record with the given uid.
     *
     * @param int|string $uid
     * @param string $type
     * @return false|mixed
     */
    public function findByUidAndType($uid, string $type)
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return false;
        }

        $row = false;
        $table = '';
        $type = (substr($type, 0, 4) == 'file') ? 'file' : $type;
        switch ($type) {
            case 'page':
                $table = 'pages';
                break;
            case 'news':
                $table = 'tx_news_domain_model_news';
                break;
            case 'file':
                $table = 'sys_file';
                break;
            default:
                // check if a table exists that matches the type name
                $tableNameToCheck = strip_tags(htmlentities($type));
                if ($this->tableExists($tableNameToCheck)) {
                    $table = $tableNameToCheck;
                }
        }
        // hook to add a custom types
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['GenericRepositoryTablename'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_search']['GenericRepositoryTablename'] as $_classRef) {
                $_procObj = GeneralUtility::makeInstance($_classRef);
                $table = $_procObj->getTableName($type);
            }
        }
        if (!empty($table)) {
            $queryBuilder = $this->getQueryBuilder($table);
            $row = $queryBuilder
                ->select('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                    )
                )
                ->executeQuery()
                ->fetchAssociative();
        }
        return $row;
    }

    /**
     * @param string $table
     * @param int $uid
     * @param int $languageId
     * @return array|null
     */
    public function findLangaugeOverlayByUidAndLanguage(string $table, int $uid, int $languageId)
    {
        $overlayRecord = null;
        $transOrigPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null;
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null;

        if (!empty($transOrigPointerField) && !empty($languageField)) {
            $queryBuilder = $this->getQueryBuilder($table);
            $overlayRecord = $queryBuilder
                ->select('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq(
                        $transOrigPointerField,
                        $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        $languageField,
                        $queryBuilder->createNamedParameter($languageId, PDO::PARAM_INT)
                    )
                )
                ->executeQuery()
                ->fetchAssociative();
        }
        return $overlayRecord;
    }

    public function getQueryBuilder(string $table, bool $includeHiddenAndTimeRestricted = false): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);
        if ($includeHiddenAndTimeRestricted) {
            $queryBuilder
                ->getRestrictions()
                ->removeByType(HiddenRestriction::class)
                ->removeByType(StartTimeRestriction::class)
                ->removeByType(EndTimeRestriction::class);
        }
        return $queryBuilder;
    }

    public function findByReferenceField(
        string $table,
        string $fieldName,
        int $value,
        bool $includeHiddenAndTimeRestricted = false
    ) {
        $queryBuilder = $this->getQueryBuilder($table, $includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $fieldName,
                    $queryBuilder->createNamedParameter($value, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function tableExists(string $table): bool
    {
        $table = strip_tags(htmlentities($table));
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable($table);
        $statement = $connection->prepare('SHOW TABLES LIKE "' . $table . '"');
        $result = $statement->executeQuery();
        if ($result->rowCount()) {
            return true;
        }
        return false;
    }
}
