<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Repository;

use PDO;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *  (c) 2021 Christian Bülter
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
class BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = '';

    public function getQueryBuilder(bool $includeHiddenAndTimeRestricted = false): QueryBuilder
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable($this->tableName);
        if ($includeHiddenAndTimeRestricted) {
            $queryBuilder
                ->getRestrictions()
                ->removeByType(HiddenRestriction::class)
                ->removeByType(StartTimeRestriction::class)
                ->removeByType(EndTimeRestriction::class);
        }
        return $queryBuilder;
    }

    /**
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed
     */
    public function findAll(bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param $uid
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed
     */
    public function findByUid($uid, bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @param array $pidList
     * @param int $tstamp
     * @return mixed[]
     */
    public function findAllDeletedAndHiddenByPidListAndTimestampInAllLanguages(array $pidList, int $tstamp)
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable($this->tableName);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->orWhere(
                $queryBuilder->expr()->eq('deleted', 1),
                $queryBuilder->expr()->eq('hidden', 1)
            )
            ->andWhere(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pidList, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($tstamp, PDO::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
