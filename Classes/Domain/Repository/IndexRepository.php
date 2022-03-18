<?php
namespace Tpwd\KeSearch\Domain\Repository;

use Doctrine\DBAL\Driver\Statement;
use PDO;
use TYPO3\CMS\Core\Database\Connection;

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
 * @package TYPO3
 * @subpackage ke_search
 */
class IndexRepository {
    /**
     * @var string
     */
    protected $tableName = 'tx_kesearch_index';
    /**
     * @var Connection
     */
    private $connection;

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
            ->execute()
            ->fetch();
    }

    /**
     * @param string $hash
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
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
            ->execute()
            ->fetch();
    }

    /**
     * @param integer $uid
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
        return $queryBuilder->execute();
    }

    /**
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getTotalNumberOfRecords(): int
    {
        return $this->connection->createQueryBuilder()
            ->count('*')
            ->from('tx_kesearch_index')
            ->execute()
            ->fetchColumn(0);
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
            ->execute();

        $resultsPerType = [];
        while ($row = $typeCount->fetch()) {
            $resultsPerType[$row['type']] = $row['count'];
        }

        return $resultsPerType;
    }

    /**
     * @param int $filterOptionUid
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
            ->execute();
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
            ->execute();
    }

    /**
     * Deletes the corresponding index records for a record which has been indexed.
     *
     * @param string $type type as stored in the index table, eg. "page", "news", "file", "tt_address" etc.
     * @param array $record array of the record rows
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
     * @throws \Doctrine\DBAL\DBALException
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
            ->execute()
            ->fetchAll();
    }
}