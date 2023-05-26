<?php

namespace Tpwd\KeSearch\Domain\Repository;

use Tpwd\KeSearch\Lib\SearchHelper;

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
class FilterRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = 'tx_kesearch_filters';

    /**
     * @param int $l10n_parent
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed
     */
    public function findByL10nParent(int $l10n_parent, bool $includeHiddenAndTimeRestricted)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($l10n_parent, \PDO::PARAM_INT)
                )
            )
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
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            );
        foreach ($updateFields as $key => $value) {
            $queryBuilder->set($key, $value);
        }
        return $queryBuilder->executeStatement();
    }

    /**
     * Returns the filter which has the given filter option assigned
     *
     * @param int $filterOptionUid
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed[]
     */
    public function findByAssignedFilterOption(int $filterOptionUid, bool $includeHiddenAndTimeRestricted)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->inSet(
                    'options',
                    (string)(int)$filterOptionUid
                )
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * remove filter option from the filter where it is used
     *
     * @param int $filterOptionUid
     */
    public function removeFilterOptionFromFilter(int $filterOptionUid)
    {
        $filter = $this->findByAssignedFilterOption($filterOptionUid, true);
        if (!empty($filter)) {
            $updateFields = [
                'options' => SearchHelper::rmFromList((string)$filterOptionUid, $filter['options']),
            ];
            $this->update($filter['uid'], $updateFields);
        }
    }
}
