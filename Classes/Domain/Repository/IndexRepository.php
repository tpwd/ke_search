<?php

namespace Tpwd\KeSearch\Domain\Repository;

use Doctrine\DBAL\Driver\Statement;
use PDO;
use TYPO3\CMS\Core\Database\Connection;
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
class IndexRepository
{
    public const IGNORE_COLS_FOR_SORTING =
        'uid,pid,tstamp,crdate,cruser_id,starttime,endtime'
        . ',fe_group,targetpid,content,hidden_content,params,type,tags,abstract,language'
        . ',orig_uid,orig_pid,hash,lat,lon,externalurl,lastremotetransfer';

    protected string $tableName = 'tx_kesearch_index';
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param int $uid
     * @return mixed
     */
    public function findByUid(int $uid)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
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
     * @param string $hash
     * @return mixed
     */
    public function findOneByHashAndModificationTime(string $hash, $mtime)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'hash',
                    $queryBuilder->quote($hash, \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'sortdate',
                    $queryBuilder->quote($mtime, \PDO::PARAM_INT)
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @param int $uid
     * @param array $updateFields
     * @return mixed
     */
    public function update(int $uid, array $updateFields)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder
            ->update($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                )
            );
        foreach ($updateFields as $key => $value) {
            $queryBuilder->set($key, $value);
        }
        return $queryBuilder->executeStatement();
    }

    /**
     * @return int
     */
    public function getTotalNumberOfRecords(): int
    {
        return $this->connection->createQueryBuilder()
            ->count('*')
            ->from($this->tableName)
            ->executeQuery()
            ->fetchNumeric()[0];
    }

    /**
     * returns number of records per type in an array
     *
     * @return array
     */
    public function getNumberOfRecordsInIndexPerType(): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $typeCount = $queryBuilder
            ->select('type')
            ->addSelectLiteral(
                $queryBuilder->expr()->count('tx_kesearch_index.uid', 'count')
            )
            ->from('tx_kesearch_index')
            ->groupBy('tx_kesearch_index.type')
            ->executeQuery();

        $resultsPerType = [];
        while ($row = $typeCount->fetchAssociative()) {
            $resultsPerType[$row['type']] = $row['count'];
        }

        return $resultsPerType;
    }

    /**
     * @param int $uid
     * @return Statement|int
     */
    public function deleteByUid(int $uid)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->delete($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    /**
     * Deletes records from the index which can be clearly identified by the properties $orig_uid, $pid, $type and $language.
     * Uses the same properties as IndexerRunner->checkIfRecordWasIndexed()
     *
     * @param int $origUid
     * @param int $pid
     * @param string $type
     * @param int $language
     * @return Statement|int
     */
    public function deleteByUniqueProperties(int $origUid, int $pid, string $type, int $language)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->delete($this->tableName)
            ->where(
                $queryBuilder->expr()->eq('orig_uid', $queryBuilder->createNamedParameter($origUid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter($type)),
                $queryBuilder->expr()->eq('language', $queryBuilder->createNamedParameter($language, PDO::PARAM_INT))
            )
            ->executeStatement();
    }

    /**
     * Deletes the corresponding index records for a record which has been indexed.
     *
     * @param string $type type as stored in the index table, eg. "page", "news", "file", "tt_address" etc.
     * @param array $records array of the record rows
     * @param array $indexerConfig the indexerConfig for which the index record should be removed
     */
    public function deleteCorrespondingIndexRecords(string $type, array $records, array $indexerConfig)
    {
        $count = 0;
        if (!empty($records)) {
            foreach ($records as $record) {
                if ($type == 'page') {
                    $origUid = ($record['sys_language_uid'] > 0) ? $record['l10n_parent'] : $record['uid'];
                } else {
                    $origUid = $record['uid'];
                }
                $this->deleteByUniqueProperties(
                    $origUid,
                    $indexerConfig['storagepid'],
                    $type,
                    $record['sys_language_uid']
                );
                $count++;
            }
        }
        return $count;
    }

    public function truncate(): void
    {
        $this->connection->truncate('tx_kesearch_index');
    }

    /**
     * @param int $pid
     * @param int $timestamp
     * @return mixed
     */
    public function findOutdatedFileRecordsByPidAndTimestamp(int $pid, int $timestamp)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->like(
                    'type',
                    $queryBuilder->createNamedParameter('file%')
                ),
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->lt(
                    'tstamp',
                    $queryBuilder->createNamedParameter($timestamp, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns a list of columns which are relevant as columns for sorting. Takes out system columns, like crdate,
     * tstamp etc. but includes custom columns.
     *
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    public function getColumnsRelevantForSorting(): array
    {
        $result = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM tx_kesearch_index');
        foreach ($result as $key => $col) {
            if (GeneralUtility::inList(self::IGNORE_COLS_FOR_SORTING, $col['Field'])) {
                unset($result[$key]);
            }
        }
        return $result;
    }

    /**
     * Returns a list of records which can be used to show the indexed content for the given page in the backend
     * module. Index records are returned if they are either stored on the given page or, in case the index record is
     * of type "page", the targetpid is the given page.
     *
     * @param int $pageUid
     * @return mixed
     */
    public function findByPageUidToShowIndexedContent(int $pageUid)
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter('page')),
                $queryBuilder->expr()->eq('targetpid', (int)$pageUid)
            )
            ->orWhere(
                $queryBuilder->expr()->neq('type', $queryBuilder->createNamedParameter('page')) .
                ' AND ' .
                $queryBuilder->expr()->eq('pid', (int)$pageUid)
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
