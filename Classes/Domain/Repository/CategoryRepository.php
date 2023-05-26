<?php

namespace Tpwd\KeSearch\Domain\Repository;

use PDO;
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
class CategoryRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = 'sys_category';

    /**
     * @param $categoryUid
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed
     */
    public function findAllSubcategoriesByParentUid($categoryUid, bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'parent',
                    $queryBuilder->createNamedParameter($categoryUid, PDO::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param string $tag
     * @param bool $includeHiddenAndTimeRestricted
     * @return mixed
     */
    public function findByTag(string $tag, bool $includeHiddenAndTimeRestricted = false)
    {
        $uid = (int)(str_replace(SearchHelper::$systemCategoryPrefix, '', $tag));
        return $this->findByUid($uid, $includeHiddenAndTimeRestricted);
    }

    /**
     * Returns the system categories assigend to the record $uid in the table $tablename
     *
     * @param string $tableName
     * @param int $uid
     * @return mixed
     */
    public function findAssignedToRecord(string $tableName, int $uid, bool $includeHiddenAndTimeRestricted = false)
    {
        $queryBuilder = $this->getQueryBuilder($includeHiddenAndTimeRestricted);
        return $queryBuilder
            ->select('sys_category.*')
            ->from('sys_category')
            ->from('sys_category_record_mm')
            ->from($tableName)
            ->orderBy('sys_category_record_mm.sorting')
            ->where(
                $queryBuilder->expr()->eq(
                    'sys_category.uid',
                    $queryBuilder->quoteIdentifier('sys_category_record_mm.uid_local')
                ),
                $queryBuilder->expr()->eq(
                    $tableName . '.uid',
                    $queryBuilder->quoteIdentifier('sys_category_record_mm.uid_foreign')
                ),
                $queryBuilder->expr()->eq(
                    $tableName . '.uid',
                    $queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_category_record_mm.tablenames',
                    $queryBuilder->createNamedParameter($tableName)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
