<?php

namespace Tpwd\KeSearch\Domain\Repository;

use Doctrine\DBAL\Driver\Statement;
use Tpwd\KeSearch\Lib\SearchHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
class FilterOptionRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = 'tx_kesearch_filteroptions';

    /**
     * @param string $tagPrefix
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed[]
     */
    public function findByTagPrefix(string $tagPrefix, bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->like(
                    'tag',
                    $queryBuilder->createNamedParameter($tagPrefix . '%', Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param string $tagPrefix
     * @param int $sys_language_uid
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed[]
     */
    public function findByTagPrefixAndLanguage(
        string $tagPrefix,
        int $sys_language_uid,
        bool $includeHiddenAndTimeRestricted = false
    ) {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->like(
                    'tag',
                    $queryBuilder->createNamedParameter($tagPrefix . '%', Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($sys_language_uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns all filter options for a given filter uid.
     *
     * @param $filterUid
     * @param bool $includeHiddenAndTimeRestricted
     * @return array|mixed[]
     */
    public function findByFilterUid($filterUid, bool $includeHiddenAndTimeRestricted = false)
    {
        /** @var FilterRepository $filterRepository */
        $filterRepository = GeneralUtility::makeInstance(FilterRepository::class);
        $filter = $filterRepository->findByUid($filterUid, $includeHiddenAndTimeRestricted);

        if (empty($filter) || empty($filter['options'])) {
            return [];
        }

        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter(
                        GeneralUtility::trimExplode(',', $filter['options']),
                        Connection::PARAM_INT_ARRAY
                    )
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param int $l10n_parent
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed[]
     */
    public function findByL10nParent(int $l10n_parent, bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($l10n_parent, Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param string $tag
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed[]
     */
    public function findByTag(string $tag, bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'tag',
                    $queryBuilder->createNamedParameter($tag, Connection::PARAM_STR)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param string $tag
     * @param int $sys_language_uid
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed[]
     */
    public function findByTagAndLanguage(
        string $tag,
        int $sys_language_uid,
        bool $includeHiddenAndTimeRestricted = false
    ) {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'tag',
                    $queryBuilder->createNamedParameter($tag, Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($sys_language_uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns all the filter options of a given filter with the given tag
     *
     * @param $filterUid
     * @param $tag
     * @param bool $includeHiddenAndTimeRestricted
     * @return array|mixed[]
     */
    public function findByFilterUidAndTag($filterUid, $tag, bool $includeHiddenAndTimeRestricted = false)
    {
        $options = $this->findByFilterUid($filterUid, $includeHiddenAndTimeRestricted);
        if (empty($options)) {
            return [];
        }

        foreach ($options as $key => $option) {
            if ($option['tag'] !== $tag) {
                unset($options[$key]);
            }
        }

        return $options;
    }

    /**
     * Creates a filter option record and adds it to the given filter
     *
     * @param int $filterUid
     * @param array $additionalFields
     * @return array
     */
    public function create(int $filterUid, array $additionalFields = [])
    {
        /** @var FilterRepository $filterRepository */
        $filterRepository = GeneralUtility::makeInstance(FilterRepository::class);
        $filter = $filterRepository->findByUid($filterUid, true);

        $newRecord = [
            'pid' => $filter['pid'],
            'crdate' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
            'tstamp' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
            'cruser_id' => isset($GLOBALS['BE_USER']->user['uid']) ? (int)$GLOBALS['BE_USER']->user['uid'] : 0,
            'l10n_diffsource' => '',
        ];
        $newRecord = array_merge($newRecord, $additionalFields);
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($this->tableName);
        $connection->insert(
            $this->tableName,
            $newRecord,
            ['l10n_diffsource' => Connection::PARAM_LOB]
        );
        $record = $newRecord;
        $record['uid'] = (int)$connection->lastInsertId();

        // Create slug
        $this->update($record['uid'], ['slug' => SearchHelper::createFilterOptionSlug($record)]);

        // add the new filter option to the filter
        $updateFields = [
            'options' => $filter['options'],
        ];
        if (!empty($updateFields['options'])) {
            $updateFields['options'] .= ',';
        }
        $updateFields['options'] .= $record['uid'];
        $filterRepository->update($filterUid, $updateFields);
        return $record;
    }

    /**
     * Removes the filter option with the given uid from all filters and deletes the record.
     *
     * @param int $filterOptionUid
     * @return Statement|int
     */
    public function deleteByUid(int $filterOptionUid)
    {
        /** @var FilterRepository $filterRepository */
        $filterRepository = GeneralUtility::makeInstance(FilterRepository::class);
        $filterRepository->removeFilterOptionFromFilter($filterOptionUid);

        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->delete($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($filterOptionUid, Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    /**
     * Removes the filter options with the given tag from all filters and deletes the record.
     *
     * @param string $tag
     */
    public function deleteByTag(string $tag)
    {
        $filterOptions = $this->findByTag($tag, true);
        if (!empty($filterOptions)) {
            foreach ($filterOptions as $filterOption) {
                $this->deleteByUid($filterOption['uid']);
            }
        }
    }

    /**
     * @param int $uid
     * @param array $updateFields
     * @return mixed
     */
    public function update(int $uid, array $updateFields)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            );
        foreach ($updateFields as $key => $value) {
            $queryBuilder->set($key, $value);
        }
        return $queryBuilder->executeStatement();
    }
}
