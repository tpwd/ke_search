<?php

declare(strict_types=1);

namespace Tpwd\KeSearch\Domain\Repository;

use Doctrine\DBAL\Connection as DoctrineDbalConnection;
use PDO;

/***************************************************************
 *  Copyright notice
 *  (c) 2022 Christian Bülter
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
class FileReferenceRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected $tableName = 'sys_file_reference';

    public function findOneByTableAndFieldnameAndUidForeignAndLanguage(
        string $table,
        string $fieldname,
        int $uid_foreign,
        array $languageIds = [0, -1]
    ) {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'tablenames',
                    $queryBuilder->createNamedParameter($table)
                ),
                $queryBuilder->expr()->eq(
                    'fieldname',
                    $queryBuilder->createNamedParameter($fieldname)
                ),
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($uid_foreign, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageIds, DoctrineDbalConnection::PARAM_INT_ARRAY)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_state',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    't3ver_wsid',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                )
            )
            ->orderBy('sorting_foreign', 'asc')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
    }
}
